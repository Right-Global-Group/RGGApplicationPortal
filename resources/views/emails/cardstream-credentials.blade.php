<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your CardStream Account is Ready</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">🎉 Your CardStream Account is Ready!</h2>
        <p>Hello {{ $account_name }},</p>
        <p>Great news! Your payment has been received and your CardStream payment gateway account is now ready to use.</p>
        
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea;">
            <h3 style="margin-top: 0;">Your CardStream Login Credentials</h3>
            <p><strong>Username:</strong> {{ $username }}</p>
            <p><strong>Password:</strong> {{ $password }}</p>
            <p><strong>Merchant ID:</strong> {{ $merchant_id }}</p>
            <p style="margin-top: 15px;">
                <a href="https://portal.cardstream.com" style="display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                    Login to CardStream
                </a>
            </p>
        </div>

        <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>⚠️ Important:</strong> Please change your password after your first login for security.</p>
        </div>

        <p>If you have any questions about setting up your account, please don't hesitate to contact us.</p>
    </div>
</body>
</html>