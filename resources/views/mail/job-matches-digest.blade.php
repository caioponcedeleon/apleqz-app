<x-mail::message>
# {{ $greeting }}

{{ $intro }}

@foreach ($matches as $match)
<x-mail::panel>
**{{ $match['score'] }}/100** · {{ $match['company'] }}

@if ($match['url'])
[{{ $match['title'] }}]({{ $match['url'] }})
@else
{{ $match['title'] }}
@endif

{{ $match['reason'] }}
</x-mail::panel>

@endforeach

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

{{ $footer }}

{!! nl2br(e($salutation)) !!}

<x-slot:subcopy>
{{ __('notifications.mail.subcopy', ['actionText' => $actionText]) }}
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
