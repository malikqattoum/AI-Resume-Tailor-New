<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Basic = 'basic';
    case Pro = 'pro';

    public function limit(): int
    {
        return match ($this) {
            self::Free => 3,
            self::Basic => 20,
            self::Pro => PHP_INT_MAX,
        };
    }
}
