<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\AddCommentRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            return ApiResponse::error('Unauthorized access to ticket comments', 403);
        }

        $query = $ticket->comments()->with('user:id,name,email,avatar_url');

        if ($user->isCustomer()) {
            $query->where('is_internal_note', false);
        }

        $comments = $query->latest()->get();

        return ApiResponse::success($comments);
    }

    public function store(AddCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            return ApiResponse::error('Unauthorized to add comment to this ticket', 403);
        }

        $validated = $request->validated();

        $isInternalNote = $user->isCustomer() ? false : ($validated['is_internal_note'] ?? false);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => $validated['content'],
            'is_internal_note' => $isInternalNote,
        ]);

        // Record first_responded_at timestamp if non-customer/agent makes first public response
        if (! $user->isCustomer() && ! $isInternalNote && ! $ticket->first_responded_at) {
            $ticket->update([
                'first_responded_at' => now(),
                'status' => TicketStatus::IN_PROGRESS,
            ]);
        }

        return ApiResponse::success(
            $comment->load('user:id,name,email,avatar_url'),
            'Comment added successfully',
            201
        );
    }
}
