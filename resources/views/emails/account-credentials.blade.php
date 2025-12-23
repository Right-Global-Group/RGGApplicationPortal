<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Credentials</title>
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
        .credentials-box {
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-item {
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        .credential-label {
            font-weight: bold;
            color: #667eea;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 10px;
            margin: 15px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to Your Account!</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $name }},</p>
        
        <p>Your account has been created successfully. Below are your login credentials:</p>
        
        <div class="credentials-box">
            <div class="credential-item">
                <span class="credential-label">Email:</span><br>
                {{ $email }}
            </div>
            <div class="credential-item">
                <span class="credential-label">Password:</span><br>
                {{ $password }}
            </div>
        </div>
        
        <div class="note">
            <strong>⚠️ Important:</strong> Please keep these credentials secure and change your password after your first login.
        </div>
        
        <center>
            <a href="{{ $login_url }}" class="button">Login to Your Account</a>
        </center>
        
        <p style="text-align: center; color: #666; font-size: 14px;">
            Or copy this link: <a href="{{ $login_url }}">{{ $login_url }}</a>
        </p>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>