<?php

namespace App\Http\Controllers;

use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
 * Tasks with due dates. Everybody has a list of the tasks given to them. Whoever has "My tasks > Manage" (the Admin always) can also give a task
 * to another login and see everybody's tasks. user_id = who has to do it, created_by = who gave it.
 * Done / move: the one who has to do it, the one who gave it, or a manager. Edit / delete: the one who gave it, or a manager.
 */
class TasksController extends Controller
{
    private function task(Request $request, int $id, bool $owner = false)
    {
        $t = DB::table('tasks')->where('id', $id)->first();
        abort_unless($t, 404);
        $me = $request->user()->id;
        $ok = Perm::has('tasks.manage') || (int) $t->created_by === $me || (!$owner && (int) $t->user_id === $me);
        abort_unless($ok, 403, 'This task is not yours.');
        return $t;
    }

    /* One line of the history of a task: who did what, when, and the reason they wrote. */
    private function event(Request $request, $task, string $action, ?string $note = null, ?string $from = null, ?string $to = null): void
    {
        DB::table('task_events')->insert(['task_id' => $task->id, 'title' => mb_substr((string) $task->title, 0, 200), 'user_id' => $request->user()->id, 'for_user_id' => $task->user_id,
            'action' => $action, 'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null, 'from_date' => $from, 'to_date' => $to, 'created_at' => now()]);
    }

    private function date(?string $d): ?string
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)) ? $d : null;
    }

    private function staff()
    {
        return DB::table('users')->whereIn('role', ['admin', 'subadmin'])->where('active', 1)->orderBy('name')->get(['id', 'name', 'role']);
    }

    /* The person a task is given to: only the managers may choose somebody else. */
    private function assignee(Request $request): ?int
    {
        $me = $request->user()->id;
        $to = (int) $request->input('assigned_to', $me);
        if ($to === $me || !Perm::has('tasks.manage')) {
            return $me;
        }
        return $this->staff()->contains('id', $to) ? $to : null;
    }

    public function index(Request $request)
    {
        $today = now()->toDateString();
        $me = $request->user()->id;
        $manager = Perm::has('tasks.manage');
        $scope = in_array($request->query('scope'), ['mine', 'given', 'all'], true) ? $request->query('scope') : 'mine';
        if ($scope === 'all' && !$manager) {
            $scope = 'mine';
        }
        $who = $manager && $scope === 'all' ? (int) $request->query('who', 0) : 0;
        $f = in_array($request->query('f'), ['open', 'overdue', 'today', 'done'], true) ? $request->query('f') : 'open';

        $base = function () use ($scope, $me, $who) {
            $q = DB::table('tasks as k');
            if ($scope === 'mine') {
                $q->where('k.user_id', $me);
            } elseif ($scope === 'given') {
                $q->where('k.created_by', $me)->where('k.user_id', '!=', $me);
            } elseif ($who) {
                $q->where('k.user_id', $who);
            }
            return $q;
        };
        $q = $base()->leftJoin('users as u', 'u.id', '=', 'k.user_id')->leftJoin('users as g', 'g.id', '=', 'k.created_by')
            ->select('k.*', 'u.name as assignee', 'g.name as giver');
        if ($f === 'done') {
            $q->where('k.status', 'done')->orderByDesc('k.done_at');
        } else {
            $q->where('k.status', 'open');
            if ($f === 'overdue') {
                $q->where('k.due_date', '<', $today);
            } elseif ($f === 'today') {
                $q->where('k.due_date', $today);
            }
            $q->orderBy('k.due_date')->orderBy('k.id');
        }
        $n = [
            'open' => $base()->where('k.status', 'open')->count(),
            'overdue' => $base()->where('k.status', 'open')->where('k.due_date', '<', $today)->count(),
            'today' => $base()->where('k.status', 'open')->where('k.due_date', $today)->count(),
            'done' => $base()->where('k.status', 'done')->count(),
        ];

        $team = collect();
        if ($manager) {
            $team = DB::table('users as u')->whereIn('u.role', ['admin', 'subadmin'])->where('u.active', 1)->orderBy('u.name')
                ->selectRaw("u.id, u.name, u.role,
                    (select count(*) from tasks k where k.user_id = u.id and k.status = 'open') as n_open,
                    (select count(*) from tasks k where k.user_id = u.id and k.status = 'open' and k.due_date < ?) as n_late,
                    (select count(*) from tasks k where k.user_id = u.id and k.status = 'open' and k.due_date = ?) as n_today,
                    (select count(*) from tasks k where k.user_id = u.id and k.status = 'done') as n_done", [$today, $today])->get();
        }
        return view('tasks.index', ['rows' => $q->paginate(25)->withQueryString(), 'f' => $f, 'n' => $n, 'today' => $today, 'scope' => $scope, 'who' => $who,
            'manager' => $manager, 'team' => $team, 'staff' => $manager ? $this->staff() : collect(), 'me' => $me]);
    }

    public function store(Request $request)
    {
        $title = trim((string) $request->input('title', ''));
        $due = $this->date($request->input('due_date'));
        if ($title === '') {
            return back()->withInput()->withErrors(['form' => 'Write what the task is.']);
        }
        if (!$due) {
            return back()->withInput()->withErrors(['form' => 'Choose a due date.']);
        }
        $to = $this->assignee($request);
        if (!$to) {
            return back()->withInput()->withErrors(['form' => 'Choose who the task is for.']);
        }
        $newId = DB::table('tasks')->insertGetId(['user_id' => $to, 'created_by' => $request->user()->id, 'title' => mb_substr($title, 0, 200), 'notes' => trim((string) $request->input('notes', '')) ?: null,
            'due_date' => $due, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->event($request, DB::table('tasks')->where('id', $newId)->first(), 'added', trim((string) $request->input('notes', '')), null, $due);
        \App\Support\Audit::log('add', 'task', (string) $newId, mb_substr($title, 0, 80) . ' · for ' . DB::table('users')->where('id', $to)->value('name') . ' · due ' . $due);
        return redirect()->route('tasks.index', $to === $request->user()->id ? [] : ['scope' => 'given'])->with('ok', 'Task added');
    }

    public function edit(Request $request, int $id)
    {
        return view('tasks.edit', ['t' => $this->task($request, $id, true), 'staff' => Perm::has('tasks.manage') ? $this->staff() : collect()]);
    }

    public function update(Request $request, int $id)
    {
        $t = $this->task($request, $id, true);
        $title = trim((string) $request->input('title', ''));
        $due = $this->date($request->input('due_date'));
        if ($title === '' || !$due) {
            return back()->withInput()->withErrors(['form' => 'A task needs a title and a due date.']);
        }
        $to = Perm::has('tasks.manage') ? $this->assignee($request) : (int) $t->user_id;
        if (!$to) {
            return back()->withInput()->withErrors(['form' => 'Choose who the task is for.']);
        }
        DB::table('tasks')->where('id', $id)->update(['title' => mb_substr($title, 0, 200), 'notes' => trim((string) $request->input('notes', '')) ?: null,
            'due_date' => $due, 'user_id' => $to, 'moved' => $t->moved + ($due !== $t->due_date ? 1 : 0), 'updated_at' => now()]);
        $this->event($request, DB::table('tasks')->where('id', $id)->first(), 'edited', null, $t->due_date, $due);
        return redirect()->route('tasks.index')->with('ok', 'Task updated');
    }

    public function done(Request $request, int $id)
    {
        $t = $this->task($request, $id);
        if (trim((string) $request->input('note', '')) === '') {
            return back()->withErrors(['form' => 'Write a note before marking the task done.']);
        }
        DB::table('tasks')->where('id', $id)->update(['status' => 'done', 'done_at' => now(), 'updated_at' => now()]);
        $this->event($request, $t, 'done', (string) $request->input('note', ''));
        \App\Support\Audit::log('edit', 'task', $id, 'Marked done');
        return back()->with('ok', 'Task marked done');
    }

    public function reopen(Request $request, int $id)
    {
        $t = $this->task($request, $id);
        DB::table('tasks')->where('id', $id)->update(['status' => 'open', 'done_at' => null, 'updated_at' => now()]);
        $this->event($request, $t, 'reopened', (string) $request->input('note', ''));
        return back()->with('ok', 'Task opened again');
    }

    public function reschedule(Request $request, int $id)
    {
        $t = $this->task($request, $id);
        $due = $this->date($request->input('due_date'));
        if (!$due) {
            return back()->withErrors(['form' => 'Choose a valid new date.']);
        }
        $why = trim((string) $request->input('note', ''));
        if ($why === '') {
            return back()->withErrors(['form' => 'Write the reason for moving the task to another date.']);
        }
        DB::table('tasks')->where('id', $id)->update(['due_date' => $due, 'moved' => $t->moved + 1, 'updated_at' => now()]);
        $this->event($request, $t, 'moved', $why, $t->due_date, $due);
        \App\Support\Audit::log('edit', 'task', $id, 'Moved from ' . $t->due_date . ' to ' . $due);
        return back()->with('ok', 'Task moved to ' . \Carbon\Carbon::parse($due)->format('d/m/Y'));
    }

    /* The report: every action on every task, newest first. Only for those who manage tasks (the Admin). */
    public function history(Request $request)
    {
        abort_unless(Perm::has('tasks.manage') || Perm::isAdmin(), 403);
        $who = (int) $request->query('who', 0);
        $act = in_array($request->query('a'), ['added', 'done', 'moved', 'reopened', 'edited'], true) ? $request->query('a') : '';
        $q = DB::table('task_events as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('users as w', 'w.id', '=', 'e.for_user_id')
            ->select('e.*', 'u.name as by_name', 'w.name as for_name');
        if ($who) {
            $q->where(fn ($x) => $x->where('e.user_id', $who)->orWhere('e.for_user_id', $who));
        }
        if ($act) {
            $q->where('e.action', $act);
        }
        if ($from = $this->date($request->query('from'))) {
            $q->whereDate('e.created_at', '>=', $from);
        }
        if ($to = $this->date($request->query('to'))) {
            $q->whereDate('e.created_at', '<=', $to);
        }
        if (trim((string) $request->query('q', '')) !== '') {
            $q->where('e.title', 'like', '%' . trim((string) $request->query('q')) . '%');
        }
        return view('tasks.history', ['rows' => $q->orderByDesc('e.id')->paginate(30)->withQueryString(), 'staff' => $this->staff(), 'who' => $who, 'act' => $act,
            'from' => $from, 'to' => $to, 'q' => trim((string) $request->query('q', ''))]);
    }

    public function destroy(Request $request, int $id)
    {
        $t = $this->task($request, $id, true);
        DB::table('tasks')->where('id', $id)->delete();
        \App\Support\Audit::log('delete', 'task', $id, $t->title);
        return back()->with('ok', 'Task deleted');
    }
}
