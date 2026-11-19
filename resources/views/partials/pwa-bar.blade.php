@if (config('rentstock.pwa') && auth()->user()->role !== 'customer')
@php($c = \App\Support\Menu::current())
<nav class="mbar noprint" aria-label="Quick menu">
    <a href="{{ route('dashboard') }}" class="{{ $c === 'dashboard' ? 'on' : '' }}">{!! \App\Support\Icons::get('grid') !!}<span>Home</span></a>
    <a href="{{ route('stock.inventory') }}" class="{{ $c === 'inventory' ? 'on' : '' }}">{!! \App\Support\Icons::get('box') !!}<span>Stock</span></a>
    <a href="{{ route('scan') }}" class="scanb {{ $c === 'scan' ? 'on' : '' }}">{!! \App\Support\Icons::get('bars') !!}<span>Scan</span></a>
    <a href="{{ route('rental.index') }}" class="{{ $c === 'rental' ? 'on' : '' }}">{!! \App\Support\Icons::get('rent') !!}<span>Rental</span></a>
    <button type="button" id="mmore">{!! \App\Support\Icons::get('menu') !!}<span>More</span></button>
</nav>
@endif
