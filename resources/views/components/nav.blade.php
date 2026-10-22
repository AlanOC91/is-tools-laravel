@props(['href', 'icon', 'active' => false])
<a href="{{ $href }}" class="nav-item {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><i data-lucide="{{ $icon }}"></i><span>{{ $slot }}</span>@if($active)<span class="active-dot"></span>@endif</a>
