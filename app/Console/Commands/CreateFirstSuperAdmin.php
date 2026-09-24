<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('user:create-first-super-admin')]
#[Description('Interactively create the first Super Admin account')]
class CreateFirstSuperAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->where('is_super_admin', true)->exists()) {
            $this->error('A Super Admin already exists. This bootstrap command cannot create another one.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email address')));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The passwords do not match. No account was created.');

            return self::FAILURE;
        }

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12), 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['role' => 'admin', 'is_active' => true, 'is_super_admin' => true])->save();

            DB::table('administrative_audits')->insert([
                'user_id' => $user->id,
                'actor' => 'Local operator',
                'action' => 'security.super-admin.created',
                'type' => 'security',
                'record' => (string) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info('First Super Admin account created. Sign in through the staff login page.');

        return self::SUCCESS;
    }
}
