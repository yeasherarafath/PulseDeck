<x-mail::message>
# {{ $eventLabel }}

{{ $subjectLine }}

@foreach ($lines as $line)
- {{ $line }}
@endforeach

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
View status page
</x-mail::button>
@endif

Thanks,<br>
{{ setting('app_name', config('app.name')) }}
</x-mail::message>
