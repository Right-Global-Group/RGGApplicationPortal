<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application Submission</title>
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
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
        }
        .info-value {
            color: #111827;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
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
        .alert-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .alert-box strong {
            color: #92400e;
        }
        .fee-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .fee-item {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
        }
        .fee-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .fee-value {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 New Application Submission</h1>
    </div>
    
    <div class="content">
        <p>Hello CardStream Team,</p>
        
        <p>A new merchant application has been submitted and is ready for processing.</p>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #667eea;">Application Details</h3>
            
            <div class="info-row">
                <span class="info-label">Application Name:</span>
                <span class="info-value">{{ $application_name }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Account Name:</span>
                <span class="info-value">{{ $account_name }}</span>
            </div>

            @if($trading_name && $trading_name !== 'N/A')
            <div class="info-row">
                <span class="info-label">Trading Name:</span>
                <span class="info-value">{{ $trading_name }}</span>
            </div>
            @endif
            
            <div class="info-row">
                <span class="info-label">Submitted By:</span>
                <span class="info-value">{{ $submitted_by }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Submission Date:</span>
                <span class="info-value">{{ now()->format('F j, Y \a\t g:i A') }}</span>
            </div>
        </div>

        <div class="info-box">
            <h3 style="margin-top: 0; color: #667eea;">Fee Structure</h3>
            
            <div class="fee-grid">
                <div class="fee-item">
                    <div class="fee-label">Setup Fee</div>
                    <div class="fee-value">£{{ number_format($setup_fee, 2) }}</div>
                </div>
                
                <div class="fee-item">
                    <div class="fee-label">Transaction Fee</div>
                    <div class="fee-value">{{ $transaction_percentage }}% + £{{ number_format($transaction_fixed_fee, 2) }}</div>
                </div>
                
                <div class="fee-item">
                    <div class="fee-label">Monthly Fee</div>
                    <div class="fee-value">£{{ number_format($monthly_fee, 2) }}</div>
                </div>
                
                <div class="fee-item">
                    <div class="fee-label">Monthly Minimum</div>
                    <div class="fee-value">£{{ number_format($monthly_minimum, 2) }}</div>
                </div>
            </div>
        </div>
        
        <div class="alert-box">
            <strong>✓ All Requirements Met:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>All required documents uploaded and approved</li>
                <li>Contract fully signed by all parties</li>
                <li>Application approved by internal team</li>
            </ul>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $contract_url }}" class="button">
                View Signed Contract (DocuSign)
            </a>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <a href="{{ $application_url }}" class="button" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                View Full Application Details
            </a>
        </div>
        
        <p style="margin-top: 30px;">
            Please review the application at your earliest convenience. All required documentation has been completed and verified.
        </p>
        
        <p>
            If you have any questions or need additional information, please don't hesitate to reach out.
        </p>
        
        <p style="margin-top: 30px;">
            Best regards,<br>
            <strong>G2Pay Application Portal</strong>
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated notification from the G2Pay Application Portal.</p>
        <p>© {{ date('Y') }} G2Pay. All rights reserved.</p>
    </div>
</body>
</html>