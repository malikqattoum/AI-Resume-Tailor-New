<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsageLog extends Model
{
    public $timestamps = false;

    const ACTION_TAILOR_REQUEST = 'tailor_request';

    const VALID_ACTIONS = [
        self::ACTION_TAILOR_REQUEST,
    ];

    protected $fillable = [
        'user_id',
        'action',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (empty($log->action)) {
                throw new \InvalidArgumentException('Action cannot be empty');
            }
            if (!in_array($log->action, self::VALID_ACTIONS)) {
                throw new \InvalidArgumentException('Invalid action: ' . $log->action);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
