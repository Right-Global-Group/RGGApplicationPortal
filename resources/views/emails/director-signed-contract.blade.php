<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Ready for Your Signature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .alert-box {
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .alert-box strong {
            color: #065f46;
            font-size: 18px;
        }
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
        }
        .button:hover {
            opacity: 0.9;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Director has now signed Contract</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $account_name }},</p>
        
        <div class="alert-box">
            <strong>Your contract has been reviewed and approved by our Product Manager.</strong>
            <p style="margin: 10px 0 0 0; color: #065f46;">
                It has now also been signed by the G2Pay Director.
            </p>
        </div>
        
        <p>
            Your application <strong>{{ $application_name }}</strong> has successfully passed our internal review process.
            
        </p>

        <div class="info-box">
            <h3 style="margin-top: 0; color: #667eea;">Next Steps:</h3>
            <ol style="margin: 10px 0; padding-left: 20px; color: #4b5563;">
                <li>Click the button below to review and sign the contract</li>
                <li>The signing process takes approximately 2-3 minutes</li>
                <li>Once signed, we'll immediately begin processing your application</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $signing_url }}" class="button">
                📝 Sign Contract Now
            </a>
        </div>

        <p style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <strong style="color: #92400e;">⏰ Time-Sensitive:</strong><br>
            <span style="color: #78350f;">Please sign within the next 48 hours to avoid any delays in processing your application.</span>
        </p>
        
        <p>
            If you have any questions before signing, please don't hesitate to reach out to your account manager or reply to this email.
        </p>

        <p style="margin-top: 30px;">
            You can also view your full application status at any time:
        </p>

        <div style="text-align: center;">
            <a href="{{ $application_url }}" style="color: #667eea; text-decoration: none; font-weight: bold;">
                View Application Status →
            </a>
        </div>
        
        <p style="margin-top: 30px;">
            Best regards,<br>
            <strong>G2Pay Onboarding Team</strong>
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated notification from the G2Pay Application Portal.</p>
        <p>© {{ date('Y') }} G2Pay. All rights reserved.</p>
    </div>
</body>
</html>