<x-layout title="Record a meeting"><a class="back-link" href="{{ route('meetings.index') }}"><i data-lucide="arrow-left"></i> Meetings &amp; teaching</a><x-page-heading title="Record a meeting" description="Capture the session details and everyone who attended."/>
<form method="post" action="{{ route('meetings.store') }}" class="card form-card max-w-4xl">@csrf<div class="form-section-title"><span class="step-number">1</span><div><h2>Session details</h2><p>Enter the presenter's full name as it appears in the directory.</p></div></div><div class="grid sm:grid-cols-2 gap-6"><x-staff-input name="presenter_name" label="Presenter" :value="old('presenter_name')" :required="true"/><x-staff-input name="presenter_name_2" label="Secondary presenter (optional)" :value="old('presenter_name_2')"/><div><label for="presenting_topic">Topic <span class="required-mark">*</span></label><input class="input" id="presenting_topic" name="presenting_topic" required maxlength="255" value="{{ old('presenting_topic') }}"></div><div><label for="meeting_name">Meeting name (optional)</label><input class="input" id="meeting_name" name="meeting_name" maxlength="255" value="{{ old('meeting_name') }}" placeholder="e.g. NCHD teaching"></div><div><label for="created_at">Session date &amp; time</label><input class="input" id="created_at" type="datetime-local" name="created_at" value="{{ old('created_at', now()->format('Y-m-d\TH:i')) }}"><small class="field-help">Ireland local time. Defaults to now.</small></div></div><div class="form-divider"></div><div class="form-section-title"><span class="step-number">2</span><div><h2>Attendees</h2><p>Add at least one staff member. Duplicate names are saved once.</p></div></div>
<div x-data="attendees({{ \Illuminate\Support\Js::from(old('user_name', [''])) }})">
    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="row.id">
            <div class="flex gap-3 items-start">
                <span class="text-xs text-slate-400 pt-10 w-5" x-text="index + 1"></span>
                <div class="flex-1 min-w-0" x-data="staffSearch" @click.outside="open = false"
                    @focusout="if (!$el.contains($event.relatedTarget)) open = false"
                    @keydown.escape.stop="open = false; $refs.input.focus()">
                    <label :for="'attendee-' + row.id">Staff member <span class="required-mark">*</span></label>
                    <div class="relative">
                        <input class="input" :id="'attendee-' + row.id" name="user_name[]" x-model="row.name"
                            x-ref="input" @input.debounce.350ms="search($event.target.value)"
                            @keydown.arrow-down.prevent="$refs.results?.querySelector('button')?.focus()"
                            required maxlength="255" placeholder="Search staff or enter a full name…" autocomplete="off"
                            aria-autocomplete="list" :aria-expanded="open" :aria-controls="'attendee-' + row.id + '-results'">
                        <div class="autocomplete" x-cloak x-show="open" x-ref="results"
                            :id="'attendee-' + row.id + '-results'" role="group" aria-label="Directory search results">
                            <template x-for="person in results" :key="person.account_name">
                                <button type="button" @click="row.name = person.name; open = false; $refs.input.focus()"
                                    @keydown.arrow-down.prevent="$el.nextElementSibling?.focus()"
                                    @keydown.arrow-up.prevent="$el.previousElementSibling?.focus()">
                                    <strong x-text="person.name"></strong>
                                    <small x-text="person.account_name"></small>
                                </button>
                            </template>
                            <p x-show="!results.length" x-text="message" role="status"></p>
                        </div>
                    </div>
                </div>
                <button class="icon-button mt-7" type="button" @click="remove(index)" :disabled="rows.length === 1" :aria-label="'Remove attendee ' + (index + 1)">×</button>
            </div>
        </template>
    </div>
    <button class="btn btn-white mt-5" type="button" @click="add()">+ Add another attendee</button>
</div>
<div class="form-actions"><a class="btn btn-white" href="{{ route('meetings.index') }}">Cancel</a><button class="btn btn-primary">Save meeting <i data-lucide="check"></i></button></div></form></x-layout>
