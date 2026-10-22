@props(['slug', 'form'])
@if($form['access'] !== 'admin' || auth()->user()?->can('admin'))
<a href="{{ route('forms.show', $slug) }}" class="service-card group"><div class="flex justify-between items-start"><span class="icon-box {{ $form['access'] === 'guest' ? '' : 'blue' }}"><i data-lucide="{{ $form['icon'] }}"></i></span><span class="access-label">{{ $form['access'] === 'guest' ? 'NO LOGIN NEEDED' : ($form['access'] === 'admin' ? 'IS TEAM' : 'STAFF') }}</span></div><h3>{{ $form['title'] }}</h3><p>{{ $form['description'] }}</p><div class="card-action">Open form <i data-lucide="arrow-right"></i></div></a>
@endif
