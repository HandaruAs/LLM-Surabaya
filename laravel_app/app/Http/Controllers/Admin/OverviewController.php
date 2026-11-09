<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Rag\RagClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman "Ringkasan" admin: angka-angka singkat dan grafik aktivitas chatbot.
 * Hanya membaca data (tidak ada isi pertanyaan pengguna yang ditampilkan).
 */
class OverviewController extends Controller
{
    private const DAYS = 14;

    public function __invoke(RagClient $rag): View
    {
        $tz = 'Asia/Jakarta';

        $docs = DB::table('admin_documents')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $messages = DB::table('chat_messages');

        // Jumlah pertanyaan & jawaban per hari (WIB) selama 14 hari terakhir.
        $from = Carbon::now($tz)->startOfDay()->subDays(self::DAYS - 1);
        $daily = DB::table('chat_messages')
            ->where('created_at', '>=', $from->copy()->utc())
            ->select(
                DB::raw("to_char(created_at AT TIME ZONE '{$tz}', 'YYYY-MM-DD') as day"),
                'role',
                DB::raw('count(*) as total'),
            )
            ->groupBy('day', 'role')
            ->get();

        $series = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $d = $from->copy()->addDays($i);
            $series[$d->format('Y-m-d')] = ['day' => $d->format('Y-m-d'), 'label' => $d->translatedFormat('d M'), 'questions' => 0, 'answers' => 0];
        }
        foreach ($daily as $row) {
            if (isset($series[$row->day])) {
                $series[$row->day][$row->role === ChatMessage::ROLE_USER ? 'questions' : 'answers'] = (int) $row->total;
            }
        }

        $recentDocs = DB::table('admin_documents')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['original_name', 'status', 'size_bytes', 'updated_at'])
            ->map(fn ($d) => [
                'name' => $d->original_name,
                'status' => $d->status,
                'size' => (int) $d->size_bytes,
                'updated_at' => Carbon::parse($d->updated_at, config('app.timezone'))->toIso8601ZuluString(),
            ]);

        $health = $rag->health();

        $stats = [
            'documents' => [
                'total' => (int) $docs->sum(),
                'indexed' => (int) ($docs['indexed'] ?? 0),
                'failed' => (int) ($docs['failed'] ?? 0),
                'pending' => (int) ($docs['pending'] ?? 0),
            ],
            'users' => User::query()->where('role', User::ROLE_USER)->count(),
            'admins' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'sessions' => DB::table('chat_sessions')->whereNotNull('user_id')->count(),
            'questions' => (clone $messages)->where('role', ChatMessage::ROLE_USER)->count(),
            'answers' => (clone $messages)->where('role', ChatMessage::ROLE_ASSISTANT)->count(),
            'avg_response_ms' => (int) round((float) (clone $messages)->where('role', ChatMessage::ROLE_ASSISTANT)->avg('response_ms')),
            'questions_week' => (int) collect($series)->slice(-7)->sum('questions'),
            'daily' => array_values($series),
            'recent_documents' => $recentDocs,
            'ai_online' => $health !== null && ($health['ollama'] ?? true) === true,
            'routes' => [
                'documents' => route('admin.documents.index'),
                'ai_config' => route('admin.ai-config'),
                'ai_chat' => route('admin.ai-chat'),
                'accounts' => route('admin.accounts.index'),
            ],
        ];

        return view('admin.overview', compact('stats'));
    }
}
