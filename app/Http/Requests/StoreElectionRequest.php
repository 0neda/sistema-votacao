<?php

namespace App\Http\Requests;

use App\Models\Election;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Election::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $candidates = $this->input('candidates');
        if (is_array($candidates)) {
            $this->merge(['candidates' => array_map(fn ($candidate) => is_array($candidate) && is_string($candidate['name'] ?? null) ? [...$candidate, 'name' => trim($candidate['name'])] : $candidate, $candidates)]);
        }
        $this->normalizeSchedule();
    }

    protected function normalizeSchedule(): void
    {
        $value = $this->input('scheduled_at');
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $value, 'America/Sao_Paulo');
                if ($date !== null && $date->format('Y-m-d\\TH:i') === $value) {
                    $this->merge(['scheduled_at' => $date->utc()->toIso8601String()]);
                }
            } catch (\Exception) { /* Invalid dates are rejected by validation. */
            }
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'opening' => ['required', 'in:imediata,agendada'],
            'scheduled_at' => ['exclude_unless:opening,agendada', 'required', 'date', 'after:now'],
            'candidates' => ['required', 'array', 'size:3'], 'candidates.*' => ['required', 'array:name,photo'],
            'candidates.*.name' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'candidates.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
        ];
    }

    public function messages(): array
    {
        return ['*.required' => 'Preencha este campo.', 'required' => 'Preencha este campo.',
            'candidates.size' => 'Informe exatamente três candidatos.',
            'candidates.*.name.distinct' => 'Os três candidatos devem ter nomes distintos.',
            'scheduled_at.after' => 'Escolha uma data e hora futuras (horário de Brasília).',
            'candidates.*.photo.image' => 'Envie uma imagem válida.',
            'candidates.*.photo.mimes' => 'Use uma foto JPG, PNG ou WebP.',
            'candidates.*.photo.max' => 'A foto deve ter no máximo 2 MB.',
            '*.max' => 'O texto ultrapassa o tamanho permitido.'];
    }
}
