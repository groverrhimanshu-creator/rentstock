<?php

namespace App\Http\Controllers;

use App\Support\Pager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* Activity log (Admin only): who did what and when. Fed by Audit::log() from every add, edit, delete, import, payment, task and sign-in. */
class ActivityController extends Controller
{
    public function __construct()
    {
        abort_unless(\App\Support\Features::activityLog(), 404);   // paid extra, off unless ACTIVITY_LOG_ENABLED=true
    }

    private function range(Request $request): array
    {
        $ok = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        $from = $ok($request->query('from')) ? $request->query('from') : now()->subDays(6)->toDateString();
        $to = $ok($request->query('to')) ? $request->query('to') : now()->toDateString();
        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function query(Request $request)
    {
        [$from, $to] = $this->range($request);
        $q = DB::table('audit_log as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->whereRaw('date(a.created_at) between ? and ?', [$from, $to])
            ->select('a.*', 'u.name as person', 'u.role as role');
        if ((int) $request->query('user') > 0) {
            $q->where('a.user_id', (int) $request->query('user'));
        }
        if ($request->filled('action')) {
            $q->where('a.action', (string) $request->query('action'));
        }
        if ($request->filled('subject')) {
            $q->where('a.subject', (string) $request->query('subject'));
        }
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->where('a.detail', 'like', $like)->orWhere('a.subject_id', 'like', $like)->orWhere('a.username', 'like', $like);
            });
        }
        return $q->orderByDesc('a.id');
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $n = Pager::size($request->query('n'));
        return view('activity.index', [
            'rows' => $this->query($request)->paginate($n)->withQueryString(), 'n' => $n, 'from' => $from, 'to' => $to,
            'f' => ['user' => (int) $request->query('user', 0), 'action' => (string) $request->query('action', ''), 'subject' => (string) $request->query('subject', ''), 'q' => trim((string) $request->query('q', ''))],
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name', 'username', 'role']),
            'actions' => DB::table('audit_log')->distinct()->orderBy('action')->pluck('action'),
            'subjects' => DB::table('audit_log')->distinct()->orderBy('subject')->pluck('subject'),
        ]);
    }

    public function csv(Request $request)
    {
        $q = $this->query($request);
        return response()->streamDownload(function () use ($q) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['When', 'User', 'Name', 'Action', 'What', 'Number / id', 'Details'], ',', '"', '');
            $q->chunk(500, function ($rows) use ($h) {
                foreach ($rows as $r) {
                    fputcsv($h, [$r->created_at, $r->username, (string) $r->person, $r->action, $r->subject, $r->subject_id, (string) $r->detail], ',', '"', '');
                }
            });
            fclose($h);
        }, 'activity-log-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
