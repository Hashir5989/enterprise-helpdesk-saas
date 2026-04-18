<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_ADMIN = 'tenant_admin';
    case AGENT = 'agent';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::TENANT_ADMIN => 'Tenant Admin',
            self::AGENT => 'Support Agent',
            self::CUSTOMER => 'Customer',
        };
    }
}
