<?php

namespace Tests\Feature;

use App\Enums\TenantStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\SlaPolicy;
use App\Models\SlaTarget;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $agent;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\TenantContext::clear();

        $this->tenant = Tenant::create([
            'name' => 'Acme Support',
            'slug' => 'acme-support',
            'status' => TenantStatus::ACTIVE,
        ]);

        $this->agent = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Agent Smith',
            'email' => 'agent@acme.com',
            'password' => 'password',
            'role' => UserRole::AGENT,
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Client Charlie',
            'email' => 'client@acme.com',
            'password' => 'password',
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        // Create Default SLA Policy
        $policy = SlaPolicy::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard SLA',
            'is_default' => true,
        ]);

        SlaTarget::create([
            'sla_policy_id' => $policy->id,
            'priority' => TicketPriority::HIGH->value,
            'first_response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
        ]);
    }

    public function test_can_create_ticket_with_sla_deadlines(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/tickets', [
            'title' => 'System Downtime Issue',
            'description' => 'Server is unreachable from office network.',
            'priority' => TicketPriority::HIGH->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'System Downtime Issue')
            ->assertJsonPath('data.priority', TicketPriority::HIGH->value)
            ->assertJsonPath('data.status', TicketStatus::OPEN->value);

        $this->assertNotNull($response->json('data.ticket_number'));
        $this->assertNotNull($response->json('data.first_response_due_at'));
        $this->assertNotNull($response->json('data.resolution_due_at'));
    }

    public function test_can_list_tickets_scoped_by_tenant(): void
    {
        Sanctum::actingAs($this->agent);

        Ticket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-10001',
            'title' => 'Billing inquiry',
            'description' => 'Invoice discrepancy',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::LOW,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/tickets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Billing inquiry');
    }

    public function test_can_update_ticket_status(): void
    {
        Sanctum::actingAs($this->agent);

        $ticket = Ticket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-10002',
            'title' => 'Software Crash',
            'description' => 'App closes on startup',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::HIGH,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->putJson("/api/v1/tickets/{$ticket->id}", [
            'status' => TicketStatus::RESOLVED->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TicketStatus::RESOLVED->value);

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_agent_comment_triggers_first_response_timestamp(): void
    {
        Sanctum::actingAs($this->agent);

        $ticket = Ticket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-10003',
            'title' => 'Network speed issue',
            'description' => 'Latency over 500ms',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::MEDIUM,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'We are investigating your network issue now.',
            'is_internal_note' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'We are investigating your network issue now.');

        $this->assertNotNull($ticket->fresh()->first_responded_at);
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_customer_cannot_view_internal_notes(): void
    {
        $ticket = Ticket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-10004',
            'title' => 'Access Request',
            'description' => 'Need VPN access',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::LOW,
            'customer_id' => $this->customer->id,
        ]);

        // Agent adds internal note
        Sanctum::actingAs($this->agent);
        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'content' => 'Internal note: Customer security check pending',
            'is_internal_note' => true,
        ]);

        // Customer views comments
        Sanctum::actingAs($this->customer);
        $response = $this->getJson("/api/v1/tickets/{$ticket->id}/comments");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
