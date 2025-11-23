<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Application Fees</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f0abfc 0%, #e879f9 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">⏰</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #1e293b; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                Fee Confirmation Required
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #f0abfc;">{{ $account_name }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                This is a reminder that your application <strong style="color: #e2e8f0;">{{ $application_name }}</strong> is awaiting fee structure confirmation.
                            </p>

                            <!-- Fee Structure Box -->
                            <div style="background-color: #1e293b; border-radius: 12px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #f0abfc;">
                                    📋 Fee Structure Summary:
                                </p>
                                <table width="100%" cellpadding="8" cellspacing="0" style="font-size: 14px;">
                                    <tr>
                                        <td style="color: #cbd5e1; padding: 8px 0;">Scaling Fee (+ VAT):</td>
                                        <td style="color: #e2e8f0; text-align: right; font-weight: 600; padding: 8px 0;">£{{ number_format($scaling_fee, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #cbd5e1; padding: 8px 0;">Transaction Fee:</td>
                                        <td style="color: #e2e8f0; text-align: right; font-weight: 600; padding: 8px 0;">{{ $transaction_percentage }}% + £{{ number_format($transaction_fixed_fee, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #cbd5e1; padding: 8px 0;">Monthly Fee:</td>
                                        <td style="color: #e2e8f0; text-align: right; font-weight: 600; padding: 8px 0;">£{{ number_format($monthly_fee, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #cbd5e1; padding: 8px 0;">Monthly Minimum:</td>
                                        <td style="color: #e2e8f0; text-align: right; font-weight: 600; padding: 8px 0;">£{{ number_format($monthly_minimum, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #cbd5e1; padding: 8px 0; border-top: 1px solid #475569;">Service Fee:</td>
                                        <td style="color: #e2e8f0; text-align: right; font-weight: 600; padding: 8px 0; border-top: 1px solid #475569;">£{{ number_format($service_fee, 2) }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Warning Box -->
                            <div style="background-color: #422006; border-left: 4px solid #f97316; border-radius: 8px; padding: 15px; margin: 25px 0;">
                                <p style="margin: 0; font-size: 14px; color: #fdba74;">
                                    <strong>⚠️ Action Required:</strong> Please confirm these fees to proceed with your application. Your application cannot progress until the fees are confirmed.
                                </p>
                            </div>

                            <!-- Call to Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $application_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f0abfc 0%, #e879f9 100%); color: #1e293b; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(240, 171, 252, 0.3);">
                                            Confirm Fees Now
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8; text-align: center;">
                                If you have any questions about these fees, please contact your account manager.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #0f172a; border-top: 1px solid #1e293b; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.6;">
                                This is an automated reminder from the G2Pay Portal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <!-- Tracking Pixel -->
    <img src="{{ $tracking_url }}" width="1" height="1" alt="" style="display:block;border:0;" />
</body>
</html>