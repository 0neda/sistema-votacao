<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ([
            ['Alex', 'alex@teste.dev', 'administrador'],
            ['Layne', 'layne@teste.dev', 'auditor'],
            ['Guilherme', 'guilherme@teste.dev', 'eleitor'],
        ] as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => '123456',
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
