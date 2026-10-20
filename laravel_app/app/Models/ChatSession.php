<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    /** Panjang judul otomatis dari pertanyaan pertama (FR-11). */
    public const TITLE_LENGTH = 40;

    /** Kolom waktu bertipe timestamptz: tulis beserta offset agar tidak bergeser zona. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = ['user_id', 'title'];

    protected $hidden = ['visitor_id', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /** Hanya sesi milik user tertentu. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public static function titleFrom(string $question): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $question));

        return rtrim(mb_substr($title, 0, self::TITLE_LENGTH));
    }
}
