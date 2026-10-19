<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSource extends Model
{
    // Tabel dari tim AI: tanpa kolom id dan timestamps,
    // primary key gabungan (message_id, filename_snapshot, chunk_snapshot).
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['chunk_id', 'filename_snapshot', 'chunk_snapshot'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
