<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<div style="text-align: center;">
<img src="{{ url('/images/logo.svg') }}" width="160" alt="{{ config('app.name') }}" style="display: block; margin: 0 auto; max-height: 48px; width: auto;">
<p style="margin: 8px 0 0; font-size: 16px; font-weight: bold; color: #18181b;">{{ config('app.name') }}</p>
</div>
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('notifications.mail.rights_reserved') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
