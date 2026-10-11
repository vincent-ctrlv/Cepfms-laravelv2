<?php

namespace Tests\Unit;

use App\Services\SpamService;
use Tests\TestCase;

class ServiceAreaTest extends TestCase
{
    public function test_barangay_names_are_matched_case_insensitively(): void
    {
        $this->assertSame('Kaypian', sjdm_barangay('  kaypian '));
        $this->assertNull(sjdm_barangay('Quezon City'));
    }

    public function test_city_boundary_check(): void
    {
        $this->assertTrue(sjdm_contains(14.8139, 121.0453));   // San Jose del Monte city proper
        $this->assertFalse(sjdm_contains(14.5995, 120.9842));  // Manila
    }

    public function test_keyboard_mashing_is_flagged_as_unreadable(): void
    {
        $this->assertNotNull(SpamService::unreadableReason('sdadadsadassss qwrtzxcv'));
        $this->assertNull(SpamService::unreadableReason('Walang tubig sa Purok 3'));
    }
}
