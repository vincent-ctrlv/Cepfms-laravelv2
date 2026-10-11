<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/** Citizen accounts: Google sign-in, ID verification, and who may submit. */
class CitizenAccountTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $sub, string $email, string $name = 'Juan Dela Cruz', bool $verified = true): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $sub, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $sub, 'email' => $email, 'name' => $name]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function citizen(string $verification = 'none'): int
    {
        $id = (int) DB::table('citizens')->insertGetId([
            'full_name' => 'Juan Dela Cruz', 'email' => 'juan@gmail.com', 'google_sub' => 'g-1',
        ]);
        if ($verification !== 'none') {
            DB::table('citizen_verifications')->insert([
                'citizen_id' => $id, 'full_name' => 'Juan P. Dela Cruz', 'id_type' => 'Postal ID',
                'id_number_hash' => str_repeat('a', 64), 'id_number_last4' => '1234', 'barangay' => SJDM_BARANGAYS[0],
                'id_photo' => 'verifications/x/id.jpg', 'selfie_photo' => 'verifications/x/selfie.jpg', 'status' => $verification,
            ]);
        }
        return $id;
    }

    private function admin(): int
    {
        return (int) DB::table('users')->insertGetId([
            'full_name' => 'Ana Admin', 'email' => 'ana@lgu.test', 'password_hash' => 'x', 'role' => 'admin',
        ]);
    }

    public function test_guests_are_sent_to_sign_in_before_submitting(): void
    {
        $this->get('/citizen/submit')->assertRedirect('/citizen/login');
        $this->postJson('/api/submissions/create')->assertStatus(401)->assertHeader('X-Citizen-Login', '1');
    }

    public function test_google_sign_in_creates_an_account_and_asks_for_an_id(): void
    {
        $this->fakeGoogle('g-new', 'Maria@Gmail.com', 'Maria Santos');

        $this->get('/citizen/auth/google/callback')->assertRedirect('/citizen/verify');

        $citizen = DB::table('citizens')->where('google_sub', 'g-new')->first();
        $this->assertSame('maria@gmail.com', $citizen->email);
        $this->assertSame('Maria Santos', $citizen->full_name);
        $this->assertSame($citizen->id, session('citizen_id'));
    }

    public function test_google_sign_in_links_an_existing_citizen_by_email(): void
    {
        $existing = (int) DB::table('citizens')->insertGetId(['full_name' => 'Old Record', 'email' => 'pedro@gmail.com']);
        $this->fakeGoogle('g-pedro', 'pedro@gmail.com');

        $this->get('/citizen/auth/google/callback');

        $this->assertSame('g-pedro', DB::table('citizens')->where('id', $existing)->value('google_sub'));
        $this->assertSame(1, DB::table('citizens')->where('email', 'pedro@gmail.com')->count());
    }

    public function test_google_accounts_without_a_verified_email_are_refused(): void
    {
        $this->fakeGoogle('g-x', 'x@gmail.com', 'X', false);

        $this->get('/citizen/auth/google/callback')->assertRedirect('/citizen/login');
        $this->assertFalse(DB::table('citizens')->where('google_sub', 'g-x')->exists());
    }

    public function test_unverified_and_pending_citizens_cannot_submit(): void
    {
        $id = $this->citizen('Pending');

        $this->withSession(['citizen_id' => $id])->get('/citizen/submit')->assertRedirect('/citizen/verify');
        $this->withSession(['citizen_id' => $id])->postJson('/api/submissions/create')->assertStatus(403);
    }

    public function test_verification_photos_are_stored_privately_and_await_review(): void
    {
        Storage::fake('local');
        $id = $this->citizen();

        $this->withSession(['citizen_id' => $id])->post('/api/citizen/verification', [
            'full_name' => 'Juan P. Dela Cruz', 'id_type' => 'Postal ID', 'id_number' => 'PID-0000-5678',
            'barangay' => SJDM_BARANGAYS[0], 'consent' => '1',
            'id_photo' => UploadedFile::fake()->image('id.jpg', 900, 560),
            'selfie_photo' => UploadedFile::fake()->image('selfie.jpg', 720, 960),
        ])->assertOk()->assertJson(['success' => true]);

        $row = DB::table('citizen_verifications')->where('citizen_id', $id)->first();
        $this->assertSame('Pending', $row->status);
        $this->assertSame('5678', $row->id_number_last4);
        $this->assertStringNotContainsString('0000', $row->id_number_hash);
        Storage::disk('local')->assertExists([$row->id_photo, $row->selfie_photo]);
        $this->assertStringStartsWith("verifications/{$id}/", $row->id_photo);

        // Only one at a time.
        $this->withSession(['citizen_id' => $id])->postJson('/api/citizen/verification', [])->assertStatus(409);
    }

    public function test_tiny_or_non_image_files_are_rejected(): void
    {
        Storage::fake('local');
        $id = $this->citizen();

        $this->withSession(['citizen_id' => $id])->postJson('/api/citizen/verification', [
            'full_name' => 'Juan', 'id_type' => 'Postal ID', 'id_number' => '12345678',
            'barangay' => SJDM_BARANGAYS[0], 'consent' => '1',
            'id_photo' => UploadedFile::fake()->image('id.jpg', 100, 60),
            'selfie_photo' => UploadedFile::fake()->create('selfie.pdf', 50, 'application/pdf'),
        ])->assertStatus(422);

        $this->assertFalse(DB::table('citizen_verifications')->exists());
    }

    public function test_admin_approval_lets_the_citizen_submit(): void
    {
        $citizenId = $this->citizen('Pending');
        $verificationId = (int) DB::table('citizen_verifications')->value('id');

        $this->withSession(['staff_id' => $this->admin(), '_last_activity' => time()])
            ->postJson('/api/verifications/action', ['id' => $verificationId, 'action' => 'approve'])
            ->assertOk();

        $this->assertSame('Approved', DB::table('citizen_verifications')->where('id', $verificationId)->value('status'));
        $this->assertSame('Juan P. Dela Cruz', DB::table('citizens')->where('id', $citizenId)->value('full_name'));
        $this->withSession(['citizen_id' => $citizenId])->get('/citizen/submit')->assertOk()->assertSee('Juan P. Dela Cruz');
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $this->citizen('Pending');
        $verificationId = (int) DB::table('citizen_verifications')->value('id');
        $session = ['staff_id' => $this->admin(), '_last_activity' => time()];

        $this->withSession($session)->postJson('/api/verifications/action', ['id' => $verificationId, 'action' => 'reject'])->assertStatus(422);
        $this->withSession($session)->postJson('/api/verifications/action', [
            'id' => $verificationId, 'action' => 'reject', 'reason' => 'ID not readable', 'note' => 'Too blurry',
        ])->assertOk();

        $this->assertSame('ID not readable — Too blurry', DB::table('citizen_verifications')->where('id', $verificationId)->value('reject_reason'));
    }

    public function test_id_photos_are_only_shown_to_signed_in_admins(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verifications/x/id.jpg', UploadedFile::fake()->image('id.jpg', 400, 300)->get());
        $citizenId = $this->citizen('Pending');
        $verificationId = (int) DB::table('citizen_verifications')->value('id');

        $this->get("/admin/verifications/{$verificationId}/id")->assertRedirect('/auth/login');
        $this->withSession(['citizen_id' => $citizenId])->get("/admin/verifications/{$verificationId}/id")->assertRedirect('/auth/login');
        $this->withSession(['staff_id' => $this->admin(), '_last_activity' => time()])
            ->get("/admin/verifications/{$verificationId}/id")
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_verified_citizens_can_submit_under_their_verified_name(): void
    {
        // Evidence photos go under public/; keep them out of the real one.
        $publicDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cepfms-test-public-' . bin2hex(random_bytes(4));
        $this->app->usePublicPath($publicDir);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($publicDir));

        $citizenId = $this->citizen('Approved');
        DB::table('citizens')->where('id', $citizenId)->update(['full_name' => 'Juan P. Dela Cruz']);

        $response = $this->withSession(['citizen_id' => $citizenId])->post('/api/submissions/create', [
            'subject' => 'Broken streetlight near the barangay hall',
            'description' => 'The streetlight in front of the barangay hall has been off for a week and the road is very dark at night.',
            'phone' => '09171234567', 'barangay' => SJDM_BARANGAYS[0], 'consent' => '1',
            'form_loaded_at' => time() - 120,
            'photos' => [UploadedFile::fake()->image('light.jpg', 800, 600)],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $submissionId = $response->json('data.submission_id');
        $this->assertDirectoryExists($publicDir . DIRECTORY_SEPARATOR . 'uploads');

        $this->assertSame($citizenId, (int) DB::table('submissions')->where('id', $submissionId)->value('citizen_id'));
        $this->assertSame('09171234567', DB::table('citizens')->where('id', $citizenId)->value('phone'));
    }
}
