<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Returns, replacements and upgrades (barcode consumption), and undoing them. Each change is one database transaction. */
class Moves
{
    public const REASONS = ['Rent period over', 'Project closed', 'Customer reduced seats'];
    public const KIND_RENTAL = 'Rental received';
    public const KIND_SALE = 'Sales received (credit note)';

    private static function bc(int $code): string
    {
        return Barcodes::text($code);
    }

    /* True when something was written in the history of the piece after the history row $histId (so it has moved on since). */
    private static function movedAfter(int $code, ?int $histId): bool
    {
        if (!$histId) {
            return true;
        }
        return DB::table('device_history')->where('code', $code)->where('id', '>', $histId)->exists();
    }

    private static function history(int $code, string $text, ?int $userId, $now = null): int
    {
        return (int) DB::table('device_history')->insertGetId(['code' => $code, 'at' => $now ?: now(), 'text' => $text, 'user_id' => $userId]);
    }

    /* ---------------------------------------------------------------- return */

    /* On rent with a customer: used by the Return screen. Each row: code, bc, model, config, rented_at, challan. */
    public static function onRent(int $customerId): array
    {
        $prefix = Barcodes::prefix();
        return DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.status', 'rented')->where('d.customer_id', $customerId)
            ->orderBy('d.code')->get(['d.code', 'd.config', 'd.rented_at', 'd.rent_no', 'i.name as model'])
            ->map(fn ($r) => ['code' => (int) $r->code, 'bc' => $prefix . $r->code, 'model' => $r->model, 'config' => $r->config, 'rented_at' => $r->rented_at ? substr($r->rented_at, 0, 10) : '', 'challan' => (string) $r->rent_no])->all();
    }

    /*
     * $d: customer_id, notes, items => [['code' => int, 'cond' => 'Good'|'Damaged', 'reason' => string, 'note' => string]]
     * Good pieces go back to stock, damaged ones go to repair. Returns the new return id.
     */
    public static function createReturn(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $customer = DB::table('customers')->where('id', $d['customer_id'])->first(['id', 'name']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            $codes = array_column($d['items'], 'code');
            $devs = DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->get(['code', 'status', 'customer_id'])->keyBy('code');
            foreach ($d['items'] as $it) {
                $dv = $devs[$it['code']] ?? null;
                if (!$dv || $dv->status !== 'rented' || (int) $dv->customer_id !== (int) $customer->id) {
                    throw new RuntimeException(self::bc($it['code']) . ' is not on rent with this customer.');
                }
            }
            $reasons = [];
            foreach ($d['items'] as $it) {
                if ($it['reason'] !== '' && !in_array($it['reason'], $reasons, true)) {
                    $reasons[] = $it['reason'];
                }
            }
            $fy = Tax::fy(now());
            $no = Barcodes::prefix() . '/' . $fy . '/' . str_pad((string) Rentals::counter('ret-' . $fy), 3, '0', STR_PAD_LEFT);
            $now = now();
            $rid = DB::table('returns')->insertGetId([
                'no' => $no, 'customer_id' => $customer->id, 'reason' => mb_substr(implode(', ', $reasons), 0, 255), 'notes' => $d['notes'] ?? '', 'user_id' => $userId, 'created_at' => $now,
            ]);
            foreach ($d['items'] as $it) {
                $damaged = $it['cond'] === 'Damaged';
                DB::table('devices')->where('code', $it['code'])->update([
                    'status' => $damaged ? 'repair' : 'in_stock', 'customer_id' => null, 'rented_at' => null, 'return_by' => null, 'rent_no' => null,
                ]);
                $text = 'Returned by ' . $customer->name . ' · return ' . $no . ($it['reason'] !== '' ? ' · ' . $it['reason'] : '') . ($damaged ? ' · damaged, sent to repair' : '');
                $hid = self::history($it['code'], $text, $userId, $now);
                DB::table('return_lines')->insert(['return_id' => $rid, 'code' => $it['code'], 'cond' => $it['cond'], 'reason' => $it['reason'], 'note' => $it['note'], 'hist_id' => $hid]);
            }
            // close the open lines of the challans (never the lines of a rental invoice)
            DB::table('rental_lines')->whereIn('code', $codes)->whereNull('returned_at')
                ->whereIn('rental_id', DB::table('rentals')->where('kind', 'challan')->select('id'))
                ->update(['returned_at' => $now, 'return_id' => $rid]);
            return $rid;
        });
    }

    public static function returnDeleteBlocker(int $id): ?string
    {
        $r = DB::table('returns')->where('id', $id)->first(['id', 'no']);
        if (!$r) {
            return 'That return was not found.';
        }
        $lines = DB::table('return_lines')->where('return_id', $id)->get(['code', 'hist_id']);
        $moved = 0;
        foreach ($lines as $l) {
            $st = DB::table('devices')->where('code', $l->code)->value('status');
            if (!in_array($st, ['in_stock', 'repair'], true) || self::movedAfter((int) $l->code, $l->hist_id ? (int) $l->hist_id : null)) {
                $moved++;
            }
        }
        if ($moved) {
            return 'Return ' . $r->no . ' cannot be deleted. ' . $moved . ' of its items have been rented, sold, repaired or changed since they came back.';
        }
        if (DB::table('rental_lines')->where('return_id', $id)->count() < $lines->count()) {
            return 'Return ' . $r->no . ' cannot be deleted. The challan it belongs to was changed.';
        }
        return null;
    }

    /* Deletes a return: its pieces go back on rent with the customer, as before. */
    public static function deleteReturn(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $r = DB::table('returns as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->where('r.id', $id)->lockForUpdate()->first(['r.id', 'r.no', 'r.customer_id', 'c.name']);
            if (!$r) {
                throw new RuntimeException('That return was not found.');
            }
            $codes = DB::table('return_lines')->where('return_id', $id)->pluck('code')->all();
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->pluck('code');
            }
            if ($m = self::returnDeleteBlocker($id)) {
                throw new RuntimeException($m);
            }
            $now = now();
            $lines = DB::table('rental_lines as l')->join('rentals as c', 'c.id', '=', 'l.rental_id')->where('l.return_id', $id)->get(['l.id', 'l.code', 'l.from_at', 'c.no', 'c.return_by']);
            foreach ($lines as $l) {
                DB::table('devices')->where('code', $l->code)->update(['status' => 'rented', 'customer_id' => $r->customer_id, 'rented_at' => $l->from_at, 'return_by' => $l->return_by, 'rent_no' => $l->no]);
                DB::table('rental_lines')->where('id', $l->id)->update(['returned_at' => null, 'return_id' => null]);
                self::history((int) $l->code, 'Return cancelled · return ' . $r->no . ' deleted, back on rent with ' . $r->name, $userId, $now);
            }
            DB::table('returns')->where('id', $id)->delete();   // the lines go with it
            Audit::log('delete', 'return', $r->no, 'Customer ' . $r->name . ' · ' . count($codes) . ' pieces back on rent');
        });
    }

    /* ---------------------------------------------------------------- replacement */

    /*
     * $d: kind (KIND_RENTAL or KIND_SALE), customer_id, old_code (the faulty piece), new_code (the working piece), reason
     * Rental: the faulty piece goes to repair, the new one is rented to the same customer at the same rent.
     * Sale: the faulty piece goes to repair and a credit note number is made, the new one is sold to the customer.
     */
    public static function createReplacement(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $sold = $d['kind'] === self::KIND_SALE;
            $customer = DB::table('customers')->where('id', $d['customer_id'])->first(['id', 'name']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            if ($d['old_code'] === $d['new_code']) {
                throw new RuntimeException('The faulty item and the replacement must be different.');
            }
            $devs = DB::table('devices')->whereIn('code', [$d['old_code'], $d['new_code']])->lockForUpdate()->get(['code', 'item_id', 'status', 'customer_id', 'return_by', 'rent_no'])->keyBy('code');
            $old = $devs[$d['old_code']] ?? null;
            $new = $devs[$d['new_code']] ?? null;
            if (!$old || $old->status !== ($sold ? 'sold' : 'rented') || (int) $old->customer_id !== (int) $customer->id) {
                throw new RuntimeException(self::bc($d['old_code']) . ' is not ' . ($sold ? 'sold to' : 'on rent with') . ' this customer.');
            }
            if (!$new || $new->status !== 'in_stock') {
                throw new RuntimeException('The replacement ' . self::bc($d['new_code']) . ' is not in stock.');
            }
            // The replacement must be of the same category as the faulty item (or the same item when it has no category).
            $cats = DB::table('items')->whereIn('id', [$old->item_id, $new->item_id])->pluck('category_id', 'id');
            $oc = $cats[$old->item_id] ?? null;
            if ($oc ? (int) ($cats[$new->item_id] ?? 0) !== (int) $oc : (int) $new->item_id !== (int) $old->item_id) {
                throw new RuntimeException('The replacement must be of the same category as the faulty item. ' . self::bc($d['new_code']) . ' is from another category.');
            }
            $now = now();
            $line = null;
            $rent = null;
            if (!$sold) {
                $line = DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')->where('r.kind', 'challan')->where('l.code', $old->code)->whereNull('l.returned_at')
                    ->orderByDesc('l.id')->first(['l.id', 'l.rental_id', 'l.rent']);
                if (!$line) {
                    throw new RuntimeException('The rental record of ' . self::bc($d['old_code']) . ' could not be found.');
                }
                $rent = (float) $line->rent;
            }
            $fy = Tax::fy($now);
            $cn = $sold ? 'CN/' . $fy . '/' . str_pad((string) Rentals::counter('cn-' . $fy), 3, '0', STR_PAD_LEFT) : null;
            $rid = DB::table('replacements')->insertGetId([
                'kind' => $d['kind'], 'customer_id' => $customer->id, 'old_code' => $old->code, 'new_code' => $new->code, 'reason' => $d['reason'], 'cn' => $cn,
                'rent' => $rent, 'old_return_by' => $old->return_by, 'user_id' => $userId, 'created_at' => $now,
            ]);
            DB::table('devices')->where('code', $old->code)->update(['status' => 'repair', 'customer_id' => null, 'rented_at' => null, 'return_by' => null, 'rent_no' => null]);
            $oldHist = self::history((int) $old->code, 'Replaced at ' . $customer->name . ' · faulty, sent to repair', $userId, $now);
            if ($sold) {
                DB::table('devices')->where('code', $new->code)->update(['status' => 'sold', 'customer_id' => $customer->id]);
                $newHist = self::history((int) $new->code, 'Sold as replacement to ' . $customer->name . ' (credit note ' . $cn . ')', $userId, $now);
            } else {
                DB::table('rental_lines')->where('id', $line->id)->update(['returned_at' => $now, 'close_replacement_id' => $rid]);
                $it = DB::table('items')->where('id', $new->item_id)->first(['id', 'hsn', 'gst_percent']);
                DB::table('rental_lines')->insert([
                    'rental_id' => $line->rental_id, 'code' => $new->code, 'item_id' => $it->id, 'rent' => $rent, 'gst_percent' => $it->gst_percent, 'hsn' => config('rentstock.rent_sac', '997315'),
                    'from_at' => $now, 'open_replacement_id' => $rid,
                ]);
                DB::table('devices')->where('code', $new->code)->update(['status' => 'rented', 'customer_id' => $customer->id, 'rented_at' => $now, 'return_by' => $old->return_by, 'rent_no' => $old->rent_no]);
                $newHist = self::history((int) $new->code, 'Rented to ' . $customer->name . ' as replacement for ' . self::bc((int) $old->code), $userId, $now);
            }
            DB::table('replacements')->where('id', $rid)->update(['old_hist_id' => $oldHist, 'new_hist_id' => $newHist]);
            return $rid;
        });
    }

    public static function replacementDeleteBlocker(int $id): ?string
    {
        $r = DB::table('replacements')->where('id', $id)->first();
        if (!$r) {
            return 'That replacement was not found.';
        }
        $sold = $r->kind === self::KIND_SALE;
        $old = DB::table('devices')->where('code', $r->old_code)->first(['status']);
        $new = DB::table('devices')->where('code', $r->new_code)->first(['status', 'customer_id']);
        $bad = !$old || $old->status !== 'repair' || self::movedAfter((int) $r->old_code, $r->old_hist_id ? (int) $r->old_hist_id : null)
            || !$new || $new->status !== ($sold ? 'sold' : 'rented') || (int) $new->customer_id !== (int) $r->customer_id || self::movedAfter((int) $r->new_code, $r->new_hist_id ? (int) $r->new_hist_id : null);
        if ($bad) {
            return 'This replacement cannot be deleted. The faulty item or the new item has moved on since (repaired, rented, sold or returned).';
        }
        if (!$sold && DB::table('rentals')->where('kind', 'invoice')->where('customer_id', $r->customer_id)->whereDate('period_to', '>=', substr((string) $r->created_at, 0, 10))->exists()) {
            return 'This replacement cannot be deleted. A rental invoice already bills this customer for a period after the swap. Delete that invoice first.';
        }
        return null;
    }

    public static function deleteReplacement(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $r = DB::table('replacements')->where('id', $id)->lockForUpdate()->first();
            if (!$r) {
                throw new RuntimeException('That replacement was not found.');
            }
            DB::table('devices')->whereIn('code', [$r->old_code, $r->new_code])->lockForUpdate()->pluck('code');
            if ($m = self::replacementDeleteBlocker($id)) {
                throw new RuntimeException($m);
            }
            $sold = $r->kind === self::KIND_SALE;
            $name = (string) DB::table('customers')->where('id', $r->customer_id)->value('name');
            $now = now();
            if ($sold) {
                DB::table('devices')->where('code', $r->old_code)->update(['status' => 'sold', 'customer_id' => $r->customer_id]);
            } else {
                $line = DB::table('rental_lines as l')->join('rentals as c', 'c.id', '=', 'l.rental_id')->where('l.close_replacement_id', $id)->first(['l.id', 'l.from_at', 'c.no', 'c.return_by']);
                if (!$line) {
                    throw new RuntimeException('The challan of this replacement was changed, so it cannot be deleted.');
                }
                DB::table('rental_lines')->where('open_replacement_id', $id)->delete();
                DB::table('rental_lines')->where('id', $line->id)->update(['returned_at' => null, 'close_replacement_id' => null]);
                DB::table('devices')->where('code', $r->old_code)->update(['status' => 'rented', 'customer_id' => $r->customer_id, 'rented_at' => $line->from_at, 'return_by' => $r->old_return_by, 'rent_no' => $line->no]);
            }
            DB::table('devices')->where('code', $r->new_code)->update(['status' => 'in_stock', 'customer_id' => null, 'rented_at' => null, 'return_by' => null, 'rent_no' => null]);
            self::history((int) $r->old_code, 'Replacement cancelled · back with ' . $name, $userId, $now);
            self::history((int) $r->new_code, 'Replacement cancelled · back in stock', $userId, $now);
            DB::table('replacements')->where('id', $id)->delete();
            Audit::log('delete', 'replacement', $id, $r->kind . ' · ' . self::bc((int) $r->old_code) . ' to ' . self::bc((int) $r->new_code) . ($r->cn ? ' · credit note ' . $r->cn . ' number is not reused' : ''));
        });
    }

    /* ---------------------------------------------------------------- upgrade (barcode consumption) */

    /*
     * Spare parts in stock, grouped by upgrade type: ['RAM' => [['id', 'label', 'qty', 'val'], ...], 'Storage' => ..., 'Part' => ...].
     * RAM and SSD items are found by name or category (a size such as 16 GB in the name). Every other quantity item in stock
     * (battery, keyboard...) is offered under 'Part', where taking it from stock is optional.
     */
    public static function partsInStock(): array
    {
        $out = ['RAM' => [], 'Storage' => [], 'Part' => []];
        $rows = DB::table('item_qty as q')->join('items as i', 'i.id', '=', 'q.item_id')->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where('q.qty', '>', 0)->get(['i.id', 'i.name', 'c.name as cat', 'q.qty']);
        foreach ($rows as $r) {
            $p = RentMath::itemPart($r->name, (string) ($r->cat ?? ''));
            if ($p && in_array($p['part'], ['RAM', 'Storage'], true)) {
                $out[$p['part']][] = ['id' => (int) $r->id, 'label' => RentMath::valLabel($p['part'], $p['val'], $r->name), 'qty' => (int) $r->qty, 'val' => $p['val']];
            } else {
                $out['Part'][] = ['id' => (int) $r->id, 'label' => $r->name, 'qty' => (int) $r->qty, 'val' => null];
            }
        }
        foreach ($out as &$list) {
            usort($list, fn ($a, $b) => ($a['val'] ?? 0) <=> ($b['val'] ?? 0) ?: strcmp($a['label'], $b['label']));
        }
        return $out;
    }

    /*
     * $d: code, ups => [['part' => 'RAM'|'Storage', 'item_id' => int], ['part' => 'Part', 'text' => 'Keyboard', 'item_id' => int|0]]
     * The device keeps its barcode. RAM and SSD change its config and take the chosen part out of stock.
     * A part replacement (battery, keyboard...) is written as typed; taking a part from stock is optional. Every change goes in the history
     * and in the configuration log.
     */
    public static function createUpgrade(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $dv = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.code', $d['code'])->lockForUpdate()->first(['d.code', 'd.config', 'd.status', 'i.name as model']);
            if (!$dv) {
                throw new RuntimeException('Find the device first.');
            }
            if ($dv->status === 'sold') {
                throw new RuntimeException('This item is sold, so it cannot be upgraded here.');
            }
            if (!$d['ups']) {
                throw new RuntimeException('Add what was changed.');
            }
            if (count($d['ups']) > 10) {
                throw new RuntimeException('One upgrade can have 10 rows at most.');
            }
            $seen = [];
            $used = [];   // quantity already taken per stock item in this upgrade
            $ups = [];
            foreach ($d['ups'] as $u) {
                if (!in_array($u['part'], RentMath::PART_TYPES, true)) {
                    throw new RuntimeException('Choose what was changed in every row.');
                }
                $it = null;
                if ($u['item_id']) {
                    $it = DB::table('items as i')->leftJoin('categories as c', 'c.id', '=', 'i.category_id')->where('i.id', $u['item_id'])->first(['i.id', 'i.name', 'c.name as cat']);
                    if (!$it) {
                        throw new RuntimeException('That part is not in the item list.');
                    }
                }
                if ($u['part'] === 'Part') {
                    $text = trim(preg_replace('/\s+/', ' ', (string) ($u['text'] ?? '')));
                    if ($text === '') {
                        throw new RuntimeException('Write which part was replaced (for example Battery or Keyboard).');
                    }
                    if (mb_strlen($text) > 60) {
                        throw new RuntimeException('The part name can have 60 characters at most.');
                    }
                    $ups[] = ['part' => 'Part', 'val' => null, 'item' => $it, 'label' => $text];
                } else {
                    if (isset($seen[$u['part']])) {
                        throw new RuntimeException('RAM and SSD can each be changed only once in one upgrade.');
                    }
                    $seen[$u['part']] = true;
                    if (!$it) {
                        throw new RuntimeException('Choose the new RAM or SSD from stock in every row.');
                    }
                    $p = RentMath::itemPart($it->name, (string) ($it->cat ?? ''));
                    if (!$p || $p['part'] !== $u['part']) {
                        throw new RuntimeException('That part does not match the type chosen.');
                    }
                    $ups[] = ['part' => $u['part'], 'val' => $p['val'], 'item' => $it, 'label' => RentMath::valLabel($p['part'], $p['val'], $it->name)];
                }
                if ($it) {
                    $used[$it->id] = ($used[$it->id] ?? 0) + 1;
                    $have = (int) DB::table('item_qty')->where('item_id', $it->id)->lockForUpdate()->value('qty');
                    if ($have < $used[$it->id]) {
                        throw new RuntimeException($it->name . ' is out of stock.');
                    }
                }
            }
            $chain = RentMath::upChain($dv->config, array_map(fn ($u) => ['part' => $u['part'], 'val' => $u['val']], $ups));
            $now = now();
            $last = 0;
            foreach ($chain as $k => $c) {
                $u = $ups[$k];
                $partText = RentMath::partLabel($u['part']);
                $isPart = $u['part'] === 'Part';
                $from = $isPart ? '' : ($c['old'] !== '' ? $c['old'] : 'old');
                $stockNote = $u['item'] ? ($isPart ? ' (part taken from stock: ' . $u['item']->name . ')' : ' (part taken from stock)') : '';
                $text = $isPart ? 'Part replaced: ' . $u['label'] . $stockNote : 'Upgraded ' . $partText . ': ' . $from . ' to ' . $u['label'] . $stockNote;
                $hid = self::history((int) $dv->code, $text, $userId, $now);
                $last = DB::table('consumptions')->insertGetId([
                    'code' => $dv->code, 'model' => $dv->model, 'part' => $partText, 'from_val' => $from, 'to_val' => $u['label'],
                    'old_cfg' => $c['cfg_before'], 'new_cfg' => $c['cfg_after'], 'used_item_id' => $u['item'] ? $u['item']->id : null, 'used_name' => $u['item'] ? $u['item']->name : '',
                    'hist_id' => $hid, 'user_id' => $userId, 'created_at' => $now,
                ]);
                if ($u['item']) {
                    DB::table('item_qty')->where('item_id', $u['item']->id)->decrement('qty');
                }
                ConfigLog::add((int) $dv->code, $isPart ? 'part' : 'upgrade', $c['cfg_before'], $c['cfg_after'],
                    $isPart ? 'Part replaced: ' . $u['label'] . ($u['item'] ? ' (stock item: ' . $u['item']->name . ')' : '') : $partText . ': ' . $from . ' to ' . $u['label'] . ' (part: ' . $u['item']->name . ')', $userId, $now);
            }
            DB::table('devices')->where('code', $dv->code)->update(['config' => $chain[count($chain) - 1]['cfg_after']]);
            return (int) $last;
        });
    }

    public static function consumptionDeleteBlocker(int $id): ?string
    {
        $c = DB::table('consumptions')->where('id', $id)->first();
        if (!$c) {
            return 'That upgrade was not found.';
        }
        $dv = DB::table('devices')->where('code', $c->code)->first(['config']);
        if (!$dv || $dv->config !== $c->new_cfg || self::movedAfter((int) $c->code, $c->hist_id ? (int) $c->hist_id : null)) {
            return 'This upgrade cannot be deleted. The device has been changed, rented, returned or upgraded again since.';
        }
        return null;
    }

    /* Deletes an upgrade: the old config comes back and the part goes back into stock. */
    public static function deleteConsumption(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $c = DB::table('consumptions')->where('id', $id)->lockForUpdate()->first();
            if (!$c) {
                throw new RuntimeException('That upgrade was not found.');
            }
            DB::table('devices')->where('code', $c->code)->lockForUpdate()->first(['code']);
            if ($m = self::consumptionDeleteBlocker($id)) {
                throw new RuntimeException($m);
            }
            DB::table('devices')->where('code', $c->code)->update(['config' => $c->old_cfg]);
            ConfigLog::add((int) $c->code, 'upgrade_undo', $c->new_cfg, $c->old_cfg, 'Upgrade undone: ' . ($c->from_val !== '' ? $c->part . ' ' . $c->from_val . ' to ' . $c->to_val : $c->part . ' ' . $c->to_val), $userId);
            if ($c->used_item_id) {
                DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, 1) ON DUPLICATE KEY UPDATE qty = qty + 1', [$c->used_item_id]);
            }
            self::history((int) $c->code, 'Upgrade cancelled: ' . ($c->from_val !== '' ? $c->part . ' ' . $c->from_val . ' to ' . $c->to_val : $c->part . ' ' . $c->to_val) . ' undone' . ($c->used_item_id ? ', part back in stock' : ''), $userId);
            DB::table('consumptions')->where('id', $id)->delete();
            Audit::log('delete', 'upgrade', $id, self::bc((int) $c->code) . ' · ' . ($c->from_val !== '' ? $c->part . ' ' . $c->from_val . ' to ' . $c->to_val : $c->part . ' ' . $c->to_val));
        });
    }
}
