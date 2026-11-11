<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* Opens every main admin screen on an empty database: none may crash. */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_screen_opens(): void
    {
        $admin = User::create(['name' => 'Boss', 'username' => 'boss', 'email' => 'boss@example.com', 'password' => 'secret123', 'role' => 'admin', 'active' => true]);
        $urls = [
            '/dashboard', '/stock/inventory', '/stock/entries', '/stock/import',
            '/purchase', '/purchase/new', '/sale', '/sale/new', '/rental', '/rental/new',
            '/return', '/return/new', '/replacement', '/replacement/new',
            '/consumption', '/consumption/new', '/barcodes', '/payments', '/reports',
            '/logins', '/logins/create', '/company',
        ];
        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_pagination_size_is_accepted(): void
    {
        $admin = User::create(['name' => 'Boss', 'username' => 'boss', 'email' => 'boss@example.com', 'password' => 'secret123', 'role' => 'admin', 'active' => true]);
        foreach ([10, 20, 50, 100, 7] as $n) {
            $this->actingAs($admin)->get('/purchase?n=' . $n)->assertOk();
        }
    }

    public function test_customer_portal_opens(): void
    {
        $c = User::create(['name' => 'Cust', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret123', 'role' => 'customer', 'active' => true]);
        foreach (['/my/rentals', '/my/invoices', '/my/returns'] as $url) {
            $this->actingAs($c)->get($url)->assertOk();
        }
    }
}
