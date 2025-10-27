<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Documents Uploaded</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .success-badge {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            margin: 10px 0;
            font-weight: bold;
        }
        .documents-list {
            background: white;
            border: 2px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .document-item {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }
        .document-item:last-child {
            border-bottom: none;
        }
        .checkmark {
            color: #10b981;
            font-size: 20px;
            margin-right: 10px;
        }
        .label {
            font-weight: bold;
            color: #059669;
        }
        .button {
            display: inline-block;
            background: #10b981;
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
        <h1>All Documents Submitted</h1>
        <div class="success-badge">Complete</div>
    </div>
    
    <div class="content">
        <p>Hello {{ $user_name ?? 'User Name' }},</p>
        
        <p><strong>Great news!</strong> {{ $account_name ?? 'Account Name' }} has submitted all required documents for the application <strong>{{ $application_name ?? 'Application Name' }}</strong>.</p>
        
        <div class="documents-list">
            <h3 style="margin-top: 0; color: #059669;">Documents Submitted:</h3>
            @if(isset($documents) && is_array($documents))
                @foreach($documents as $doc)
                <div class="document-item">
                    <span class="checkmark">✓</span>
                    <div>
                        <strong>{{ $doc['category'] ?? 'Document Category' }}</strong>
                        @if(isset($doc['count']) && $doc['count'] > 1)
                        <span style="color: #6b7280; font-size: 14px;">({{ $doc['count'] }} files)</span>
                        @endif
                    </div>
                </div>
                @endforeach
            @else
                <div class="document-item">
                    <span class="checkmark">✓</span>
                    <div>
                        <strong>Sample Document Category</strong>
                        <span style="color: #6b7280; font-size: 14px;">(2 files)</span>
                    </div>
                </div>
                <div class="document-item">
                    <span class="checkmark">✓</span>
                    <div>
                        <strong>Another Document Category</strong>
                        <span style="color: #6b7280; font-size: 14px;">(1 file)</span>
                    </div>
                </div>
            @endif
        </div>
        
        <p>The application can now proceed to the next stage. You can review all documents and continue processing:</p>
        
        <center>
            <a href="{{ $application_url ?? '#' }}" class="button">Review Application</a>
        </center>
        
        <p><strong>Next Steps:</strong> Review the submitted documents and proceed with the contract signing process.</p>
        
        <div class="footer">
            <p>This is an automated notification from the G2Pay Portal.</p>
        </div>
    </div>
</body>
</html>