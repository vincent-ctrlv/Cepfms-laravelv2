<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Checks that run against the MySQL test database (cepfms_test), migrated fresh. */
class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_command_makes_a_super_admin(): void
    {
        $this->artisan('cepfms:create-admin', ['email' => 'head@example.test', 'password' => 'A-strong-password', 'name' => 'Office Head'])
            ->assertSuccessful();

        $user = DB::table('users')->where('email', 'head@example.test')->first();
        $this->assertSame('super_admin', $user->role);
        $this->assertSame(1, (int) $user->must_change_password);
    }

    public function test_new_accounts_default_to_admin(): void
    {
        DB::table('users')->insert(['full_name' => 'New Person', 'email' => 'new@example.test', 'password_hash' => 'x']);

        $this->assertSame('admin', DB::table('users')->where('email', 'new@example.test')->value('role'));
    }

    public function test_track_page_finds_a_submission_by_reference(): void
    {
        $citizenId = DB::table('citizens')->insertGetId(['full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.test']);
        DB::table('submissions')->insert([
            'reference_number' => 'CM-1234', 'citizen_id' => $citizenId, 'submission_type' => 'complaint',
            'subject' => 'Broken streetlight on Main St', 'description' => 'The streetlight has been out for a week.',
        ]);

        $this->get('/citizen/track?ref=cm-1234')
            ->assertOk()
            ->assertSee('Broken streetlight on Main St');
    }
}
