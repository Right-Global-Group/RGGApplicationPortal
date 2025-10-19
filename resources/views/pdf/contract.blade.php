<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merchant Application Contract</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #4a5568;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2d3748;
            margin: 0;
            font-size: 28px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            font-size: 20px;
        }
        .info-row {
            margin: 10px 0;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .signature-section {
            margin-top: 80px;
            page-break-inside: avoid;
        }
        .signature-box {
            border: 1px solid #cbd5e0;
            padding: 20px;
            margin: 20px 0;
            background-color: #f7fafc;
        }
        .terms {
            font-size: 12px;
            color: #4a5568;
        }
        .terms li {
            margin: 8px 0;
        }
        .highlight {
            background-color: #fef5e7;
            padding: 15px;
            border-left: 4px solid #f39c12;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MERCHANT APPLICATION CONTRACT</h1>
        <p style="color: #718096; font-size: 14px;">G2Pay Payment Solutions</p>
    </div>

    <div class="section">
        <h2>Merchant Information</h2>
        <div class="info-row">
            <span class="info-label">Business Name:</span>
            <span>{{ $application->name }}</span>
        </div>
        @if($application->trading_name)
        <div class="info-row">
            <span class="info-label">Trading Name:</span>
            <span>{{ $application->trading_name }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span>{{ $application->email }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Phone:</span>
            <span>{{ $application->phone }}</span>
        </div>
        @if($application->address)
        <div class="info-row">
            <span class="info-label">Address:</span>
            <span>{{ $application->address }}, {{ $application->city }}, {{ $application->postal_code }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Application Date:</span>
            <span>{{ $application->created_at->format('F d, Y') }}</span>
        </div>
    </div>

    <div class="section">
        <h2>Agreement Overview</h2>
        <p>This Merchant Application Contract ("Agreement") is entered into between G2Pay Payment Solutions ("Provider") and {{ $application->name }} ("Merchant") for the provision of payment processing services.</p>
        
        <div class="highlight">
            <strong>Important:</strong> By signing this contract, you agree to the terms and conditions outlined in this document and authorize G2Pay to provide payment gateway services for your business.
        </div>
    </div>

    <div class="section">
        <h2>Terms and Conditions</h2>
        <ul class="terms">
            <li><strong>Service Description:</strong> Provider agrees to furnish payment processing services including but not limited to credit card processing, debit card processing, and online payment gateway integration.</li>
            
            <li><strong>Merchant Obligations:</strong> Merchant agrees to:
                <ul>
                    <li>Provide accurate business information and documentation</li>
                    <li>Comply with all applicable card network rules and regulations</li>
                    <li>Maintain PCI-DSS compliance standards</li>
                    <li>Notify Provider of any business changes within 30 days</li>
                </ul>
            </li>
            
            <li><strong>Fees and Charges:</strong> Merchant agrees to pay the fees as outlined in the pricing schedule provided separately. All fees are subject to change with 30 days written notice.</li>
            
            <li><strong>Term and Termination:</strong> This agreement shall remain in effect until terminated by either party with 30 days written notice. Provider reserves the right to terminate immediately for breach of terms.</li>
            
            <li><strong>Data Protection:</strong> Both parties agree to comply with GDPR and all applicable data protection regulations. Merchant data will be handled in accordance with our Privacy Policy.</li>
            
            <li><strong>Liability:</strong> Provider's liability is limited to direct damages not exceeding the fees paid by Merchant in the preceding 12 months. Provider is not liable for indirect, consequential, or punitive damages.</li>
            
            <li><strong>Chargebacks:</strong> Merchant is responsible for all chargebacks and associated fees. Provider may hold reserves to cover potential chargeback liability.</li>
            
            <li><strong>Dispute Resolution:</strong> Any disputes arising from this agreement shall be resolved through arbitration in accordance with UK law.</li>
        </ul>
    </div>

    <div class="section">
        <h2>Compliance and Security</h2>
        <p>Merchant acknowledges and agrees to:</p>
        <ul class="terms">
            <li>Maintain PCI-DSS compliance at all times</li>
            <li>Implement reasonable security measures to protect cardholder data</li>
            <li>Report any security breaches within 24 hours of discovery</li>
            <li>Undergo periodic security assessments as required</li>
        </ul>
    </div>

    <div class="signature-section">
        <h2>Signatures</h2>
        
        <div class="signature-box">
            <p><strong>Merchant Representative</strong></p>
            <p>Name: ________________________________</p>
            <p style="margin-top: 40px;">Signature: ________________________________</p>
            <p>Date: ________________________________</p>
        </div>

        <div class="signature-box" style="margin-top: 40px;">
            <p><strong>For G2Pay Payment Solutions</strong></p>
            <p>Name: ________________________________</p>
            <p style="margin-top: 40px;">Signature: ________________________________</p>
            <p>Date: ________________________________</p>
        </div>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #718096;">
        <p><strong>G2Pay Payment Solutions</strong></p>
        <p>123 Business Street, London, UK | support@g2pay.co.uk | www.g2pay.co.uk</p>
        <p>© {{ date('Y') }} G2Pay Payment Solutions. All rights reserved.</p>
    </div>
</body>
</html>