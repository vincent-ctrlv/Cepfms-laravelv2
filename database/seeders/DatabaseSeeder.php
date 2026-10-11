<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Production defaults only (no demo staff, citizens or tickets). Create the
     * first super admin afterwards with:
     *   php artisan cepfms:create-admin you@lgu.gov.ph 'A-strong-password' "Full Name"
     *
     * For local testing, `php artisan db:seed --class=DemoDataSeeder` adds
     * sample accounts and submissions.
     */
    public function run(): void
    {
        foreach ([
            'ai_classification_enabled' => '1',
            'site_name'                 => 'CEPFMS - SJDM Legislative Services',
            // Only honoured when APP_ENV=local and no SMTP is configured
            // (see EmailService::isDevMode), so it is safe as a default.
            'otp_dev_mode'              => '1',
        ] as $key => $value) {
            DB::table('settings')->insertOrIgnore(['setting_key' => $key, 'setting_value' => $value]);
        }
    }
}
