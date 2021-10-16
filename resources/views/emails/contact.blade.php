@component('mail::message')

# Email received from {{ config('app.name') }} Contact page

@if($name)
**Sender name:**  {{ $name }}
@endif

**Sender email:** {{ $email }}

@component('mail::panel')
{!! $message !!}
@endcomponent

@endcomponent
