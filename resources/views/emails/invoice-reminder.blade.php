<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">Invoice Creation Reminder</h2>
        <p>Hello,</p>
        <p>This is a reminder to create an invoice for the following application:</p>
        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Application:</strong> {{ $application_name }}</p>
            <p><strong>Account:</strong> {{ $account_name }}</p>
        </div>
        <p>Please create the invoice in Xero and send it to the merchant.</p>
        <p>
            <a href="{{ $application_url }}" style="display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                View Application
            </a>
        </p>
    </div>
</body>
</html>