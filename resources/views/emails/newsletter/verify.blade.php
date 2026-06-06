@component('mail::message')
# {{ __('Thank You for Subscribing!') }}

{{ __('Please verify your email by clicking the link below:') }}

@component('mail::button', ['url' => $verificationUrl])
{{ __('Verify Email') }}
@endcomponent

{{ __('If you did not request this, you can safely ignore this email.') }}
@endcomponent
