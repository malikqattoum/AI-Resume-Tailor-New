<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    private ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        if ($this->secretKey) {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function getOrCreateCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        DB::transaction(function () use ($user, $customer) {
            $user->update(['stripe_customer_id' => $customer->id]);
        });

        return $customer->id;
    }

    public function createCheckoutSession(User $user, string $priceId, string $successUrl = null, string $cancelUrl = null): string
    {
        $customerId = $this->getOrCreateCustomer($user);

        $session = Session::create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl ?? config('app.url') . '/subscription/success',
            'cancel_url' => $cancelUrl ?? config('app.url') . '/subscription/canceled',
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ],
        ]);

        return $session->url;
    }

    public function createPortalSession(User $user, string $returnUrl = null): string
    {
        $customerId = $this->getOrCreateCustomer($user);

        $session = PortalSession::create([
            'customer' => $customerId,
            'return_url' => $returnUrl ?? config('app.url'),
        ]);

        return $session->url;
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $subscription->cancel();
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error canceling subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getSubscription(string $subscriptionId): ?object
    {
        try {
            return \Stripe\Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error retrieving subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
