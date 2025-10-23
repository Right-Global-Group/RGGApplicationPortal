<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application Created</title>
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
        .label {
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
    </style>
</head>
<body>
    <div class="header">
        <h1>New Application Created</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $account_name }},</p>
        
        <p>A new application has been created for your account:</p>
        
        <div class="application-box">
            <div class="application-item">
                <span class="label">Application Name:</span><br>
                {{ $application_name }}
            </div>
            <div class="application-item">
                <span class="label">Created At:</span><br>
                {{ $created_at }}
            </div>
        </div>
        
        <p>You can track the progress and view all details of your application by clicking the button below:</p>
        
        <center>
            <a href="{{ $status_url }}" class="button">View Application Status</a>
        </center>
        
        <p>We will keep you updated as your application progresses through each stage. If you have any questions, please contact our support team.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>