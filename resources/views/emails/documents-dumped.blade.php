<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Documents Removed</title>
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
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .info-box {
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-item {
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        .label {
            font-weight: bold;
            color: #667eea;
        }
        .documents-list {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .document-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-left: 3px solid #dc3545;
            border-radius: 3px;
        }
        .notice-box {
            background: #e6f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 Application Documents Removed</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $user_name ?? 'User' }},</p>
        
        <p>This is an automated notification regarding document retention for the following application:</p>
        
        <div class="info-box">
            <div class="info-item">
                <span class="label">Application Name:</span><br>
                {{ $application_name ?? 'Application Name' }}
            </div>
            <div class="info-item">
                <span class="label">Account:</span><br>
                {{ $account_name ?? 'Account Name' }}
            </div>
            <div class="info-item">
                <span class="label">Approved Date:</span><br>
                {{ $approved_at ?? 'Not Available' }}
            </div>
        </div>
        
        <div class="notice-box">
            <strong>🗓️ Automatic Document Removal Policy</strong>
            <p style="margin: 10px 0 0 0;">As per our data retention policy, application documents are automatically removed <strong>one month after the application reaches approved status</strong>. This helps us maintain compliance with data protection regulations while keeping your application records accessible.</p>
        </div>

        <p><strong>The following documents have been removed:</strong></p>
        
        <div class="documents-list">
            @foreach($dumped_documents ?? [] as $doc)
            <div class="document-item">
                <strong>{{ ucwords(str_replace('_', ' ', $doc['category'])) }}</strong><br>
                <small style="color: #666;">
                    File: {{ $doc['filename'] }}<br>
                    Originally uploaded: {{ $doc['uploaded_at'] }}
                </small>
            </div>
            @endforeach
        </div>
        
        <div class="info-box">
            <p style="margin: 0;"><strong>What this means:</strong></p>
            <ul style="margin: 10px 0;">
                <li>Document files have been permanently deleted from our servers</li>
                <li>Document records (names and dates) remain visible in the system</li>
                <li>A note indicating removal will be displayed where documents were shown</li>
                <li>The application status and history remain unaffected</li>
            </ul>
        </div>

        <p><strong>Removed on:</strong> {{ $dumped_at ?? now()->format('d/m/Y H:i') }}</p>
        
        <p>If you have any questions about this automated process or our data retention policies, please contact our support team.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
            <p>Documents are automatically removed 30 days after application approval as part of our data retention policy.</p>
        </div>
    </div>
</body>
</html>