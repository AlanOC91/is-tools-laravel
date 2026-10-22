<x-layout title="Staff sign in">
    <div class="login-layout">
        <div class="card login-card">
            <img src="{{ asset('images/logo.png') }}" alt="St. John's Hospital" class="login-logo" width="64" height="64">
            <h1>IS Tools</h1>
            <p>St. John's Hospital IT request system</p>
            <h2>Staff sign in</h2>
            <p>Use your hospital network account.</p>

            @if(isset($directoryError))
                <div class="notice error mt-5" role="alert">{{ $directoryError }}</div>
            @endif

            <form method="post" action="{{ route('login') }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label for="username">Hospital account name</label>
                    <input class="input" id="username" name="username" value="{{ old('username') }}" autocomplete="username" placeholder="e.g. jane.smith" required maxlength="100" autofocus>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <label class="flex gap-2 items-center text-sm">
                    <input type="checkbox" name="remember" value="1"> Keep me signed in on this device
                </label>
                <button class="btn btn-primary w-full">Sign in <i data-lucide="arrow-right"></i></button>
            </form>

            <div class="login-note">
                <a class="text-link" href="{{ route('forms.show', 'ad') }}">Request a hospital login</a>
                <p>I.S. Support · Extension {{ config('is-tools.support_extension') }}</p>
            </div>
        </div>
    </div>
</x-layout>
