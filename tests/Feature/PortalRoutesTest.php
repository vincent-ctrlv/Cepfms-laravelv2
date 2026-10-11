<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Route-level checks that need no database. */
class PortalRoutesTest extends TestCase
{
    public function test_root_redirects_to_the_citizen_portal(): void
    {
        $this->get('/')->assertRedirect('/citizen');
    }

    public function test_health_check(): void
    {
        $this->get('/healthz')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_old_php_urls_redirect(): void
    {
        $this->get('/citizen/track.php?ref=CM-1234')->assertRedirect('/citizen/track?ref=CM-1234');
    }

    public function test_admin_pages_require_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/auth/login');
    }

    public function test_api_requires_login_with_json_401(): void
    {
        $this->getJson('/api/dashboard/kpis')
            ->assertStatus(401)
            ->assertHeader('X-Session-Expired', '1')
            ->assertJson(['success' => false]);
    }
}
