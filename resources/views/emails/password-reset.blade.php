@component('mail::message')
# Reset Your Password

Hi **{{ $userName }}**,

We received a request to reset the password for your {{ $appName }} account.

To set up a new password, please click the button below:

@component('mail::button', ['url' => $url, 'color' => 'primary'])
Reset My Password
@endcomponent

<div style="margin: 30px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; border-left: 4px solid #0038A7;">
    <strong>Security Notice:</strong><br>
    • This link will expire in <strong>60 minutes</strong><br>
    • If you didn't request this reset, please ignore this email<br>
    • Your password will remain unchanged unless you use this link
</div>

For your security, never share your password or reset links with anyone.

---

Best regards,<br>
**{{ config('app.name') }} Team**
@endcomponent
