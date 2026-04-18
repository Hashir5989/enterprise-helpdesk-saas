<?php

namespace App\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'sla_policy_id',
        'priority',
        'first_response_time_minutes',
        'resolution_time_minutes',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'first_response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
    ];

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }
}
