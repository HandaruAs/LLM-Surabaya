<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    /** Panjang judul otomatis dari pertanyaan pertama (FR-11). */
    public const TITLE_LENGTH = 40;

    /** Kolom waktu bertipe timestamptz: tulis beserta offset agar tidak bergeser zona. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = ['visitor_id', 'title'];

    protected $hidden = ['visitor_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /** Hanya sesi milik pengunjung tertentu. */
    public function scopeOwnedBy(Builder $query, string $visitorId): Builder
    {
        return $query->where('visitor_id', $visitorId);
    }

    public static function titleFrom(string $question): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $question));

        return rtrim(mb_substr($title, 0, self::TITLE_LENGTH));
    }
}
