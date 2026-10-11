<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
| Staff account bootstrap commands (the original app's bin/create-admin.php
| and bin/make-super-admin.php). Super admins are the only role that can
| manage staff accounts, and they are only ever made from the command line.
*/

Artisan::command('cepfms:create-admin {email?} {password?} {name=Administrator}', function () {
    $email = (string) ($this->argument('email') ?: env('ADMIN_EMAIL', ''));
    $password = (string) ($this->argument('password') ?: env('ADMIN_PASSWORD', ''));
    $name = $this->argument('name');
    if ($name === 'Administrator' && env('ADMIN_NAME')) {
        $name = env('ADMIN_NAME');
    }

    if ($email === '' || $password === '') {
        $this->error('Usage: php artisan cepfms:create-admin <email> <password> [full name]');
        return 1;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Invalid email address.');
        return 1;
    }
    if (strlen($password) < 10) {
        $this->error('Password must be at least 10 characters.');
        return 1;
    }

    // Safe to re-run: an existing account is left untouched.
    if (DB::table('users')->where('email', $email)->exists()) {
        $this->info("Staff account already exists for {$email} — nothing to do.");
        return 0;
    }

    DB::table('users')->insert([
        'full_name'            => $name,
        'email'                => $email,
        'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
        'role'                 => 'super_admin',
        'is_active'            => 1,
        'must_change_password' => 1,
    ]);
    $this->info("Created super admin {$email} (must change password on first login).");
    return 0;
})->purpose('Create the first super admin account');

Artisan::command('cepfms:make-super-admin {email}', function () {
    $email = mb_strtolower(trim($this->argument('email')));
    $user = DB::table('users')->select('id', 'role')->where('email', $email)->first();
    if (!$user) {
        $this->error("No staff account found for {$email}.");
        return 1;
    }
    if ($user->role === 'super_admin') {
        $this->info("{$email} is already a super admin — nothing to do.");
        return 0;
    }
    DB::table('users')->where('id', $user->id)->update(['role' => 'super_admin']);
    $this->info("Promoted {$email} from {$user->role} to super admin.");
    return 0;
})->purpose('Promote an existing staff account to super admin');
