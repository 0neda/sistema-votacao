<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $activeAdministrators = User::where('role', 'administrador')->where('is_active', true)->count();
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('users', [
            'users' => User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
                ->withExists(['participations', 'auditLogs', 'createdElections'])
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query->where('name', 'ilike', '%'.$filters['search'].'%')->orWhere('email', 'ilike', '%'.$filters['search'].'%')))
                ->orderBy('id')->paginate(20)->withQueryString()->through(fn (User $user) => [
                    'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                    'role' => $user->role, 'is_active' => $user->is_active,
                    'can_change_access' => ! ($user->role === 'administrador' && $user->is_active && $activeAdministrators <= 1),
                    'can_delete' => $user->id !== $request->user()->id && ! ($user->participations_exists || $user->audit_logs_exists || $user->created_elections_exists),
                ]),
            'search' => $filters['search'] ?? '',
        ]);
    }

    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $this->lockAdministration();
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $actor = $request->user()->fresh();
            Gate::forUser($actor)->authorize('update', $target);
            if ($target->role === 'administrador' && $target->is_active && ($request->input('role') !== 'administrador' || ! $request->boolean('is_active'))
                && User::where('role', 'administrador')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'O último administrador ativo não pode perder o acesso administrativo.']);
            }
            $previousRole = $target->role;
            $previousActive = $target->is_active;
            $target->forceFill($request->validated())->save();
            AuditLog::record('ALTERACAO_ACESSO_USUARIO', 'user', $target->id, metadata: [
                'previous_role' => $previousRole,
                'role' => $target->role,
                'previous_active' => $previousActive,
                'is_active' => $target->is_active,
            ]);
        }, attempts: 3);

        return to_route($request->user()->fresh()->can('viewAny', User::class) ? 'admin.users' : 'home')->with('success', 'Permissões do usuário atualizadas.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        DB::transaction(function () use ($request, $user): void {
            $this->lockAdministration();
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user()->fresh())->authorize('delete', $target);
            if ($target->id === $request->user()->id) {
                throw ValidationException::withMessages(['user' => 'Você não pode excluir sua própria conta nesta área.']);
            }
            if ($target->hasVotingHistory()) {
                throw ValidationException::withMessages(['user' => 'Esta conta possui histórico e não pode ser excluída. Desative-a para preservar votos e auditoria.']);
            }
            $target->delete();
            AuditLog::record('EXCLUSAO_USUARIO', 'user', $target->id);
        }, attempts: 3);

        return to_route('admin.users')->with('success', 'Conta sem histórico excluída.');
    }

    private function lockAdministration(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(731029)');
    }
}
