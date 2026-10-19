<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan yang dibutuhkan API web (PRD bagian 7.3 dan 9) di atas
 * skema dari tim AI. Semua kolom nullable / punya default, supaya kode
 * python_app/db.py (mis. INSERT INTO chat_sessions DEFAULT VALUES) tetap jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_sessions', 'visitor_id')) {
                // Sesi tanpa visitor_id (dibuat dari CLI/db.py) tidak tampil di web.
                $table->string('visitor_id', 36)->nullable();
            }
            if (! Schema::hasColumn('chat_sessions', 'title')) {
                $table->string('title', 100)->nullable();
            }
            if (! Schema::hasColumn('chat_sessions', 'updated_at')) {
                $table->timestampTz('updated_at')->useCurrent();
            }
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            // Daftar sesi per pengunjung, terbaru dulu (FR-12).
            $table->index(['visitor_id', 'updated_at'], 'chat_sessions_visitor_updated_idx');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'sources')) {
                // Daftar nama file sumber untuk ditampilkan cepat di UI.
                $table->jsonb('sources')->nullable();
            }
            if (! Schema::hasColumn('chat_messages', 'response_ms')) {
                $table->integer('response_ms')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['sources', 'response_ms']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('chat_sessions_visitor_updated_idx');
            $table->dropColumn(['visitor_id', 'title', 'updated_at']);
        });
    }
};
