{{-- DC copy in a list row. Needs $kind, $id, $file. --}}
<div class="rowx" style="flex-wrap:nowrap;align-items:center;gap:6px">
    @if ($file)<a class="ab sm" href="{{ route('dc.show', [$kind, $id]) }}" target="_blank">View</a>@endif
    <form method="post" action="{{ route('dc.upload', [$kind, $id]) }}" enctype="multipart/form-data" class="rowx" style="margin:0;flex-wrap:nowrap;gap:6px">@csrf
        <input type="file" name="dc_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required style="max-width:150px;font-size:11px">
        <button class="ab sm">{{ $file ? 'Replace' : 'Upload' }}</button>
    </form>
</div>
