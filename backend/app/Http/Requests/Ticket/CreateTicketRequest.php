<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'customer_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
