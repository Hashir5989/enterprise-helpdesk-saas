<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Models\Department;
use App\Models\SlaPolicy;
use App\Models\SlaTarget;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function registerTenant(RegisterTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            $slug = $validated['slug'] ?? Str::slug($validated['company_name']);
            
            // Ensure slug uniqueness
            if (Tenant::where('slug', $slug)->exists()) {
                $slug = $slug . '-' . Str::random(4);
            }

            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'slug' => $slug,
                'status' => TenantStatus::ACTIVE,
            ]);

            // Create default SLA Policy for tenant
            $slaPolicy = SlaPolicy::create([
                'tenant_id' => $tenant->id,
                'name' => 'Standard SLA Policy',
                'description' => 'Default SLA response and resolution targets',
                'is_default' => true,
            ]);

            $slaTargets = [
                TicketPriority::LOW->value => ['response' => 1440, 'resolution' => 2880],
                TicketPriority::MEDIUM->value => ['response' => 720, 'resolution' => 1440],
                TicketPriority::HIGH->value => ['response' => 240, 'resolution' => 720],
                TicketPriority::URGENT->value => ['response' => 60, 'resolution' => 240],
            ];

            foreach ($slaTargets as $priority => $times) {
                SlaTarget::create([
                    'sla_policy_id' => $slaPolicy->id,
                    'priority' => $priority,
                    'first_response_time_minutes' => $times['response'],
                    'resolution_time_minutes' => $times['resolution'],
                ]);
            }

            // Create default Department for tenant
            Department::create([
                'tenant_id' => $tenant->id,
                'name' => 'General Support',
                'description' => 'Default support department',
                'is_active' => true,
            ]);

            // Create Tenant Admin User
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // hashed automatically via model cast
                'role' => UserRole::TENANT_ADMIN,
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user->load('tenant'),
                'token' => $token,
            ];
        });

        return ApiResponse::success($result, 'Tenant and admin account registered successfully', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error('Invalid email or password credentials', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('User account is deactivated', 403);
        }

        if ($user->tenant && $user->tenant->status !== TenantStatus::ACTIVE) {
            return ApiResponse::error('Tenant organization account is inactive', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user->load('tenant'),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => $request->user()->load('tenant'),
        ]);
    }
}
