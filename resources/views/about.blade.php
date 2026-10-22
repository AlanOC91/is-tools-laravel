<x-layout title="About IS Tools">
    <x-page-heading title="IS Tools" description="St. John's Hospital IT request system"/>
    <div class="card p-8 max-w-3xl space-y-6 leading-7 text-slate-500">
        <img src="{{ asset('images/logo.png') }}" class="size-20 object-contain" alt="St. John's Hospital" width="80" height="80">
        <p>IS Tools handles IT account requests, equipment and software access, staff changes, and meeting attendance for St. John's Hospital.</p>
        <h2 class="text-lg font-semibold">Account requests</h2>
        <p>Hospital login, NIMIS and iLab accounts can be requested without signing in. Other forms, meeting records and attendance reports require a hospital network account.</p>
        <h2 class="text-lg font-semibold">I.S. Support</h2>
        <p>Extension <strong>{{ config('is-tools.support_extension') }}</strong>.@if(config('is-tools.support_email')) <a class="text-link" href="mailto:{{ config('is-tools.support_email') }}">Email support</a>.@endif</p>
        <a class="text-link" href="{{ route('wellbeing') }}">Health &amp; wellbeing →</a>
    </div>
</x-layout>
