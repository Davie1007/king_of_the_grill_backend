@component('mail::message')
# Welcome to {{ config('app.name') }}!

Hello {{ $user->name ?? 'Valued Employee' }},

We’re thrilled to welcome you to the {{ config('app.name') }} team! Your account has been successfully created, and you’re ready to get started.

## Your Account Details
@component('mail::panel')
**Username**: {{ $user->username }}
**Temporary Password**: {{ $plainPassword }}
@endcomponent

Please log in to your account and change your temporary password as soon as possible for security.

@component('mail::button', ['url' => 'https://kingofthegrill.co.ke/#/forgot-password', 'color' => 'primary'])
Reset Your Password
@endcomponent

If you have any questions or need assistance, feel free to reach out to our support team at [support@{{ config('app.name') | lower }}.com](mailto:support@{{ config('app.name') | lower }}.com).

Thanks for joining us,  
The {{ config('app.name') }} Team

@component('mail::subcopy')
For security, please do not share your temporary password with anyone. If you didn’t request this account, contact us immediately.
@endcomponent

@endcomponent