<x-mail::message>
@php($mailLogo = branding_asset(setting('logo_path')))
@if ($mailLogo)
<div style="text-align: center; margin-bottom: 16px;"><img src="{{ $mailLogo }}" alt="{{ setting('app_name', config('app.name')) }}" style="height: 40px; max-width: 220px;" /></div>
@endif
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
