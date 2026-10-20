<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat chat kini milik akun (user_id), bukan lagi milik browser (visitor_id).
 * Tamu tidak menyimpan apa pun ke database.
 *
 * - user_id nullable supaya kode python_app/db.py (INSERT ... DEFAULT VALUES) tetap jalan;
 *   sesi tanpa user_id tidak tampil di web.
 * - visitor_id dibiarkan ada (tidak dipakai lagi oleh web) agar tidak mengubah skema tim AI.
 * - Akun dihapus -> seluruh sesinya ikut terhapus (cascade ke pesan & sumber).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->index(['user_id', 'updated_at'], 'chat_sessions_user_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('chat_sessions_user_updated_idx');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
