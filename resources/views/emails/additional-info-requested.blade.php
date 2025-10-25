<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Additional Information Required</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">ℹ️</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                Additional Information Required
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
                                We need some additional information to proceed with your application: <strong style="color: #e2e8f0;">{{ $application_name }}</strong>
                            </p>

                            <!-- Info Request Box -->
                            <div style="background-color: #1e293b; border-left: 4px solid #ef4444; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #f87171; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Information Requested:
                                </p>
                                <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #e2e8f0; white-space: pre-wrap;">{{ $requested_info }}</p>
                            </div>

                            <p style="margin: 25px 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Please provide this information at your earliest convenience to avoid delays in processing your application.
                            </p>

                            <!-- Call to Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $application_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f0abfc 0%, #e879f9 100%); color: #1e293b; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(240, 171, 252, 0.3); transition: all 0.3s;">
                                            View Application & Respond
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Additional Context -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    What happens next:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li>Review the information request carefully</li>
                                    <li>Provide the requested details via your application portal</li>
                                    <li>Our team will review and continue processing</li>
                                    <li>You'll be notified once we proceed to the next step</li>
                                </ul>
                            </div>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                Requested by: <strong style="color: #cbd5e1;">{{ $user_name }}</strong>
                            </p>
                        </td>
                    </tr>

                    <div class="footer">
                        <p>This is an automated notification from the G2Pay Portal.</p>
                    </div>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>