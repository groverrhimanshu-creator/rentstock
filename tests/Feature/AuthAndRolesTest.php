<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $name): User
    {
        return User::create(['name' => ucfirst($name), 'username' => $name, 'email' => $name . '@example.com', 'password' => 'secret123', 'role' => $role, 'active' => true]);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_has_no_subadmin_while_switched_off(): void
    {
        config(['rentstock.subadmin_enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('SubAdmin')->assertSee('Admin');
    }

    public function test_login_page_shows_subadmin_when_switched_on(): void
    {
        config(['rentstock.subadmin_enabled' => true]);
        $this->get('/login')->assertOk()->assertSee('SubAdmin');
    }

    public function test_admin_can_log_in(): void
    {
        $this->user('admin', 'boss');
        $this->post('/login', ['role' => 'admin', 'username' => 'boss', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_refused(): void
    {
        $this->user('admin', 'boss');
        $this->post('/login', ['role' => 'admin', 'username' => 'boss', 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_wrong_role_is_refused(): void
    {
        $this->user('customer', 'cust');
        $this->post('/login', ['role' => 'admin', 'username' => 'cust', 'password' => 'secret123'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_subadmin_cannot_log_in_while_switched_off(): void
    {
        config(['rentstock.subadmin_enabled' => false]);
        $this->user('subadmin', 'sub');
        $this->post('/login', ['role' => 'subadmin', 'username' => 'sub', 'password' => 'secret123'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_subadmin_can_log_in_when_switched_on(): void
    {
        config(['rentstock.subadmin_enabled' => true]);
        $this->user('subadmin', 'sub');
        $this->post('/login', ['role' => 'subadmin', 'username' => 'sub', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_already_logged_in_subadmin_is_thrown_out_when_switched_off(): void
    {
        config(['rentstock.subadmin_enabled' => false]);
        $this->actingAs($this->user('subadmin', 'sub'))->get('/dashboard')->assertRedirect('/login');
    }

    public function test_customer_cannot_open_admin_pages(): void
    {
        $c = $this->user('customer', 'cust');
        foreach (['/dashboard', '/purchase', '/sale', '/rental', '/stock/inventory', '/reports', '/logins', '/company'] as $url) {
            $this->actingAs($c)->get($url)->assertStatus(403);
        }
    }

    public function test_five_wrong_tries_lock_the_login(): void
    {
        $this->user('admin', 'boss');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['role' => 'admin', 'username' => 'boss', 'password' => 'bad']);
        }
        $this->post('/login', ['role' => 'admin', 'username' => 'boss', 'password' => 'secret123'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
