<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Fees Changed</title>
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
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .fee-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .fee-item:last-child {
            border-bottom: none;
        }
        .fee-label {
            font-weight: 600;
            color: #4b5563;
        }
        .fee-value {
            color: #764ba2;
            font-weight: 700;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">Application Fees Changed</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $account_name ?? 'Account Name' }},</p>
        
        <p>A new application has been created for your account because the fee structure has been updated.</p>
        
        <div class="warning">
            <strong>⚠️ Action Required:</strong> You need to review and confirm these new fees before we can proceed with your application.
        </div>
        
        <p><strong>Application Name:</strong> {{ $application_name ?? 'Application Name' }}</p>
        
        <div class="fee-box">
            <h3 style="margin-top: 0; color: #764ba2;">Updated Fee Structure</h3>
            
            <div class="fee-item">
                <span class="fee-label">Scaling Fee:</span>
                <span class="fee-value">£{{ number_format($scaling_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-item">
                <span class="fee-label">Transaction Fee:</span>
                <span class="fee-value">{{ number_format($transaction_percentage ?? 0, 2) }}% + £{{ number_format($transaction_fixed_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-item">
                <span class="fee-label">Monthly Fee:</span>
                <span class="fee-value">£{{ number_format($monthly_fee ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-item">
                <span class="fee-label">Monthly Minimum:</span>
                <span class="fee-value">£{{ number_format($monthly_minimum ?? 0, 2) }}</span>
            </div>
            
            <div class="fee-item">
                <span class="fee-label">Service Fee:</span>
                <span class="fee-value">£{{ number_format($service_fee ?? 0, 2) }}</span>
            </div>
        </div>
        
        <p style="font-size: 14px; color: #6b7280;">
            <em>This application replaces application: {{ $parent_application_name ?? 'Previous Application' }}</em>
        </p>
        
        <div style="text-align: center;">
            <a href="{{ $status_url ?? '#' }}" class="button">
                Review & Confirm Fees
            </a>
        </div>
        
        <p style="font-size: 14px; color: #6b7280; margin-top: 30px;">
            If you're not logged in, you'll need to sign in to access your application status page.
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated notification from the G2Pay Portal.</p>
    </div>
</body>
</html>