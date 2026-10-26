<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_list_renders_as_table_with_row_actions(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(['name' => 'Admin Tabel', 'email' => 'table@example.com']);

        $this->actingAs($admin, 'web')
            ->get('/admin/accounts')
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('table@example.com')
            ->assertSee('Simpan perubahan')
            ->assertSee('Hapus akun');
    }

    public function test_admin_navigation_has_admin_account_management_link(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee(route('admin.accounts.index'), false);
    }

    public function test_admin_can_create_admin_account_with_hashed_password(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->post('/admin/accounts', [
                'name' => 'Admin Baru',
                'email' => 'baru@example.com',
                'password' => 'PasswordKuat123!',
                'password_confirmation' => 'PasswordKuat123!',
            ])
            ->assertRedirect('/admin/accounts');

        $admin = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNotSame('PasswordKuat123!', $admin->password);
        $this->assertTrue(password_verify('PasswordKuat123!', $admin->password));
    }

    public function test_admin_can_update_admin_details_and_optional_password(): void
    {
        $target = User::factory()->admin()->create(['email' => 'old@example.com']);
        $oldHash = $target->password;

        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->put('/admin/accounts/' . $target->id, [
                'name' => 'Nama Diperbarui',
                'email' => 'new@example.com',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect('/admin/accounts');

        $target->refresh();
        $this->assertSame('Nama Diperbarui', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertSame($oldHash, $target->password);
    }

    public function test_admin_cannot_delete_own_or_last_admin_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'web')
            ->delete('/admin/accounts/' . $admin->id)
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_admin_when_multiple_admins_exist(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($actor, 'web')
            ->delete('/admin/accounts/' . $target->id)
            ->assertRedirect('/admin/accounts');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_guests_and_regular_users_cannot_manage_admin_accounts(): void
    {
        $this->get('/admin/accounts')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(), 'web')
            ->get('/admin/accounts')
            ->assertForbidden();
    }
}
