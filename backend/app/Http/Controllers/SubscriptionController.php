<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Services\StripeService;
use App\Traits\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    use ApiResponses;

    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Get current user's subscription details.
     *
     * GET /api/subscription
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            $subscription = Subscription::firstOrCreateFreeForUser($user->id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'requests_used_this_period' => $subscription->requests_used_this_period,
                'monthly_limit' => $subscription->monthly_limit,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'is_active' => $subscription->isActive(),
            ],
        ]);
    }

    /**
     * Create Stripe Customer Portal session.
     *
     * POST /api/subscription/create-portal-session
     */
    public function createPortalSession(Request $request)
    {
        $user = $request->user();

        if (!$user->stripe_customer_id) {
            return $this->errorResponse('No active subscription to manage.', null, 400);
        }

        try {
            $portalUrl = $this->stripeService->createPortalSession($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'portal_url' => $portalUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create portal session: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Upgrade to a paid plan via Stripe Checkout.
     *
     * POST /api/subscription/upgrade
     * Body: { "plan": "basic" | "pro" }
     */
    public function upgrade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:basic,pro',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $user = $request->user();
        $plan = $request->input('plan');

        $priceId = config("services.stripe.price_{$plan}");

        if (!$priceId) {
            return $this->errorResponse('Invalid plan configuration.', null, 500);
        }

        try {
            $checkoutUrl = $this->stripeService->createCheckoutSession($user, $priceId);

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => $checkoutUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create checkout session: ' . $e->getMessage(), null, 500);
        }
    }
}
