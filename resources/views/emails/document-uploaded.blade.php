<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Uploaded</title>
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
        .document-box {
            background: white;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
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
        <h1>📄 Document Uploaded</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $user_name }},</p>
        
        <p>A document has been uploaded to an application by {{ $account_name }}.</p>
        
        <div class="document-box">
            <div><span class="label">Application:</span> {{ $application_name }}</div>
            <div><span class="label">Document Type:</span> {{ $document_category }}</div>
            <div><span class="label">Uploaded By:</span> {{ $account_name }}</div>
            <div><span class="label">Uploaded At:</span> {{ $uploaded_at }}</div>
        </div>
        
        <p>You can review the uploaded document and track application progress:</p>
        
        <center>
            <a href="{{ $application_url }}" class="button">View Application</a>
        </center>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>