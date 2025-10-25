<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Application Has Been Approved</title>
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
        .application-box {
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .application-item {
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        .application-label {
            font-weight: bold;
            color: #667eea;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .note {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 10px;
            margin: 15px 0;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Application Approved!</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $name }},</p>
        
        <p>We are pleased to inform you that your application <strong>{{ $application_name }}</strong> has been approved.</p>
        
        <div class="application-box">
            <div class="application-item">
                <span class="application-label">Application Name:</span><br>
                {{ $application_name }}
            </div>
            <div class="application-item">
                <span class="application-label">Access Your Application:</span><br>
                <a href="{{ $application_url }}">{{ $application_url }}</a>
            </div>
        </div>
        
        <div class="note">
            🎉 Congratulations! You can now log in to view the full details of your approved application.
        </div>
        
        <center>
            <a href="{{ $application_url }}" class="button">View Application</a>
        </center>
        
        <p style="text-align: center; color: #666; font-size: 14px;">
            Or copy this link: <a href="{{ $application_url }}">{{ $application_url }}</a>
        </p>
        
        <p>If you have any questions or need assistance, please contact our support team.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>
