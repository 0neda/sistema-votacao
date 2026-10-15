<?php

namespace App\Http\Requests;

use App\Models\Election;
use Illuminate\Foundation\Http\FormRequest;

class AuditFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('audit', Election::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'round_id' => ['nullable', 'integer'], 'tab' => ['nullable', 'in:logs,votes'],
            'logs_page' => ['nullable', 'integer', 'min:1'], 'votes_page' => ['nullable', 'integer', 'min:1'], 'participants_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
