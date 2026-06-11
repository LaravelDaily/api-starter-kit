<x-mail::message>
# Password Reset Code

You are receiving this email because we received a password reset request for your account.

<x-mail::panel>
<div style="text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: bold;">{{ $code }}</div>
</x-mail::panel>

This code will expire in {{ $expiresInMinutes }} minutes.

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
