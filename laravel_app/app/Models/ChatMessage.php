<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    /** Tabel hanya punya created_at. */
    public const UPDATED_AT = null;

    /** Pesan baru menyentuh updated_at sesi, supaya urutan riwayat benar. */
    protected $touches = ['session'];

    /** Kolom waktu bertipe timestamptz: tulis beserta offset agar tidak bergeser zona. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = ['role', 'content', 'sources', 'response_ms'];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'response_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function sourceChunks(): HasMany
    {
        return $this->hasMany(MessageSource::class, 'message_id');
    }

    /** Bentuk pesan sesuai spesifikasi API (PRD 8.1). */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'sources' => $this->sources ?? [],
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
