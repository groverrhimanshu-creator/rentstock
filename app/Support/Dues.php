<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/*
 * Unpaid invoices (sale invoices and rental invoices) and the e-mail reminders for them.
 * An invoice is due 15 days after its date. From the due date a reminder goes out, then again every N days until it is paid.
 */
class Dues
{
    public const DUE_DAYS = 15;
    public const EVERY = [3, 5, 7, 10, 15];

    public static function setting(string $name, string $default = ''): string
    {
        $v = DB::table('settings')->where('name', $name)->value('value');
        return $v === null ? $default : (string) $v;
    }

    public static function remindOn(): bool
    {
        return self::setting('remind_on', '1') === '1';
    }

    public static function remindEvery(): int
    {
        $n = (int) self::setting('remind_every', '5');
        return in_array($n, self::EVERY, true) ? $n : 5;
    }

    public static function save(bool $on, int $every): void
    {
        $every = in_array($every, self::EVERY, true) ? $every : 5;
        foreach (['remind_on' => $on ? '1' : '0', 'remind_every' => (string) $every] as $k => $v) {
            DB::statement('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$k, $v]);
        }
    }

    /*
     * All unpaid invoices, oldest due date first. $customerId limits it to one customer.
     * Each row (object): kind (sale | rental), id, key, no, customer_id, customer, email, contact, total, paid, bal, doc_date, due, late, last, next.
     */
    public static function all(?int $customerId = null): array
    {
        $out = [];
        foreach (['sale' => 'sales', 'rental' => 'rentals'] as $kind => $table) {
            $q = DB::table($table . ' as d')->join('customers as c', 'c.id', '=', 'd.customer_id')
                ->whereRaw('d.total - d.paid > 0.5')
                ->select('d.id', 'd.no', 'd.customer_id', 'd.total', 'd.paid', 'd.created_at', 'c.name as customer', 'c.email', 'c.contact');
            if ($kind === 'rental') {
                $q->where('d.kind', 'invoice');
            }
            if ($customerId !== null) {
                $q->where('d.customer_id', $customerId);
            }
            foreach ($q->get() as $r) {
                $r->kind = $kind;
                $out[] = $r;
            }
        }
        $last = [];
        foreach (DB::table('reminders')->selectRaw('doc_kind, doc_id, max(created_at) as last_at')->groupBy('doc_kind', 'doc_id')->get() as $l) {
            $last[$l->doc_kind . '|' . $l->doc_id] = $l->last_at;
        }
        $todayNo = RentMath::dayNo(date('Y-m-d'));
        $every = self::remindEvery();
        foreach ($out as $r) {
            $r->key = $r->kind . '|' . $r->id;
            $r->bal = Tax::r2((float) $r->total - (float) $r->paid);
            $r->doc_date = substr((string) $r->created_at, 0, 10);
            $dueNo = RentMath::dayNo($r->doc_date) + self::DUE_DAYS;
            $r->due = gmdate('Y-m-d', $dueNo * 86400);
            $r->late = $todayNo - $dueNo;
            $r->last = isset($last[$r->key]) ? (string) $last[$r->key] : null;
            $nextNo = $r->last ? RentMath::dayNo($r->last) + $every : $dueNo;
            $r->next = gmdate('Y-m-d', max($nextNo, $todayNo) * 86400);
        }
        usort($out, fn ($a, $b) => strcmp($a->due, $b->due) ?: strcmp($a->key, $b->key));
        return $out;
    }

    public static function find(string $kind, int $id): ?object
    {
        foreach (self::all() as $r) {
            if ($r->kind === $kind && (int) $r->id === $id) {
                return $r;
            }
        }
        return null;
    }

    /* Marks an invoice as fully paid. */
    public static function markPaid(string $kind, int $id): string
    {
        $table = $kind === 'rental' ? 'rentals' : 'sales';
        $q = DB::table($table)->where('id', $id);
        if ($kind === 'rental') {
            $q->where('kind', 'invoice');
        }
        $d = $q->first(['id', 'no', 'total']);
        if (!$d) {
            throw new RuntimeException('That invoice was not found.');
        }
        DB::table($table)->where('id', $id)->update(['paid' => $d->total]);
        Audit::log('payment', $kind === 'rental' ? 'rental invoice' : 'sale invoice', $d->no, 'Marked as fully paid · INR ' . $d->total);
        return $d->no;
    }

    /* Sends one reminder e-mail and writes it in the log. Throws with a plain message when it cannot be sent. */
    public static function send(object $x): void
    {
        $email = trim((string) $x->email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException($x->customer . ' has no valid e-mail address. Add it under Masters, Customer.');
        }
        $company = (string) DB::table('company_profile')->where('id', 1)->value('name');
        $late = $x->late > 0;
        $contact = trim((string) $x->contact) !== '' ? $x->contact : $x->customer;
        $dateText = date('d/m/Y', strtotime($x->doc_date));
        $dueText = date('d/m/Y', strtotime($x->due));
        $body = 'Dear ' . $contact . ",\n\nThis is a friendly reminder that invoice " . $x->no . ' dated ' . $dateText . ' has a balance of INR ' . Tax::money($x->bal) . ' '
            . ($late ? 'which was due on ' . $dueText . ' (' . $x->late . ' days ago)' : 'due on ' . $dueText)
            . ".\n\nPlease arrange the payment at the earliest. You can view your invoices and challans by signing in to your customer account.\n\nRegards,\n" . $company;
        $subject = 'Payment reminder: invoice ' . $x->no;
        try {
            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            throw new RuntimeException('The e-mail could not be sent (' . $e->getMessage() . '). Check the mail settings in the .env file.');
        }
        DB::table('reminders')->insert([
            'doc_kind' => $x->kind, 'doc_id' => $x->id, 'doc_no' => $x->no, 'customer_id' => $x->customer_id,
            'to_email' => $email, 'subject' => $subject, 'body' => $body, 'created_at' => now(),
        ]);
    }

    /* Sends every reminder that is due today (used by the daily schedule). Returns [sent, failed messages]. */
    public static function sendDue(): array
    {
        $sent = 0;
        $failed = [];
        if (!self::remindOn()) {
            return [0, []];
        }
        $today = date('Y-m-d');
        foreach (self::all() as $x) {
            if ($x->next > $today) {
                continue;
            }
            try {
                self::send($x);
                $sent++;
            } catch (RuntimeException $e) {
                $failed[] = $x->no . ': ' . $e->getMessage();
            }
        }
        return [$sent, $failed];
    }
}
