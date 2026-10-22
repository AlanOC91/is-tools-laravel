@props(['title' => 'Home'])
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title }} · IS Tools</title><link rel="icon" href="{{ asset('images/favicon.png') }}">@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body x-data="{ menu: false }"><a href="#main" class="skip-link">Skip to content</a>
<div class="app-shell">
    <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-30 bg-slate-900/30 lg:hidden"></div>
    <aside class="sidebar" :class="menu ? 'sidebar-open' : ''" aria-label="Main navigation">
        <a href="{{ route('home') }}" class="brand"><span class="brand-mark"><i data-lucide="cross"></i></span><span>IS Tools<small>ST. JOHN'S HOSPITAL</small></span></a>
        <div class="sidebar-label">WORKSPACE</div>
        <nav class="space-y-1">
            <x-nav href="{{ route('home') }}" icon="layout-dashboard" :active="request()->routeIs('home')">Overview</x-nav>
            <x-nav href="{{ route('forms.index') }}" icon="files" :active="request()->routeIs('forms.*')">Request forms</x-nav>
            @auth
                <x-nav href="{{ route('requests.mine') }}" icon="inbox" :active="request()->routeIs('requests.mine')">My requests</x-nav>
                <x-nav href="{{ route('meetings.index') }}" icon="users-round" :active="request()->routeIs('meetings.index', 'meetings.create', 'meetings.show')">Meetings &amp; teaching</x-nav>
                <x-nav href="{{ route('meetings.report') }}" icon="chart-no-axes-combined" :active="request()->routeIs('meetings.report')">Attendance reports</x-nav>
            @endauth
            <x-nav href="{{ route('apps') }}" icon="grid-2x2" :active="request()->routeIs('apps')">Hospital applications</x-nav>
        </nav>
        @can('admin')<div class="sidebar-label mt-9">IS ADMINISTRATION</div><nav class="space-y-1">
            <x-nav href="{{ route('requests.index') }}" icon="list-checks" :active="request()->routeIs('requests.index', 'requests.show')">All requests</x-nav>
            <x-nav href="{{ route('admin.data') }}" icon="database" :active="request()->routeIs('admin.data')">Reference data</x-nav>
            <x-nav href="{{ route('admin.deliveries') }}" icon="mail" :active="request()->routeIs('admin.deliveries')">Notifications</x-nav>
            <x-nav href="{{ route('admin.documents') }}" icon="archive" :active="request()->routeIs('admin.documents')">Document archive</x-nav>
            <x-nav href="{{ route('office365') }}" icon="panels-top-left" :active="request()->routeIs('office365')">Microsoft 365</x-nav>
        </nav>@endcan
        <div class="mt-auto pt-10"><div class="support-card"><span class="icon-box small"><i data-lucide="headset"></i></span><h3>Need a hand?</h3><p>I.S. Support is here to help.</p><a href="{{ config('is-tools.support_email') ? 'mailto:'.config('is-tools.support_email') : route('about') }}">Contact support <i data-lucide="arrow-up-right"></i></a><small>Extension {{ config('is-tools.support_extension') }}</small></div>
        <a href="{{ route('about') }}" class="sidebar-about">About IS Tools <i data-lucide="info"></i></a></div>
    </aside>
    <div class="app-content"><header class="topbar"><div class="flex items-center gap-3"><button class="icon-button lg:hidden" @click="menu = !menu" aria-label="Toggle navigation" :aria-expanded="menu"><i data-lucide="menu"></i></button><span class="text-slate-400 hidden sm:inline">Workspace</span><span class="text-slate-300 hidden sm:inline">/</span><span class="font-medium">{{ $title }}</span></div><div class="flex items-center gap-4"><span class="internal-badge hidden sm:inline-flex"><span></span> Staff portal</span>@auth<div class="flex items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="hidden xl:block text-sm">{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Sign out" aria-label="Sign out"><i data-lucide="log-out"></i></button></form></div>@else<a href="{{ route('login') }}" class="btn btn-primary compact">Staff sign in <i data-lucide="arrow-right"></i></a>@endauth</div></header>
    <main id="main" class="main-content">
        @if(session('success'))<div class="notice success" role="status"><i data-lucide="circle-check"></i><span>{{ session('success') }}</span></div>@endif
        @if($errors->any())<div class="notice error" role="alert"><i data-lucide="circle-alert"></i><div><strong>Please check the information below.</strong><ul class="list-disc pl-5 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        {{ $slot }}
    </main><footer class="page-footer"><span>© {{ date('Y') }} {{ config('is-tools.company') }}</span><span>Information Systems <span class="mx-2 text-slate-300">·</span> Made for our hospital</span></footer></div>
</div></body></html>
