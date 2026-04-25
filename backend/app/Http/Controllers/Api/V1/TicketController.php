<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\CreateTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Models\Ticket;
use App\Services\SlaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::with(['customer:id,name,email', 'assignedAgent:id,name,email', 'department:id,name', 'category:id,name']);

        // Scope to customer's own tickets if user is a Customer
        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        }

        // Apply Filters
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->filled('assigned_agent_id')) {
            $query->where('assigned_agent_id', $request->query('assigned_agent_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate($request->query('per_page', 15));

        return ApiResponse::success($tickets);
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $customerId = $validated['customer_id'] ?? null;
        if (! $customerId || $user->isCustomer()) {
            $customerId = $user->id;
        }

        // Auto-generate Ticket Number (e.g. TICK-84920)
        $ticketNumber = 'TICK-' . strtoupper(Str::random(6));

        // Calculate SLA Deadlines
        $slaDeadlines = SlaService::calculateDeadlines(
            $user->tenant_id,
            TicketPriority::from($validated['priority'])
        );

        $ticket = Ticket::create([
            'tenant_id' => $user->tenant_id,
            'ticket_number' => $ticketNumber,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => TicketStatus::OPEN,
            'priority' => $validated['priority'],
            'category_id' => $validated['category_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'customer_id' => $customerId,
            'first_response_due_at' => $slaDeadlines['first_response_due_at'],
            'resolution_due_at' => $slaDeadlines['resolution_due_at'],
        ]);

        return ApiResponse::success(
            $ticket->load(['customer:id,name,email', 'department:id,name', 'category:id,name']),
            'Ticket created successfully',
            201
        );
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Customer can only view their own ticket
        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            return ApiResponse::error('Unauthorized access to ticket', 403);
        }

        $ticket->load([
            'customer:id,name,email',
            'assignedAgent:id,name,email',
            'department:id,name',
            'category:id,name',
            'comments' => function ($query) use ($user) {
                if ($user->isCustomer()) {
                    $query->where('is_internal_note', false);
                }
                $query->with('user:id,name,email,avatar_url')->latest();
            },
            'attachments',
        ]);

        return ApiResponse::success($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            return ApiResponse::error('Unauthorized to update this ticket', 403);
        }

        $validated = $request->validated();

        // Track timestamp changes based on status updates
        if (isset($validated['status'])) {
            $newStatus = TicketStatus::from($validated['status']);
            if ($newStatus === TicketStatus::RESOLVED && ! $ticket->resolved_at) {
                $validated['resolved_at'] = now();
            } elseif ($newStatus === TicketStatus::CLOSED && ! $ticket->closed_at) {
                $validated['closed_at'] = now();
            }
        }

        $ticket->update($validated);

        return ApiResponse::success(
            $ticket->fresh(['customer:id,name,email', 'assignedAgent:id,name,email', 'department:id,name']),
            'Ticket updated successfully'
        );
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            return ApiResponse::error('Customers cannot delete tickets', 403);
        }

        $ticket->delete();

        return ApiResponse::success(null, 'Ticket deleted successfully');
    }
}
