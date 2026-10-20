<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logout JWT memakai nomor versi per user, bukan daftar token yang dicabut.
 *
 * Setiap JWT membawa "ver" = users.token_version saat token dibuat.
 * Logout menaikkan token_version, jadi semua token lama user itu langsung ditolak.
 *
 * Tabel revoked_tokens (cara sebelumnya) tidak dipakai lagi dan ikut dihapus
 * bila sempat dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('token_version')->default(0)->after('role');
        });

        Schema::dropIfExists('revoked_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('token_version');
        });
    }
};
