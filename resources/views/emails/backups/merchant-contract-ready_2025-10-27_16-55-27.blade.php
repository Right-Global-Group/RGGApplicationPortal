<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Please Sign Your Contract</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f0abfc 0%, #e879f9 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📝</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #1e293b; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                Contract Ready for Signature
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #f0abfc;">{{ $account_name ?? 'Account Name' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Your merchant application contract for <strong style="color: #e2e8f0;">{{ $application_name ?? 'Application Name' }}</strong> is ready for your signature.
                            </p>

                            <!-- Info Box -->
                            <div style="background-color: #1e293b; border-left: 4px solid #f0abfc; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #f0abfc; text-transform: uppercase; letter-spacing: 0.5px;">
                                    What You Need To Do:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li>Click the button below to review the contract</li>
                                    <li>Read through the terms carefully</li>
                                    <li>Sign electronically using DocuSign</li>
                                    <li>The process takes just a few minutes</li>
                                </ul>
                            </div>

                            <!-- Call to Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $signing_url ?? '#' }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f0abfc 0%, #e879f9 100%); color: #1e293b; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(240, 171, 252, 0.3); transition: all 0.3s;">
                                            Review & Sign Contract
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Notice -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    Important:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li>This signing link will expire in 30 days</li>
                                    <li>You can save and resume signing later</li>
                                    <li>Once signed, you'll receive a copy via email</li>
                                    <li>Your application will proceed to the next stage automatically</li>
                                </ul>
                            </div>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                If you have any questions or concerns about the contract, please <a href="{{ $application_url ?? '#' }}" style="color: #f0abfc; text-decoration: none;">contact us</a> before signing.
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