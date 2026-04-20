<?php

namespace App\Services;

use App\Models\Tenant;

class TenantContext
{
    protected static ?Tenant $tenant = null;

    public static function set(?Tenant $tenant): void
    {
        static::$tenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return static::$tenant;
    }

    public static function id(): ?int
    {
        return static::$tenant?->id;
    }

    public static function hasTenant(): bool
    {
        return static::$tenant !== null;
    }

    public static function clear(): void
    {
        static::$tenant = null;
    }
}
