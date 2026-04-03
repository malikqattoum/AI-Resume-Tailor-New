# Subscription & Stripe Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Stripe-powered subscription tiers (Free/Basic/Pro) with usage tracking, upgrade flow via Stripe Checkout, subscription management via Stripe Customer Portal, and webhook handlers to keep subscription state in sync.

**Architecture:** Laravel backend with a `subscriptions` table tracking each user's plan and usage. Stripe handles payments/checkout/portal. The `TailorController` checks usage before processing. Webhooks keep subscription status synced with Stripe.

**Tech Stack:** Laravel 8, Stripe PHP SDK, Sanctum auth, MySQL

---

## Prerequisite: Install Stripe PHP SDK

**Modify:** `backend/composer.json`

**Step 1: Add Stripe package**

```bash
cd backend && composer require stripe/stripe-php
```

---

## Task 1: Create Migrations

**Files:**
- Create: `backend/database/migrations/2026_04_03_000001_create_subscriptions_table.php`
- Create: `backend/database/migrations/2026_04_03_000002_create_subscription_usage_logs_table.php`
- Create: `backend/database/migrations/2026_04_03_000003_add_stripe_customer_id_to_users_table.php`

**Step 1: Create migration for subscriptions table**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->enum('plan', ['free', 'basic', 'pro'])->default('free');
            $table->enum('status', ['active', 'canceled', 'past_due', 'trialing'])->default('active');
            $table->timestamp('current_period_end')->nullable();
            $table->unsignedInteger('requests_used_this_period')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
}
```

**Step 2: Create migration for usage logs table**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionUsageLogsTable extends Migration
{
    public function up()
    {
        Schema::create('subscription_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // e.g., 'tailor_request'
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_usage_logs');
    }
}
```

**Step 3: Create migration to add stripe_customer_id to users**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeCustomerIdToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
}
```

**Step 4: Run migrations**

```bash
cd backend && php artisan migrate
```

---

## Task 2: Create Subscription Model

**Files:**
- Create: `backend/app/Models/Subscription.php`
- Create: `backend/app/Models/SubscriptionUsageLog.php`

**Step 1: Write failing test — Subscription model**

```php
<?php
// backend/tests/Unit/SubscriptionTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionTest extends TestCase
{
    public function test_subscription_belongs_to_user()
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
        ]);
        $this->assertEquals($user->id, $subscription->user->id);
    }

    public function test_free_plan_has_limit()
    {
        $sub = new Subscription(['plan' => 'free']);
        $this->assertEquals(3, $sub->monthly_limit);
    }

    public function test_basic_plan_has_limit()
    {
        $sub = new Subscription(['plan' => 'basic']);
        $this->assertEquals(20, $sub->monthly_limit);
    }

    public function test_pro_plan_has_unlimited()
    {
        $sub = new Subscription(['plan' => 'pro']);
        $this->assertEquals(PHP_INT_MAX, $sub->monthly_limit);
    }

    public function test_is_at_limit()
    {
        $sub = new Subscription(['plan' => 'free', 'requests_used_this_period' => 3]);
        $this->assertTrue($sub->isAtLimit());
    }

    public function test_is_not_at_limit()
    {
        $sub = new Subscription(['plan' => 'free', 'requests_used_this_period' => 2]);
        $this->assertFalse($sub->isAtLimit());
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
cd backend && php artisan test --filter=SubscriptionTest
```

**Step 3: Write Subscription model**

```php
<?php
// backend/app/Models/Subscription.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'plan',
        'status',
        'current_period_end',
        'requests_used_this_period',
    ];

    protected $casts = [
        'current_period_end' => 'datetime',
        'requests_used_this_period' => 'integer',
    ];

    const PLAN_LIMITS = [
        'free' => 3,
        'basic' => 20,
        'pro' => PHP_INT_MAX,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMonthlyLimitAttribute(): int
    {
        return self::PLAN_LIMITS[$this->plan] ?? 3;
    }

    public function isAtLimit(): bool
    {
        return $this->requests_used_this_period >= $this->monthly_limit;
    }

    public function hasUnlimitedRequests(): bool
    {
        return $this->plan === 'pro';
    }

    public function incrementUsage(): void
    {
        $this->increment('requests_used_this_period');
    }

    public function resetUsage(): void
    {
        $this->update(['requests_used_this_period' => 0]);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'trialing';
    }
}
```

**Step 4: Write SubscriptionUsageLog model**

```php
<?php
// backend/app/Models/SubscriptionUsageLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
cd backend && php artisan test --filter=SubscriptionTest
```

---

## Task 3: Update User Model

**Files:**
- Modify: `backend/app/Models/User.php`

**Step 1: Read current User model**

Read `backend/app/Models/User.php`

**Step 2: Add subscription relation and stripe_customer_id**

```php
// Add after existing casts array:
protected $casts = [
    'email_verified_at' => 'datetime',
];

// Add these two methods:
public function subscription()
{
    return $this->hasOne(Subscription::class);
}

public function subscriptionUsageLogs()
{
    return $this->hasMany(SubscriptionUsageLog::class);
}
```

---

## Task 4: Create StripeService

**Files:**
- Create: `backend/app/Services/StripeService.php`

**Step 1: Write failing test — StripeService**

```php
<?php
// backend/tests/Unit/StripeServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\StripeService;
use App\Models\User;
use App\Models\Subscription;

class StripeServiceTest extends TestCase
{
    public function test_get_or_create_customer_creates_customer()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $service = new StripeService();
        
        // Mock Stripe customer creation
        $this->mockStripeCustomerCreation($user, 'cus_test123');
        
        $customerId = $service->getOrCreateCustomer($user);
        $this->assertEquals('cus_test123', $customerId);
    }

    public function test_get_or_create_customer_returns_existing()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'stripe_customer_id' => 'cus_existing',
        ]);
        $service = new StripeService();
        
        $customerId = $service->getOrCreateCustomer($user);
        $this->assertEquals('cus_existing', $customerId);
    }

    public function test_create_checkout_session_returns_url()
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        $service = new StripeService();
        
        // Mock Stripe checkout session
        $session = new \stdClass();
        $session->url = 'https://checkout.stripe.com/test';
        
        $mock = $this->mockStripeService();
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($session);
        
        $url = $service->createCheckoutSession($user, 'price_basic');
        $this->assertStringContainsString('checkout.stripe.com', $url);
    }

    public function test_create_portal_session_returns_url()
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        $service = new StripeService();
        
        $session = new \stdClass();
        $session->url = 'https://billing.stripe.com/test';
        
        $mock = $this->mockStripeService();
        $mock->shouldReceive('createPortalSession')
            ->once()
            ->andReturn($session);
        
        $url = $service->createPortalSession($user);
        $this->assertStringContainsString('billing.stripe.com', $url);
    }

    private function mockStripeCustomerCreation($user, $customerId) { /* ... */ }
    private function mockStripeService() { /* ... */ }
}
```

**Step 2: Run tests to verify they fail**

```bash
cd backend && php artisan test --filter=StripeServiceTest
```

**Step 3: Write StripeService**

```php
<?php
// backend/app/Services/StripeService.php
namespace App\Services;

use App\Models\User;
use App\Models\Subscription;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        Stripe::setApiKey($this->secretKey);
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

        $user->update(['stripe_customer_id' => $customer->id]);

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

    public function cancelSubscription(string $subscriptionId): void
    {
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        $subscription->cancel();
    }

    public function getSubscription(string $subscriptionId): ?object
    {
        try {
            return \Stripe\Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            return null;
        }
    }
}
```

**Step 4: Add Stripe config to services.php**

**Modify:** `backend/config/services.php`

Add after existing services array entries:
```php
'stripe' => [
    'secret' => env('STRIPE_SECRET_KEY'),
    'price_basic' => env('STRIPE_PRICE_BASIC'),
    'price_pro' => env('STRIPE_PRICE_PRO'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

---

## Task 5: Create SubscriptionController

**Files:**
- Create: `backend/app/Http/Controllers/SubscriptionController.php`

**Step 1: Write failing test — SubscriptionController**

```php
<?php
// backend/tests/Feature/SubscriptionControllerTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;

class SubscriptionControllerTest extends TestCase
{
    public function test_get_subscription_returns_user_subscription()
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
            'requests_used_this_period' => 1,
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/subscription');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => 'free',
                    'status' => 'active',
                    'requests_used_this_period' => 1,
                    'monthly_limit' => 3,
                ],
            ]);
    }

    public function test_create_portal_session_returns_url()
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => 'active',
        ]);
        
        Sanctum::actingAs($user);
        
        $this->mockStripePortalSession('https://billing.stripe.com/test');
        
        $response = $this->postJson('/api/subscription/create-portal-session');
        
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['portal_url']]);
    }

    public function test_upgrade_creates_checkout_session()
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
        ]);
        
        Sanctum::actingAs($user);
        
        $this->mockStripeCheckoutSession('https://checkout.stripe.com/test');
        
        $response = $this->postJson('/api/subscription/upgrade', ['plan' => 'basic']);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['checkout_url']]);
    }

    public function test_upgrade_with_invalid_plan_returns_422()
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan' => 'free', 'status' => 'active']);
        
        Sanctum::actingAs($user);
        
        $response = $this->postJson('/api/subscription/upgrade', ['plan' => 'invalid']);
        
        $response->assertStatus(422);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
cd backend && php artisan test --filter=SubscriptionControllerTest
```

**Step 3: Write SubscriptionController**

```php
<?php
// backend/app/Http/Controllers/SubscriptionController.php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
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
            // Create free subscription if none exists
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan' => 'free',
                'status' => 'active',
            ]);
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
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to manage.',
            ], 400);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to create portal session: ' . $e->getMessage(),
            ], 500);
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $plan = $request->input('plan');

        $priceId = config("services.stripe.price_{$plan}");

        if (!$priceId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid plan configuration.',
            ], 500);
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
cd backend && php artisan test --filter=SubscriptionControllerTest
```

---

## Task 6: Create WebhookController

**Files:**
- Create: `backend/app/Http/Controllers/WebhookController.php`

**Step 1: Write failing test — WebhookController**

```php
<?php
// backend/tests/Feature/WebhookControllerTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completed_activates_subscription()
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
        ]);

        $payload = $this->buildStripeWebhookPayload('checkout.session.completed', [
            'customer' => $user->stripe_customer_id ?? 'cus_test',
            'subscription' => 'sub_test123',
            'metadata' => ['user_id' => $user->id],
        ]);

        $this->assertEquals('active', $user->subscription->status);
    }

    public function test_subscription_deleted_reverts_to_free()
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test',
        ]);
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test123',
        ]);

        $this->assertEquals('pro', $user->subscription->plan);
    }

    public function test_payment_failed_marks_subscription_past_due()
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => 'active',
        ]);

        $this->assertEquals('active', $user->subscription->status);
    }

    private function buildStripeWebhookPayload($eventType, $data) { /* ... */ }
}
```

**Step 2: Run tests to verify they fail**

```bash
cd backend && php artisan test --filter=WebhookControllerTest
```

**Step 3: Write WebhookController**

```php
<?php
// backend/app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\振振;
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
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Event::constructFrom(
                json_decode($payload, true),
                $webhookSecret ? ['signing_secret' => $webhookSecret] : null
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        if ($webhookSecret) {
            try {
                Event::constructFrom(
                    json_decode($payload, true),
                    ['signing_secret' => $webhookSecret]
                );
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
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $userId = $session->metadata->user_id ?? null;
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

        // Get the subscription details from Stripe
        $stripeSub = $this->stripeService->getSubscription($subscriptionId);
        if (!$stripeSub) {
            Log::warning('Webhook checkout.session.completed: could not retrieve Stripe subscription', [
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        $plan = $this->mapPriceToPlan($stripeSub->items->data[0]->price->id ?? null);
        $status = $stripeSub->status === 'active' ? 'active' : ($stripeSub->status === 'trialing' ? 'trialing' : 'active');

        $subscription = Subscription::updateOrCreate(
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

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $plan = $this->mapPriceToPlan($sub->items->data[0]->price->id ?? null);
        $status = match ($sub->status) {
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'trialing' => 'trialing',
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

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'plan' => 'free',
            'status' => 'canceled',
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

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

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

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        // Reset usage on successful payment (new billing period)
        $subscription->update([
            'status' => 'active',
            'requests_used_this_period' => 0,
        ]);

        Log::info('Webhook: Payment succeeded, usage reset', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function mapPriceToPlan(?string $priceId): string
    {
        if (!$priceId) {
            return 'free';
        }

        $basicPrice = config('services.stripe.price_basic');
        $proPrice = config('services.stripe.price_pro');

        if ($priceId === $basicPrice) {
            return 'basic';
        }
        if ($priceId === $proPrice) {
            return 'pro';
        }

        return 'free';
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
cd backend && php artisan test --filter=WebhookControllerTest
```

---

## Task 7: Update AuthController to Create Free Subscription on Registration

**Files:**
- Modify: `backend/app/Http/Controllers/AuthController.php`

**Step 1: Read AuthController**

Read `backend/app/Http/Controllers/AuthController.php`

**Step 2: Add subscription creation after user registration**

In the `register` method, after `$user = User::create([...])`, add:

```php
// Create free subscription for new user
Subscription::create([
    'user_id' => $user->id,
    'plan' => 'free',
    'status' => 'active',
]);
```

Add the import at the top:
```php
use App\Models\Subscription;
```

---

## Task 8: Update TailorController to Check Usage Limits

**Files:**
- Modify: `backend/app/Http/Controllers/TailorController.php`

**Step 1: Read TailorController**

Read `backend/app/Http/Controllers/TailorController.php`

**Step 2: Add usage check at the start of tailor() method**

Before the validator check, add:

```php
// Check subscription usage limit
$user = $request->user();
$subscription = $user->subscription ?? Subscription::create([
    'user_id' => $user->id,
    'plan' => 'free',
    'status' => 'active',
]);

if (!$subscription->isActive()) {
    return response()->json([
        'success' => false,
        'message' => 'Your subscription is not active.',
        'error' => 'subscription_inactive',
    ], 403);
}

if ($subscription->isAtLimit()) {
    $portalUrl = null;
    try {
        $stripeService = app(\App\Services\StripeService::class);
        if ($user->stripe_customer_id) {
            $portalUrl = $stripeService->createPortalSession($user);
        }
    } catch (\Exception $e) {
        // Portal unavailable, still return the limit error
    }

    return response()->json([
        'success' => false,
        'message' => 'Monthly request limit reached.',
        'error' => 'payment_required',
        'upgrade_url' => $portalUrl,
    ], 402);
}
```

After successful tailoring, add usage increment and log:

```php
// Increment usage counter
$subscription->incrementUsage();

// Log usage
SubscriptionUsageLog::create([
    'user_id' => $user->id,
    'action' => 'tailor_request',
]);
```

Add imports:
```php
use App\Models\Subscription;
use App\Models\SubscriptionUsageLog;
```

---

## Task 9: Add Routes

**Files:**
- Modify: `backend/routes/api.php`

**Step 1: Read current api.php and add subscription routes**

After the existing protected routes group, add:

```php
// Subscription routes (protected)
Route::middleware('auth:sanctum')->group(function () {
    // ... existing routes ...

    // Subscription management
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/create-portal-session', [SubscriptionController::class, 'createPortalSession']);
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
});

// Stripe webhook (no auth - uses Stripe signature verification)
Route::post('/webhooks/stripe', [WebhookController::class, 'handle']);
```

Add controller imports:
```php
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
```

---

## Task 10: Add Webhook Route to Kernel (Skip CSRF)

**Files:**
- Modify: `backend/app/Http/Kernel.php` (or `app/Http/Middleware/VerifyCsrfToken.php`)

**Step 1: Read Kernel or VerifyCsrfToken**

**Step 2: Add webhook to except array**

In `VerifyCsrfToken.php`, add to the `$except` array:

```php
protected $except = [
    'api/webhooks/stripe',
];
```

---

## Task 11: Update .env with Stripe Keys

**Files:**
- Modify: `backend/.env`

**Step 1: Add Stripe environment variables**

```
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_BASIC=price_...
STRIPE_PRICE_PRO=price_...
```

Also update `.env.example` with placeholder values.

---

## Task 12: Update SPEC.md

**Files:**
- Modify: `SPEC.md`

Add the new subscription section and update the API endpoints table.

---

## Execution Options

**1. Subagent-Driven (this session)** — I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** — Open new session with executing-plans, batch execution with checkpoints

Which approach?
