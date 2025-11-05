<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WordPress Credentials Needed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">WordPress Integration - Credentials Required</h2>
        <p>Hello {{ $account_name }},</p>
        <p>We're ready to integrate the payment gateway with your WordPress website!</p>
        <p>To complete the integration, we need your WordPress admin credentials.</p>
        
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>We need:</strong></p>
            <ul>
                <li>WordPress Site URL</li>
                <li>WordPress Admin Username</li>
                <li>WordPress Admin Password</li>
            </ul>
        </div>

        <p>
            <a href="{{ $application_url }}" style="display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                Enter WordPress Credentials
            </a>
        </p>

        <div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>🔒 Security Note:</strong> Your credentials are stored securely and will only be used for the initial integration setup.</p>
        </div>
    </div>
</body>
</html>