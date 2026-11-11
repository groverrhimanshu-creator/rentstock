<form data-list method="get" action="{{ url()->current() }}" class="lt">
    <label class="f"><span>Show</span>
        <select name="n">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select>
    </label>
    <div class="sr"><label class="f"><span>Search</span><input name="q" value="{{ $q }}" placeholder="Type to search" autocomplete="off"></label></div>
</form>
