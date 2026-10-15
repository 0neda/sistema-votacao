<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Hash;

test('only administrators can manage accounts', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $target = User::factory()->create();
    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
    $this->patch(route('users.update', $target), ['role' => 'administrador', 'is_active' => true])->assertForbidden();
    $this->delete(route('users.destroy', $target))->assertForbidden();
    $this->assertModelExists($target);
})->with(['auditor', 'eleitor']);

test('administrators change access without permitting arbitrary fields', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $target = User::factory()->create();
    $this->actingAs($admin)->patch(route('users.update', $target), ['role' => 'auditor', 'is_active' => false, 'email' => 'alterado@example.com'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'auditor', 'is_active' => false, 'email' => $target->email]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'ALTERACAO_ACESSO_USUARIO', 'target_id' => $target->id]);
    $this->actingAs($target->fresh())->get(route('audit.index'))->assertForbidden();
});

test('administrators can delete accounts without history but cannot delete themselves or historical accounts', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $target = User::factory()->create();
    $this->actingAs($admin)->delete(route('users.destroy', $target))->assertSessionHasNoErrors();
    $this->assertModelMissing($target);
    $this->delete(route('users.destroy', $admin))->assertSessionHasErrors('user');
    $historical = User::factory()->create();
    AuditLog::create(['user_id' => $historical->id, 'event' => 'VOTO_REJEITADO', 'target_type' => 'round', 'result' => 'rejeitado']);
    $this->delete(route('users.destroy', $historical))->assertSessionHasErrors('user');
    $this->assertModelExists($historical);
});

test('the last active administrator cannot lose administrative access', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->patch(route('users.update', $admin), ['role' => 'eleitor', 'is_active' => false])->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'administrador', 'is_active' => true]);
});

test('demo seeding is repeatable and configures the requested accounts and passwords', function () {
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseHas('users', ['email' => 'alex@teste.dev', 'role' => 'administrador', 'is_active' => true]);
    foreach (User::all() as $user) {
        expect(Hash::check('123456', $user->password))->toBeTrue();
    }
});

test('inactive sessions retain public access but cannot use account settings', function () {
    $inactive = User::factory()->create(['is_active' => false]);
    $this->actingAs($inactive)->get(route('home'))->assertOk();
    $this->get(route('profile.edit'))->assertForbidden();
    $this->patch(route('profile.update'), ['name' => 'Novo nome', 'email' => $inactive->email])->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $inactive->id, 'name' => $inactive->name]);
});

test('profile self deletion also protects voting history and the last administrator', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    $historical = User::factory()->create();
    AuditLog::create(['user_id' => $historical->id, 'event' => 'VOTO_REJEITADO', 'target_type' => 'round', 'result' => 'rejeitado']);
    $this->actingAs($historical)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    $this->assertModelExists($admin);
    $this->assertModelExists($historical);
});
