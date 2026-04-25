<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use App\Models\SlaTarget;
use Carbon\Carbon;

class SlaService
{
    /**
     * Calculate first response and resolution deadlines for a given tenant and priority.
     *
     * @return array{first_response_due_at: ?Carbon, resolution_due_at: ?Carbon}
     */
    public static function calculateDeadlines(int $tenantId, TicketPriority|string $priority, ?Carbon $fromTime = null): array
    {
        $fromTime = $fromTime ?? now();
        $priorityValue = $priority instanceof TicketPriority ? $priority->value : $priority;

        $policy = SlaPolicy::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first() ?? SlaPolicy::where('tenant_id', $tenantId)->first();

        if (! $policy) {
            return [
                'first_response_due_at' => null,
                'resolution_due_at' => null,
            ];
        }

        $target = SlaTarget::where('sla_policy_id', $policy->id)
            ->where('priority', $priorityValue)
            ->first();

        if (! $target) {
            return [
                'first_response_due_at' => null,
                'resolution_due_at' => null,
            ];
        }

        return [
            'first_response_due_at' => $fromTime->copy()->addMinutes($target->first_response_time_minutes),
            'resolution_due_at' => $fromTime->copy()->addMinutes($target->resolution_time_minutes),
        ];
    }
}
