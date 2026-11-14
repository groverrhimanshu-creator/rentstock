{{-- DC copy of one record: open, upload / replace, remove. Needs $kind, $id, $file, $name. --}}
<div class="cd noprint" style="margin-bottom:12px">
    <div class="rowx" style="align-items:center;flex-wrap:wrap">
        <b>DC copy</b>
        @if ($file)<a class="ab sm" href="{{ route('dc.show', [$kind, $id]) }}" target="_blank">{{ $name ?: 'Open' }}</a>@else<span class="hint">Not uploaded</span>@endif
        <form method="post" action="{{ route('dc.upload', [$kind, $id]) }}" enctype="multipart/form-data" class="rowx" style="margin:0;align-items:center">@csrf
            <input type="file" name="dc_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            <button class="ab sm solid">{{ $file ? 'Replace' : 'Upload' }}</button>
        </form>
        @if ($file)<form method="post" action="{{ route('dc.destroy', [$kind, $id]) }}" style="margin:0" onsubmit="return confirm('Remove the DC copy?')">@csrf @method('DELETE')<button class="ab sm danger">Remove</button></form>@endif
    </div>
</div>
