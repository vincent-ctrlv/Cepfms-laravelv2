<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for local testing only — never run this in production.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Adds three staff accounts (password for all: "Demo-Password-123"):
 *   superadmin@cepfms.test (super admin), admin@cepfms.test (admin),
 *   staff@cepfms.test (staff)
 * plus citizens and submissions spread over the last few weeks in every
 * workflow state (awaiting moderation, approved, rejected, resolved).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $password = password_hash('Demo-Password-123', PASSWORD_DEFAULT);
        $staffIds = [];
        foreach ([
            ['Maria Santos', 'superadmin@cepfms.test', 'super_admin', 'Legislative Services Head'],
            ['Jose Reyes', 'admin@cepfms.test', 'admin', 'Records Officer'],
            ['Ana Cruz', 'staff@cepfms.test', 'staff', 'Administrative Aide'],
        ] as [$name, $email, $role, $title]) {
            $staffIds[$role] = DB::table('users')->where('email', $email)->value('id') ?? DB::table('users')->insertGetId([
                'full_name' => $name, 'email' => $email, 'password_hash' => $password,
                'role' => $role, 'title' => $title, 'is_active' => 1, 'must_change_password' => 0,
            ]);
        }

        if (DB::table('submissions')->exists()) {
            return; // already seeded
        }

        $samples = [
            ['complaint', 'Malaking butas sa kalsada sa Tungkong Mangga', 'May malaking butas sa kalsada malapit sa barangay hall, delikado sa mga motor lalo na pag gabi.', 'Infrastructure & Roads', 'Critical', 'Negative', 'Tungkong Mangga'],
            ['complaint', 'Streetlight not working for weeks', 'Ilang linggo na patay yung streetlight sa may kanto. Medyo madilim sa gabi.', 'Infrastructure & Roads', 'Medium', 'Neutral', 'Muzon East'],
            ['complaint', 'Basura hindi nakokolekta', 'Tatlong araw na hindi dumadaan ang garbage truck sa aming lugar, umaapaw na ang basura.', 'Sanitation & Waste', 'High', 'Negative', 'Graceville'],
            ['complaint', 'Maingay na videoke tuwing gabi', 'Gabi-gabi may videoke hanggang alas-dos ng madaling araw, hindi makatulog ang mga bata.', 'Noise & Disturbance', 'Medium', 'Negative', 'Kaypian'],
            ['complaint', 'Baha sa kalye tuwing umuulan', 'Barado ang imburnal kaya binabaha ang kalye tuwing malakas ang ulan.', 'Infrastructure & Roads', 'High', 'Negative', 'Fatima II'],
            ['feedback', 'Salamat sa mabilis na pag-ayos ng kalsada', 'Grabe ang bilis ng team, isang linggo lang tapos na. Sana ganito palagi.', 'Infrastructure & Roads', 'Low', 'Positive', 'San Rafael I'],
            ['feedback', 'Maayos ang serbisyo sa health center', 'Mabilis at magalang ang mga staff sa health center namin. Salamat po!', 'Healthcare Services', 'Low', 'Positive', 'Poblacion'],
            ['feedback', 'Mahaba ang pila sa permit office', 'Medyo matagal ang pila sa business permit renewal pero naayos din naman.', 'Licensing & Permits', 'Low', 'Neutral', 'Gaya-Gaya'],
            ['feedback', 'Malinis na ang plaza', 'Napansin ko na malinis na ang plaza at may bagong mga ilaw. Maganda ang ginawa.', 'Parks & Recreation', 'Low', 'Positive', 'Citrus'],
            ['proposal', 'Covered walkway from terminal to market', 'Sana magkaroon ng covered walkway galing sa jeepney terminal papunta sa palengke para di mabasa pag umuulan.', 'Infrastructure & Roads', 'Medium', 'Positive', 'Sapang Palay Proper'],
            ['proposal', 'Bike lane along the main road', 'I suggest adding a protected bike lane along the main road for students and workers who cycle.', 'Transport & Transit', 'Medium', 'Positive', 'Santo Cristo'],
            ['proposal', 'Mungkahi: libreng wifi sa plaza', 'Mungkahi ko po na magkaroon ng libreng wifi sa plaza para sa mga estudyante.', 'Education', 'Low', 'Positive', 'Minuyan Proper'],
            ['complaint', 'Sirang traffic light sa intersection', 'Sira ang traffic light sa intersection malapit sa school, delikado sa mga tumatawid na bata.', 'Public Safety', 'Critical', 'Negative', 'Francisco Homes-Guijo'],
            ['feedback', 'Good job sa vaccination drive', 'Organized at mabilis ang bakuna sa barangay namin. Well done!', 'Healthcare Services', 'Low', 'Positive', 'San Martin I'],
        ];

        // [moderation_status, status] per sample, in order.
        $states = [
            ['Approved', 'In Review'], ['Approved', 'Received'], ['Approved', 'Assigned'], ['Pending', 'Pending'],
            ['Approved', 'Resolved'], ['Approved', 'Resolved'], ['Approved', 'Received'], ['Rejected', 'Pending'],
            ['Approved', 'Resolved'], ['Approved', 'In Review'], ['Pending', 'Pending'], ['Approved', 'Received'],
            ['Pending', 'Pending'], ['Approved', 'Resolved'],
        ];
        $prefix = ['feedback' => 'FB', 'complaint' => 'CM', 'proposal' => 'PS'];
        $names = ['Juan Dela Cruz', 'Rosa Mendoza', 'Pedro Garcia', 'Liza Ramos', 'Carlo Bautista', 'Grace Villanueva', 'Mark Aquino'];

        foreach ($samples as $i => [$type, $subject, $description, $category, $priority, $sentiment, $barangay]) {
            [$modStatus, $status] = $states[$i];
            $createdAt = now()->subDays(20 - $i)->subHours($i * 3);
            $name = $names[$i % count($names)];

            $citizenId = DB::table('citizens')->insertGetId([
                'full_name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . $i . '@example.com',
                'phone' => '0917' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'created_at' => $createdAt,
            ]);

            $submissionId = DB::table('submissions')->insertGetId([
                'reference_number' => $prefix[$type] . '-' . (1000 + $i * 37),
                'citizen_id' => $citizenId,
                'submission_type' => $type,
                'subject' => $subject,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'summary' => $description,
                'ai_confidence' => 0.6,
                'ai_reasoning' => 'Demo data.',
                'sentiment' => $sentiment,
                'sentiment_score' => ['Positive' => 0.6, 'Neutral' => 0, 'Negative' => -0.6][$sentiment],
                'city' => 'San Jose del Monte',
                'barangay' => $barangay,
                'street' => null,
                'status' => $status,
                'moderation_status' => $modStatus,
                'created_at' => $createdAt,
                'updated_at' => $status === 'Resolved' ? $createdAt->copy()->addDays(3) : $createdAt,
            ]);

            if ($type === 'complaint') {
                DB::table('complaints')->insert([
                    'submission_id' => $submissionId,
                    'sla_status' => $status === 'Resolved' ? 'Met' : ($priority === 'Critical' ? 'At Risk' : 'On Track'),
                    'closed_at' => $status === 'Resolved' ? $createdAt->copy()->addDays(3) : null,
                ]);
            } elseif ($type === 'proposal') {
                DB::table('proposals')->insert(['submission_id' => $submissionId, 'proposal_status' => 'Under Review']);
            }

            $moderationId = DB::table('moderation_queue')->insertGetId([
                'content_type' => ucfirst($type),
                'submission_id' => $submissionId,
                'citizen_id' => $citizenId,
                'content_snapshot' => $description,
                'status' => $modStatus,
                'reject_reason' => $modStatus === 'Rejected' ? 'Incomplete' : null,
                'reviewed_by' => $modStatus === 'Pending' ? null : $staffIds['admin'],
                'reviewed_at' => $modStatus === 'Pending' ? null : $createdAt->copy()->addHours(5),
                'created_at' => $createdAt,
            ]);

            DB::table('activity_log')->insert([
                ['staff_id' => null, 'action' => 'Submission created', 'entity' => 'submission', 'entity_id' => $submissionId,
                 'metadata' => json_encode(['reference' => $prefix[$type] . '-' . (1000 + $i * 37), 'type' => $type]), 'created_at' => $createdAt],
            ]);
            if ($modStatus !== 'Pending') {
                DB::table('activity_log')->insert([
                    'staff_id' => $staffIds['admin'], 'action' => 'Moderation ' . ($modStatus === 'Approved' ? 'approve' : 'reject'),
                    'entity' => 'moderation_queue', 'entity_id' => $moderationId,
                    'metadata' => json_encode(['content_type' => ucfirst($type)]), 'created_at' => $createdAt->copy()->addHours(5),
                ]);
            }

            if ($status === 'Resolved') {
                DB::table('responses')->insert([
                    'submission_id' => $submissionId,
                    'staff_id' => $staffIds['staff'],
                    'subject' => 'Re: ' . $subject,
                    'body' => "Thank you for reaching out. Your concern has been addressed.\n\nSincerely,\nCEPFMS Team",
                    'status' => 'Sent',
                    'sent_at' => $createdAt->copy()->addDays(2),
                    'created_at' => $createdAt->copy()->addDays(1),
                ]);
            }
        }
    }
}
