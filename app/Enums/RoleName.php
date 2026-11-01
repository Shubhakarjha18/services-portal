<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Officer = 'officer';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Officer => 'Case Officer',
            self::Public => 'Public User',
        };
    }

    public static function staff(): array
    {
        return [self::Admin, self::Officer];
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }
}
