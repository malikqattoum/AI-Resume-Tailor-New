# Subscription & Stripe Integration Design

## Overview

Add a subscription/payment system using Stripe, with three tiers, usage tracking, and the Stripe Customer Portal for subscription management.

## Subscription Plans

| Tier  | Price     | Monthly Requests | Features                        |
|-------|-----------|------------------|----------------------------------|
| Free  | $0/mo     | 3 tailors/mo     | Full tailoring + cover letter    |
| Basic | $4.99/mo  | 20 tailors/mo    | Full tailoring + cover letter    |
| Pro   | $14.99/mo | Unlimited        | Full tailoring + cover letter    |

All tiers include cover letter generation.

## Database Tables

### `subscriptions` table

| Column                  | Type         | Description                              |
|-------------------------|--------------|------------------------------------------|
| `id`                    | bigint (PK)  | Primary key                              |
| `user_id`               | bigint (FK)  | References `users.id`, unique            |
| `stripe_subscription_id`| string       | Stripe subscription ID (nullable)        |
| `stripe_customer_id`    | string       | Stripe customer ID (nullable)            |
| `plan`                  | enum         | 'free', 'basic', 'pro'                   |
| `status`                | enum         | 'active', 'canceled', 'past_due', 'trialing' |
| `current_period_end`   | timestamp    | Subscription period end date             |
| `requests_used_this_period` | int      | Requests used in current billing period  |
| `created_at`            | timestamp    |                                          |
| `updated_at`            | timestamp    |                                          |

### `subscription_usage_logs` table

| Column      | Type         | Description                        |
|-------------|--------------|------------------------------------|
| `id`        | bigint (PK)  | Primary key                        |
| `user_id`   | bigint (FK)  | References `users.id`             |
| `action`    | string       | Action performed (e.g., 'tailor_request') |
| `created_at`| timestamp    | When the action occurred           |

### `users` table changes

- Add `stripe_customer_id` (nullable string)

## API Endpoints

| Method | URI                                        | Auth | Description                        |
|--------|--------------------------------------------|------|------------------------------------|
| GET    | `/api/subscription`                        | Yes  | Get current plan, usage, status    |
| POST   | `/api/subscription/create-portal-session`  | Yes  | Create Stripe portal session URL   |
| POST   | `/api/subscription/upgrade`                | Yes  | Upgrade to a paid plan             |
| POST   | `/api/webhooks/stripe`                     | No*  | Handle Stripe webhook events       |

*Webhooks use Stripe signature verification instead of auth tokens.

## Usage Flow

1. User makes a `/api/tailor` request
2. Backend checks `subscriptions.requests_used_this_period` for the current period
3. **If under limit** — process normally, increment counter, log to `subscription_usage_logs`
4. **If at limit** — return `402 Payment Required` with JSON:
   ```json
   {
     "success": false,
     "message": "Request limit reached",
     "error": "payment_required",
     "upgrade_url": "/subscription"
   }
   ```
5. Frontend detects `402` and navigates to upgrade screen

### Request Limit Reset

- Requests are counted per calendar month
- Counter resets on the 1st of each month
- Free tier blocked at 3 requests
- Basic blocked at 20 requests
- Pro has unlimited

## Stripe Integration

### Environment Variables (`.env`)

```
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_BASIC=price_...
STRIPE_PRICE_PRO=price_...
```

### Stripe Dashboard Setup (Manual)

1. Create 2 products in Stripe Dashboard:
   - "Basic Plan" — $4.99/month, recurring
   - "Pro Plan" — $14.99/month, recurring
2. Copy the `price_` IDs into `.env`

### Upgrade Flow

1. User calls `POST /api/subscription/upgrade` with `{ plan: 'basic' | 'pro' }`
2. Backend creates or retrieves existing Stripe Customer for the user
3. Creates a Stripe Checkout Session for the selected price
4. Returns `{ checkout_url: "https://checkout.stripe.com/..." }`
5. Frontend redirects user to Stripe Checkout
6. On success, Stripe redirects user back to app and sends `checkout.session.completed` webhook

### Customer Portal Flow

1. User calls `POST /api/subscription/create-portal-session`
2. Backend creates Stripe Billing Portal session
3. Returns `{ portal_url: "https://billing.stripe.com/..." }`
4. Frontend redirects user to portal to manage/cancel/upgrade/downgrade

### Webhook Events to Handle

| Event                              | Action                                              |
|------------------------------------|-----------------------------------------------------|
| `checkout.session.completed`       | Activate subscription, set period end                |
| `customer.subscription.updated`    | Sync plan/status changes                            |
| `customer.subscription.deleted`    | Revert user to free plan                            |
| `invoice.payment_failed`           | Mark subscription as `past_due`                     |
| `invoice.payment_succeeded`        | Reset `past_due` to `active`, reset usage counter   |

## Backend Components

### New Files

| File                                                    | Purpose                                    |
|---------------------------------------------------------|--------------------------------------------|
| `app/Models/Subscription.php`                           | Subscription model                         |
| `app/Models/SubscriptionUsageLog.php`                  | Usage log model                            |
| `app/Services/StripeService.php`                       | Stripe API wrapper                         |
| `app/Http/Controllers/SubscriptionController.php`       | Subscription management endpoints          |
| `app/Http/Controllers/WebhookController.php`           | Stripe webhook handler                      |
| `database/migrations/2026_04_03_000001_create_subscriptions_table.php` | Subscriptions table |
| `database/migrations/2026_04_03_000002_create_subscription_usage_logs_table.php` | Usage logs table |
| `database/migrations/2026_04_03_000003_add_stripe_customer_id_to_users_table.php` | User stripe column |

### Modified Files

| File                             | Change                                                    |
|----------------------------------|-----------------------------------------------------------|
| `app/Models/User.php`            | Add `stripe_customer_id` field, `subscription()` relation |
| `app/Http/Controllers/TailorController.php` | Add usage check before processing                   |
| `routes/api.php`                 | Add subscription + webhook routes                         |
| `app/Http/Kernel.php`             | Add webhook route outside of CSRF / auth middleware       |
| `.env`                           | Add Stripe keys + price IDs                              |

## Subscription Statuses

| Status     | Meaning                                            |
|------------|----------------------------------------------------|
| `active`   | Paid subscription, user can use service            |
| `canceled` | Subscription ended, user reverts to free           |
| `past_due` | Payment failed, access may be restricted soon      |
| `trialing` | Trial period (Stripe native)                       |

## Default Subscription Creation

- Every new user gets a free plan subscription created on registration
- `stripe_subscription_id` and `stripe_customer_id` are null until first paid subscription
