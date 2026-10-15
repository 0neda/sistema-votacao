<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['role' => ['required', 'in:eleitor,administrador,auditor'], 'is_active' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['role.in' => 'Selecione um perfil válido.', 'role.required' => 'Selecione o perfil.', 'is_active.required' => 'Informe a situação da conta.', 'is_active.boolean' => 'Situação da conta inválida.'];
    }
}
