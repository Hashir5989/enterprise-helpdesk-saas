<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_policy_id')->constrained('sla_policies')->onDelete('cascade');
            $table->string('priority'); // TicketPriority Enum (low, medium, high, urgent)
            $table->integer('first_response_time_minutes'); // Target response time in minutes
            $table->integer('resolution_time_minutes'); // Target resolution time in minutes
            $table->timestamps();

            $table->unique(['sla_policy_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_targets');
    }
};
