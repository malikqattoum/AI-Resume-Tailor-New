<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($subscription) {
            if (!in_array($subscription->plan, array_column(Plan::cases(), 'value'))) {
                throw new \InvalidArgumentException('Invalid plan: ' . $subscription->plan);
            }
            if (!in_array($subscription->status, array_column(SubscriptionStatus::cases(), 'value'))) {
                throw new \InvalidArgumentException('Invalid status: ' . $subscription->status);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMonthlyLimitAttribute(): int
    {
        try {
            return Plan::from($this->plan)->limit();
        } catch (\ValueError $e) {
            throw new \RuntimeException('Unknown plan: ' . $this->plan);
        }
    }

    public function isAtLimit(): bool
    {
        return $this->requests_used_this_period >= $this->monthly_limit;
    }

    public function hasUnlimitedRequests(): bool
    {
        return $this->plan === Plan::Pro->value;
    }

    public function incrementUsage(): void
    {
        if (!$this->isActive()) {
            Log::warning('Attempted to increment usage on inactive subscription', [
                'subscription_id' => $this->id,
                'user_id' => $this->user_id,
                'status' => $this->status,
            ]);
            return;
        }
        if ($this->hasUnlimitedRequests()) {
            return;
        }
        $this->increment('requests_used_this_period');
    }

    public function resetUsage(): void
    {
        $this->update(['requests_used_this_period' => 0]);
    }

    public function isActive(): bool
    {
        return SubscriptionStatus::from($this->status)->isActive();
    }

    public static function findByStripeSubscriptionId(string $stripeSubscriptionId): ?self
    {
        return static::where('stripe_subscription_id', $stripeSubscriptionId)->first();
    }

    public static function firstOrCreateFreeForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['plan' => Plan::Free->value, 'status' => SubscriptionStatus::Active->value]
        );
    }
}
