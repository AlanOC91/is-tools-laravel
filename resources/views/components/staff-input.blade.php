@props(['name', 'label', 'value' => null, 'required' => false, 'id' => null])
@php($inputId = $id ?? $name)
<div x-data="staffSearch" @click.outside="open = false"
    @focusout="if (!$el.contains($event.relatedTarget)) open = false"
    @keydown.escape.stop="open = false; $refs.input.focus()">
    <label for="{{ $inputId }}">{{ $label }} @if($required)<span class="required-mark">*</span>@endif</label>
    <div class="relative">
        <input class="input" type="text" name="{{ $name }}" id="{{ $inputId }}" value="{{ $value }}"
            x-ref="input" @input.debounce.350ms="search($event.target.value)"
            @keydown.arrow-down.prevent="$refs.results?.querySelector('button')?.focus()"
            @if($required) required @endif maxlength="255" autocomplete="off"
            aria-autocomplete="list" :aria-expanded="open" aria-controls="{{ $inputId }}-results"
            placeholder="Type a name to search…">
        <div x-cloak x-show="open" class="autocomplete" x-ref="results" id="{{ $inputId }}-results" role="group" aria-label="Directory search results">
            <template x-for="person in results" :key="person.account_name">
                <button type="button" @click="choose(person)"
                    @keydown.arrow-down.prevent="$el.nextElementSibling?.focus()"
                    @keydown.arrow-up.prevent="$el.previousElementSibling?.focus()">
                    <strong x-text="person.name"></strong>
                    <small x-text="person.account_name"></small>
                </button>
            </template>
            <p x-show="!results.length" x-text="message" role="status"></p>
        </div>
    </div>
    <small class="field-help">You can also enter the full name manually.</small>
</div>
