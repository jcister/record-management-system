@component('mail::message')
# Welcome to {{ config('app.name') }}! 🎉

Hi **{{ $userName }}**,

We're excited to have you on board! Your account has been successfully created by an administrator.

## Your Login Credentials

<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <strong>Email:</strong> <code style="background-color: #e9ecef; padding: 4px 8px; border-radius: 4px;">{{ $email }}</code><br><br>
    <strong>Temporary Password:</strong> <code style="background-color: #fff3cd; padding: 4px 8px; border-radius: 4px; color: #856404;">{{ $temporaryPassword }}</code>
</div>

<div style="margin: 20px 0; padding: 15px; background-color: #cfe2ff; border-radius: 5px; border-left: 4px solid #0038A7;">
    <strong>⚠️ Important Security Notice:</strong><br>
    You will be required to create a new password when you first log in. This ensures your account remains secure.
</div>

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Log In to Your Account
@endcomponent

If you have any questions or need assistance, please don't hesitate to contact your system administrator.

---

Best regards,<br>
**{{ config('app.name') }} Team**
@endcomponent
