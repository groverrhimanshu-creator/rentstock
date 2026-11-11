@php
    $p = $rows->currentPage();
    $pages = max(1, $rows->lastPage());
    $s = max(1, $p - 2);
    $e = min($pages, $s + 4);
@endphp
<div class="pgn">
    <span>Showing {{ $rows->total() ? $rows->firstItem() : 0 }} to {{ $rows->total() ? $rows->lastItem() : 0 }} of {{ $rows->total() }} entries</span>
    <div class="pb">
        @if ($p > 1)<a href="{{ $rows->url($p - 1) }}">Prev</a>@else<span class="dis">Prev</span>@endif
        @for ($i = $s; $i <= $e; $i++)<a class="{{ $i === $p ? 'cur' : '' }}" href="{{ $rows->url($i) }}">{{ $i }}</a>@endfor
        @if ($p < $pages)<a href="{{ $rows->url($p + 1) }}">Next</a>@else<span class="dis">Next</span>@endif
    </div>
</div>
