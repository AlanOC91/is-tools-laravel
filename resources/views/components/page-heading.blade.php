@props(['title', 'description' => ''])
<div class="page-heading"><div><h1>{{ $title }}</h1>@if($description)<p>{{ $description }}</p>@endif</div>@if(isset($actions))<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif</div>
