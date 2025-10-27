<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Fees Confirmed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        .success-badge {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .fee-details {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .fee-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .fee-row:last-child {
            border-bottom: none;
        }
        .fee-label {
            font-weight: 600;
            color: #6b7280;
        }
        .fee-value {
            font-weight: 700;
            color: #111827;
        }
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Fees Confirmed</h1>
        </div>

        <div class="success-badge">
            Confirmed at {{ $confirmed_at ?? 'Confirmation Date' }}
        </div>

        <p>The fees for <strong>{{ $application_name ?? 'Application Name' }}</strong> have been confirmed by <strong>{{ $account_name ?? 'Account Name' }}</strong>.</p>

        <div class="fee-details">
            <h3 style="margin-top: 0; color: #111827;">Confirmed Fee Structure</h3>
            
            <div class="fee-row">
                <span class="fee-label">Setup Fee (+ VAT)</span>
                <span class="fee-value">£{{ number_format($setup_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-row">
                <span class="fee-label">Transaction Fee</span>
                <span class="fee-value">{{ $transaction_percentage ?? 0 }}% + £{{ number_format($transaction_fixed_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-row">
                <span class="fee-label">Monthly Fee</span>
                <span class="fee-value">£{{ number_format($monthly_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-row">
                <span class="fee-label">Monthly Minimum</span>
                <span class="fee-value">£{{ number_format($monthly_minimum ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-row">
                <span class="fee-label">Service Fee</span>
                <span class="fee-value">£{{ number_format($service_fee ?? 0, 2) }}</span>
            </div>
        </div>

        <div class="info-box">
            <strong>Next Steps:</strong> The application will now proceed to the document upload stage where the merchant will be required to upload specified documents.
            You will be notified when each and all documents have been uploaded.
        </div>

        <p>You can track the progress of this application at any time through your dashboard.</p>

        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>