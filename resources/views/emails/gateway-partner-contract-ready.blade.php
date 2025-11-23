<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Merchant Application Contract</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🤝</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                New Merchant Application
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #60a5fa;">{{ $gateway_partner_name ?? 'Gateway Partner' }}</strong> Team,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                We have a new merchant application that requires your review and signature.
                            </p>

                            <!-- Application Details -->
                            <div style="background-color: #1e293b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #60a5fa;">Merchant Details</h3>
                                <table width="100%" cellpadding="8" cellspacing="0">
                                    <tr>
                                        <td style="color: #94a3b8; font-size: 14px; padding: 8px 0;">Application Name:</td>
                                        <td style="color: #e2e8f0; font-weight: 600; font-size: 14px; padding: 8px 0; text-align: right;">{{ $application_name ?? 'Application Name' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #94a3b8; font-size: 14px; padding: 8px 0; border-top: 1px solid #0f172a;">Trading Name:</td>
                                        <td style="color: #e2e8f0; font-weight: 600; font-size: 14px; padding: 8px 0; text-align: right; border-top: 1px solid #0f172a;">{{ $trading_name ?? 'Trading Name' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #94a3b8; font-size: 14px; padding: 8px 0; border-top: 1px solid #0f172a;">Scaling Fee:</td>
                                        <td style="color: #e2e8f0; font-weight: 600; font-size: 14px; padding: 8px 0; text-align: right; border-top: 1px solid #0f172a;">£{{ number_format($scaling_fee ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #94a3b8; font-size: 14px; padding: 8px 0; border-top: 1px solid #0f172a;">Transaction Fee:</td>
                                        <td style="color: #e2e8f0; font-weight: 600; font-size: 14px; padding: 8px 0; text-align: right; border-top: 1px solid #0f172a;">{{ number_format($transaction_percentage ?? 0, 2) }}% + £{{ number_format($transaction_fixed_fee ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #94a3b8; font-size: 14px; padding: 8px 0; border-top: 1px solid #0f172a;">Monthly Fee:</td>
                                        <td style="color: #e2e8f0; font-weight: 600; font-size: 14px; padding: 8px 0; text-align: right; border-top: 1px solid #0f172a;">£{{ number_format($monthly_fee ?? 0, 2) }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Call to Action -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $signing_url ?? '#' }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);">
                                            Review & Sign Contract
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Next Steps -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    After Signing:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li>Please provide the Merchant ID (MID)</li>
                                    <li>Include API credentials and integration URLs</li>
                                    <li>Reply with any additional setup requirements</li>
                                </ul>
                            </div>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                If you have any questions about this application, please contact us.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px 40px; border-top: 1px solid #1e293b;">
                            <p style="margin: 0; font-size: 14px; color: #64748b; text-align: center;">
                                This is an automated notification from the G2Pay Portal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>