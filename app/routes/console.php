<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('portal:create-super-admin', function (): int {
    if (User::whereHas('roles', fn ($query) => $query->where('key', 'super_admin'))->exists()) {
        $this->components->error('A Super Admin account already exists.');

        return Command::FAILURE;
    }

    $name = trim((string) $this->ask('Super Admin name'));
    $email = Str::lower(trim((string) $this->ask('Super Admin email')));
    $password = (string) $this->secret('Password (at least 12 characters)');
    $confirmation = (string) $this->secret('Confirm password');

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password],
        ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:12']],
    );

    if ($validator->fails() || ! hash_equals($password, $confirmation)) {
        $this->components->error($validator->errors()->first() ?: 'The passwords did not match.');

        return Command::FAILURE;
    }

    $role = Role::where('key', 'super_admin')->firstOrFail();

    DB::transaction(function () use ($name, $email, $password, $role): void {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'account_status' => 'active',
        ]);

        $user->roles()->attach($role->id, ['granted_at' => now()]);
    });

    $this->components->info('Super Admin account created. Keep its credentials private.');

    return Command::SUCCESS;
})->purpose('Create the first Super Admin without storing a default password');
