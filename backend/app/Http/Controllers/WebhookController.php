<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class WebhookController extends Controller
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle incoming Stripe webhooks.
     *
     * POST /api/webhooks/stripe
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $decodedPayload = json_decode($payload, true);
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            Log::warning('Stripe webhook secret not configured - skipping signature verification');
            $event = Event::constructFrom($decodedPayload);
        } else {
            try {
                $event = Event::constructFrom($decodedPayload, ['signing_secret' => $webhookSecret]);
            } catch (\UnexpectedValueException $e) {
                Log::error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        Log::info('Stripe webhook event received', ['type' => $event->type]);

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                'invoice.payment_failed' => $this->handlePaymentFailed($event),
                'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook handler error', [
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Handler failed'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $userId = $session->metadata->user_id;
        $subscriptionId = $session->subscription ?? null;

        if (!$userId || !$subscriptionId) {
            Log::warning('Webhook checkout.session.completed: missing metadata', [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::warning('Webhook checkout.session.completed: user not found', ['user_id' => $userId]);
            return;
        }

        $stripeSub = $this->stripeService->getSubscription($subscriptionId);
        if (!$stripeSub) {
            Log::warning('Webhook checkout.session.completed: could not retrieve Stripe subscription', [
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        $plan = $this->mapPriceToPlan($stripeSub->items->data[0]->price->id ?? null);
        $status = $stripeSub->status === 'active' ? SubscriptionStatus::Active->value : ($stripeSub->status === 'trialing' ? SubscriptionStatus::Trialing->value : SubscriptionStatus::Active->value);

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_subscription_id' => $subscriptionId,
                'stripe_customer_id' => $session->customer,
                'plan' => $plan,
                'status' => $status,
                'current_period_end' => now()->createFromTimestamp($stripeSub->current_period_end),
                'requests_used_this_period' => 0,
            ]
        );

        Log::info('Webhook: Subscription activated', [
            'user_id' => $userId,
            'plan' => $plan,
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function handleSubscriptionUpdated(Event $event): void
    {
        $sub = $event->data->object;
        $subscriptionId = $sub->id;

        $subscription = Subscription::findByStripeSubscriptionId($subscriptionId);
        if (!$subscription) {
            Log::warning('Webhook: Subscription not found', [
                'subscription_id' => $subscriptionId,
                'event_type' => $event->type,
            ]);
            return;
        }

        $plan = $this->mapPriceToPlan($sub->items->data[0]->price->id ?? null);
        $status = match ($sub->status) {
            'active' => SubscriptionStatus::Active->value,
            'past_due' => SubscriptionStatus::PastDue->value,
            'canceled' => SubscriptionStatus::Canceled->value,
            'trialing' => SubscriptionStatus::Trialing->value,
            default => $subscription->status,
        };

        $subscription->update([
            'plan' => $plan,
            'status' => $status,
            'current_period_end' => now()->createFromTimestamp($sub->current_period_end),
        ]);

        Log::info('Webhook: Subscription updated', [
            'subscription_id' => $subscriptionId,
            'plan' => $plan,
            'status' => $status,
        ]);
    }

    private function handleSubscriptionDeleted(Event $event): void
    {
        $sub = $event->data->object;
        $subscriptionId = $sub->id;

        $subscription = Subscription::findByStripeSubscriptionId($subscriptionId);
        if (!$subscription) {
            Log::warning('Webhook: Subscription not found', [
                'subscription_id' => $subscriptionId,
                'event_type' => $event->type,
            ]);
            return;
        }

        $subscription->update([
            'plan' => Plan::Free->value,
            'status' => SubscriptionStatus::Canceled->value,
            'stripe_subscription_id' => null,
        ]);

        Log::info('Webhook: Subscription canceled, reverted to free', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function handlePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::findByStripeSubscriptionId($subscriptionId);
        if (!$subscription) {
            Log::warning('Webhook: Subscription not found', [
                'subscription_id' => $subscriptionId,
                'event_type' => $event->type,
            ]);
            return;
        }

        $subscription->update(['status' => SubscriptionStatus::PastDue->value]);

        Log::info('Webhook: Payment failed, marked past_due', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function handlePaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::findByStripeSubscriptionId($subscriptionId);
        if (!$subscription) {
            Log::warning('Webhook: Subscription not found', [
                'subscription_id' => $subscriptionId,
                'event_type' => $event->type,
            ]);
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active->value,
            'requests_used_this_period' => 0,
        ]);

        Log::info('Webhook: Payment succeeded, usage reset', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function mapPriceToPlan(?string $priceId): string
    {
        if (!$priceId) {
            return Plan::Free->value;
        }

        $basicPrice = config('services.stripe.price_basic');
        $proPrice = config('services.stripe.price_pro');

        if ($priceId === $basicPrice) {
            return Plan::Basic->value;
        }
        if ($priceId === $proPrice) {
            return Plan::Pro->value;
        }

        Log::warning('Webhook: Unknown price ID received', [
            'price_id' => $priceId,
        ]);
        return Plan::Free->value;
    }
}
