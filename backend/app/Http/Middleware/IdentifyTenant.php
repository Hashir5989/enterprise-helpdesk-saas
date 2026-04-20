<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Check header X-Tenant-ID or X-Tenant-Slug
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenant = Tenant::find($request->header('X-Tenant-ID'));
        } elseif ($request->hasHeader('X-Tenant-Slug')) {
            $tenant = Tenant::where('slug', $request->header('X-Tenant-Slug'))->first();
        }

        // 2. Fallback to authenticated user's tenant
        if (! $tenant && $request->user() && $request->user()->tenant_id) {
            $tenant = $request->user()->tenant;
        }

        if ($tenant) {
            if ($tenant->status !== \App\Enums\TenantStatus::ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant account is inactive or suspended',
                ], Response::HTTP_FORBIDDEN);
            }

            TenantContext::set($tenant);
        }

        return $next($request);
    }
}
