<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Congratulations! Your Account is Live</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #10b981;">🎉 Congratulations! Your Account is Now Live!</h2>
        <p>Hello {{ $account_name }},</p>
        <p>We're excited to inform you that your payment gateway is now fully integrated and your account is <strong>LIVE</strong>!</p>
        
        <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; border-radius: 12px; margin: 30px 0; text-align: center;">
            <h3 style="margin: 0 0 10px 0; font-size: 28px;">✓ You're All Set!</h3>
            <p style="margin: 0; font-size: 18px;">Start accepting payments now</p>
        </div>

        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Quick Links:</h3>
            <p>
                <a href="https://portal.cardstream.com" style="color: #667eea; text-decoration: none;">→ CardStream Portal</a>
            </p>
            <p>
                <a href="{{ $application_url }}" style="color: #667eea; text-decoration: none;">→ Your Application Dashboard</a>
            </p>
            <p>
                <a href="{{ $wordpress_url }}" style="color: #667eea; text-decoration: none;">→ Your WordPress Site</a>
            </p>
        </div>

        <p>If you need any assistance or have questions, our support team is here to help.</p>
        <p>Thank you for choosing G2Pay!</p>
    </div>
</body>
</html>