<?php

namespace Tests\Feature;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_new_tenant_and_admin_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register-tenant', [
            'company_name' => 'Acme Corporation',
            'name' => 'John Doe',
            'email' => 'admin@acme.com',
            'password' => 'secret123',
            'phone' => '+1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'John Doe')
            ->assertJsonPath('data.user.email', 'admin@acme.com')
            ->assertJsonPath('data.user.role', UserRole::TENANT_ADMIN->value)
            ->assertJsonPath('data.user.tenant.name', 'Acme Corporation');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corporation',
            'status' => TenantStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@acme.com',
            'role' => UserRole::TENANT_ADMIN->value,
        ]);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $tenant = Tenant::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'status' => TenantStatus::ACTIVE,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alice Admin',
            'email' => 'alice@techcorp.com',
            'password' => 'password123',
            'role' => UserRole::TENANT_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@techcorp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'tenant'],
                    'token',
                ]
            ]);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
