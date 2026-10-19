<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_and_updates_the_configured_admin_idempotently(): void
    {
        config([
            'admin.name' => 'Admin Uji',
            'admin.email' => 'admin-test@example.test',
            'admin.password' => 'TestPassword-OnlyForTest!',
        ]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::where('email', 'admin-test@example.test')->firstOrFail();

        $this->assertSame('Admin Uji', $admin->name);
        $this->assertTrue(Hash::check('TestPassword-OnlyForTest!', $admin->password));

        config(['admin.name' => 'Admin Diperbarui']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin-test@example.test')->count());
        $this->assertDatabaseHas('users', ['email' => 'admin-test@example.test', 'name' => 'Admin Diperbarui']);
    }
}
