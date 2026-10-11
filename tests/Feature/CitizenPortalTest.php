<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The signed-in citizen portal: dashboard, own submissions, replies, profile. */
class CitizenPortalTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(string $email = 'juan@gmail.com', bool $verified = true): int
    {
        $id = (int) DB::table('citizens')->insertGetId(['full_name' => 'Juan Dela Cruz', 'email' => $email, 'google_sub' => 'g-' . $email]);
        if ($verified) {
            DB::table('citizen_verifications')->insert([
                'citizen_id' => $id, 'full_name' => 'Juan Dela Cruz', 'id_type' => 'Postal ID', 'id_number_hash' => hash('sha256', $email),
                'id_number_last4' => '1234', 'barangay' => SJDM_BARANGAYS[0], 'id_photo' => 'x', 'selfie_photo' => 'y', 'status' => 'Approved',
            ]);
        }
        return $id;
    }

    private function submission(int $citizenId, string $ref, string $subject, string $moderation = 'Approved', string $status = 'Received'): int
    {
        return (int) DB::table('submissions')->insertGetId([
            'reference_number' => $ref, 'citizen_id' => $citizenId, 'submission_type' => 'complaint',
            'subject' => $subject, 'description' => 'Details of the problem.', 'status' => $status, 'moderation_status' => $moderation,
        ]);
    }

    public function test_portal_pages_need_a_signed_in_citizen(): void
    {
        foreach (['/citizen/dashboard', '/citizen/my-submissions', '/citizen/profile', '/citizen/submissions/CM-1234'] as $url) {
            $this->get($url)->assertRedirect('/citizen/login');
        }
    }

    public function test_dashboard_counts_only_the_citizens_own_submissions(): void
    {
        $me = $this->citizen();
        $other = $this->citizen('other@gmail.com');
        $this->submission($me, 'CM-1001', 'Pothole on Main St', 'Pending', 'Pending');
        $this->submission($me, 'CM-1002', 'Flooded drainage', 'Approved', 'In Review');
        $this->submission($me, 'CM-1003', 'Broken streetlight', 'Approved', 'Resolved');
        $this->submission($other, 'CM-2001', 'Someone else entirely');

        $this->withSession(['citizen_id' => $me])->get('/citizen/dashboard')
            ->assertOk()
            ->assertSee('Pothole on Main St')
            ->assertSee('Broken streetlight')
            ->assertDontSee('Someone else entirely')
            ->assertSeeInOrder(['3', 'Total sent', '1', 'Waiting for review', '1', 'In progress', '1', 'Resolved']);
    }

    public function test_unverified_citizens_see_the_verify_prompt_on_their_dashboard(): void
    {
        $id = $this->citizen('new@gmail.com', false);

        $this->withSession(['citizen_id' => $id])->get('/citizen/dashboard')
            ->assertOk()->assertSee('Verify your ID to start submitting')->assertDontSee('New submission');
    }

    public function test_a_citizen_cannot_open_someone_elses_submission(): void
    {
        $me = $this->citizen();
        $other = $this->citizen('other@gmail.com');
        $this->submission($other, 'CM-2001', 'Someone else entirely');

        $this->withSession(['citizen_id' => $me])->get('/citizen/submissions/CM-2001')->assertNotFound();
    }

    public function test_submission_page_shows_only_sent_replies_without_internal_notes_or_staff_names(): void
    {
        $me = $this->citizen();
        $subId = $this->submission($me, 'CM-1002', 'Flooded drainage', 'Approved', 'Assigned');
        $staffId = (int) DB::table('users')->insertGetId(['full_name' => 'Pedro Staffmember', 'email' => 'p@lgu.test', 'password_hash' => 'x', 'role' => 'admin']);
        DB::table('responses')->insert([
            ['submission_id' => $subId, 'staff_id' => $staffId, 'subject' => 'Crew scheduled', 'body' => 'A crew will clear it on Monday.',
             'internal_notes' => 'SECRET internal note', 'status' => 'Sent', 'sent_at' => now()],
            ['submission_id' => $subId, 'staff_id' => $staffId, 'subject' => 'Unsent draft reply', 'body' => 'Draft text',
             'internal_notes' => null, 'status' => 'Draft', 'sent_at' => null],
        ]);

        $this->withSession(['citizen_id' => $me])->get('/citizen/submissions/cm-1002')
            ->assertOk()
            ->assertSee('Flooded drainage')
            ->assertSee('A crew will clear it on Monday.')
            ->assertDontSee('Unsent draft reply')
            ->assertDontSee('SECRET internal note')
            ->assertDontSee('Pedro Staffmember');
    }

    public function test_my_submissions_filters(): void
    {
        $me = $this->citizen();
        $this->submission($me, 'CM-1001', 'Waiting one', 'Pending', 'Pending');
        $this->submission($me, 'CM-1003', 'Finished one', 'Approved', 'Resolved');

        $this->withSession(['citizen_id' => $me])->get('/citizen/my-submissions?filter=resolved')
            ->assertOk()->assertSee('Finished one')->assertDontSee('Waiting one');
    }

    public function test_citizens_can_update_their_mobile_number(): void
    {
        $me = $this->citizen();

        $this->withSession(['citizen_id' => $me])->postJson('/api/citizen/profile', ['phone' => '0917 123 4567'])->assertOk();
        $this->assertSame('09171234567', DB::table('citizens')->where('id', $me)->value('phone'));

        $this->withSession(['citizen_id' => $me])->postJson('/api/citizen/profile', ['phone' => '12345'])->assertStatus(422);
    }

    public function test_public_track_page_still_explains_rejected_submissions(): void
    {
        $me = $this->citizen();
        $subId = $this->submission($me, 'CM-1009', 'Duplicate report', 'Rejected', 'Pending');
        DB::table('moderation_queue')->insert([
            'content_type' => 'Complaint', 'submission_id' => $subId, 'citizen_id' => $me, 'content_snapshot' => 'x',
            'status' => 'Rejected', 'reject_reason' => 'Duplicate', 'reviewed_at' => now(),
        ]);

        $this->get('/citizen/track?ref=CM-1009')->assertOk()->assertSee("This submission wasn't accepted", false)->assertSee('Duplicate');
    }
}
