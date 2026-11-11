<?php

namespace App\Http\Controllers;

use App\Support\Dues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Payments & reminders: unpaid invoices and the e-mail reminders for them. */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $list = Dues::all();
        $n = \App\Support\Pager::size($request->query('n'));
        return view('payments.index', [
            'list' => \App\Support\Pager::ofArray($list, $n, $request), 'n' => $n,
            'over' => count(array_filter($list, fn ($x) => $x->late > 0)),
            'total' => array_sum(array_map(fn ($x) => $x->bal, $list)),
            'on' => Dues::remindOn(), 'every' => Dues::remindEvery(), 'everyOptions' => Dues::EVERY,
            'sent' => (int) DB::table('reminders')->count(),
            'log' => DB::table('reminders as r')->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')->orderByDesc('r.id')->limit(8)->get(['r.created_at', 'r.to_email', 'r.subject', 'c.name as customer']),
            'last' => DB::table('reminders')->where('body', '!=', '')->orderByDesc('id')->first(['to_email', 'subject', 'body']),
        ]);
    }

    /* Sends a sample reminder to an address typed in, to check the mail settings. Nothing is written to the reminder log. */
    public function testMail(Request $request)
    {
        $to = trim((string) $request->input('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('payments.index')->with('bad', 'Type a valid email address to send the test to.');
        }
        $company = (string) DB::table('company_profile')->where('id', 1)->value('name');
        $body = "Dear customer,\n\nThis is a TEST of the payment reminder email. If you can read this, the mail settings work.\n\nSample: invoice KPI/26-27/001 dated 01/10/2026 has a balance of INR 1,000.00 which was due on 16/10/2026.\n\nRegards,\n" . ($company ?: 'Your company');
        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($to) {
                $m->to($to)->subject('Test: payment reminder');
            });
        } catch (\Throwable $e) {
            return redirect()->route('payments.index')->with('bad', 'The test email could not be sent: ' . $e->getMessage());
        }
        $mailer = config('mail.default');
        return redirect()->route('payments.index')->with('ok', $mailer === 'log'
            ? 'Test written to storage\\logs\\laravel.log (mail is set to "log", so nothing was emailed).'
            : 'Test email sent to ' . $to . '. Check the inbox and the spam folder.');
    }

    public function settings(Request $request)
    {
        Dues::save($request->input('on') === '1', (int) $request->input('every', 5));
        return redirect()->route('payments.index')->with('ok', 'Reminder settings saved.');
    }

    public function remind(string $kind, int $id)
    {
        abort_unless(in_array($kind, ['sale', 'rental'], true), 404);
        $x = Dues::find($kind, $id);
        if (!$x) {
            return redirect()->route('payments.index')->with('bad', 'That invoice is already paid or was not found.');
        }
        try {
            Dues::send($x);
        } catch (RuntimeException $e) {
            return redirect()->route('payments.index')->with('bad', $e->getMessage());
        }
        return redirect()->route('payments.index')->with('ok', 'Reminder emailed to ' . $x->email);
    }

    public function remindAll()
    {
        $sent = 0;
        $failed = [];
        foreach (array_filter(Dues::all(), fn ($x) => $x->late > 0) as $x) {
            try {
                Dues::send($x);
                $sent++;
            } catch (RuntimeException $e) {
                $failed[] = $x->no . ': ' . $e->getMessage();
            }
        }
        if ($failed) {
            return redirect()->route('payments.index')->with('bad', $sent . ' reminders emailed. ' . count($failed) . ' could not be sent. ' . $failed[0]);
        }
        return redirect()->route('payments.index')->with('ok', $sent . ' reminders emailed');
    }

    public function paid(string $kind, int $id)
    {
        abort_unless(in_array($kind, ['sale', 'rental'], true), 404);
        try {
            $no = Dues::markPaid($kind, $id);
        } catch (RuntimeException $e) {
            return redirect()->route('payments.index')->with('bad', $e->getMessage());
        }
        return redirect()->route('payments.index')->with('ok', 'Payment recorded for ' . $no);
    }
}
