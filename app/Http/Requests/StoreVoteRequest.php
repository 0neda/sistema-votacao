<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use App\Models\Round;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vote', $this->route('round')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['candidate_id' => ['required', 'integer'], 'confirmed' => ['accepted']];
    }

    public function messages(): array
    {
        return ['candidate_id.required' => 'Selecione um candidato.', 'candidate_id.integer' => 'Candidato inválido.', 'confirmed.accepted' => 'Confirme que seu voto é definitivo e auditável.'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $round = $this->route('round');
        abort_unless($round instanceof Round, 404);
        AuditLog::record('VOTO_REJEITADO', 'round', $round->id, 'rejeitado', ['reason' => 'validacao']);
        parent::failedValidation($validator);
    }
}
