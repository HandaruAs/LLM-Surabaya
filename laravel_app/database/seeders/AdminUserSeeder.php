<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('admin.name'));
        $email = strtolower(trim((string) config('admin.email')));
        $password = (string) config('admin.password');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new RuntimeException(
                'Set ADMIN_NAME, a valid ADMIN_EMAIL, and ADMIN_PASSWORD (minimum 12 characters) in laravel_app/.env before seeding.'
            );
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = $name;
        $admin->password = Hash::make($password);
        $admin->role = User::ROLE_ADMIN; // role tidak fillable, jadi diisi langsung
        $admin->save();
    }
}
