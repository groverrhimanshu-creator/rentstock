<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/* One rule for every long list: 20 rows a page unless the user chooses 10, 50 or 100 in "Show". */
class Pager
{
    public const SIZES = [10, 20, 50, 100];
    public const DEFAULT = 20;

    public static function size($n): int
    {
        $n = (int) $n;
        return in_array($n, self::SIZES, true) ? $n : self::DEFAULT;
    }

    /* A paginator over a list that is already in memory (reports, unpaid invoices). Page links keep the other query values. */
    public static function ofArray(array $items, int $n, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));
        $last = max(1, (int) ceil(count($items) / $n));
        $page = min($page, $last);
        return new LengthAwarePaginator(array_slice($items, ($page - 1) * $n, $n), count($items), $n, $page, [
            'path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName,
        ]);
    }
}
