<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Your Documents</title>
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
        .success-icon {
            font-size: 48px;
            text-align: center;
            margin: 20px 0;
        }
        .info-box {
            background: white;
            border-left: 4px solid #667eea;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white !important;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background: #5568d3;
        }
        .next-steps {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .next-steps h3 {
            color: #1976d2;
            margin-top: 0;
        }
        .next-steps ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 8px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .highlight {
            color: #667eea;
            font-weight: bold;
        }
        .login-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Please Upload Documents</h1>
    </div>
    
    <div class="content">
        <div class="success-icon">🎉</div>
        
        <p>Hello {{ $account_name }},</p>
        
        <div class="login-warning">
            ⚠️ <strong>IMPORTANT:</strong> You must <a href="{{ $login_url }}" style="color: #667eea; font-weight: bold;">log in to your account</a> first before uploading documents. If you're not logged in, you'll get an access denied error.
        </div>
                
        <div class="info-box">
            <h3 style="margin-top: 0; color: #667eea;">An application step is awaiting your completion:</h3>
            <p>To continue processing your application, please upload the required documents. This is a crucial step in completing your merchant account setup.</p>
        </div>
        
        <div class="next-steps">
            <h3>📋 Required Documents</h3>
            <p>You'll need to upload the following:</p>
            <ul>
                <li>Proof of Identity (for all directors/shareholders)</li>
                <li>Proof of Address (for all directors/shareholders)</li>
                <li>Bank Statement</li>
                <li>Proof of Business Address</li>
                <li>Any additional documents requested</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <p style="font-size: 16px; font-weight: bold; color: #667eea; margin-bottom: 15px;">Step 1: Log In First</p>
            <a href="{{ $login_url }}" class="button">Login to Your Account</a>
            <p style="color: #666; font-size: 12px; margin-top: 10px;">
                Login URL: <a href="{{ $login_url }}">{{ $login_url }}</a>
            </p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <p style="font-size: 16px; font-weight: bold; color: #667eea; margin-bottom: 15px;">Step 2: Upload Documents</p>
            <a href="{{ $upload_url }}" class="button">Upload Documents Now</a>
            <p style="color: #666; font-size: 12px; margin-top: 10px;">
                Upload URL: <a href="{{ $upload_url }}">{{ $upload_url }}</a>
            </p>
        </div>
        
        <p style="margin-top: 30px;">Once all documents are uploaded and approved, we'll move forward with the application and inform you of the next steps required.</p>
        
        <p>If you have any questions or need assistance with the document upload process, please don't hesitate to contact our support team.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
            <p>Application: {{ $application_name }}</p>
        </div>
    </div>
</body>
</html>