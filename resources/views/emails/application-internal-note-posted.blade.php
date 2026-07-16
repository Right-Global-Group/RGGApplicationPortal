<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Note Added to Application</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); border: 2px solid #f59e0b;">

                    <!-- Header — amber, staff-only warning -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🔒</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                Internal Note Added
                            </h1>
                            <p style="margin: 10px 0 0 0; font-size: 14px; font-weight: 700; color: #451a03; text-transform: uppercase; letter-spacing: 1px;">
                                Staff only — do not forward to the merchant
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #fbbf24;">{{ $recipient_name }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                <strong style="color: #e2e8f0;">{{ $sender_name }}</strong> added an <strong style="color: #fbbf24;">internal note</strong> to application: <strong style="color: #e2e8f0;">{{ $application_name }}</strong>
                            </p>

                            @if ($step_label)
                            <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                Posted while the application was at step: <strong style="color: #cbd5e1;">{{ $step_label }}</strong>
                            </p>
                            @endif

                            <!-- Note Box -->
                            <div style="background-color: #1e293b; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.5px;">
                                    🔒 Internal note (not visible to the merchant):
                                </p>
                                <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #e2e8f0; white-space: pre-wrap;">{{ $excerpt }}</p>
                            </div>

                            <!-- Call to Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $thread_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1e293b; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(251, 191, 36, 0.3);">
                                            View Thread
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                From: <strong style="color: #cbd5e1;">{{ $sender_name }}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px 40px; text-align: center; border-top: 1px solid #1e293b;">
                            <p style="margin: 0; font-size: 14px; color: #64748b;">
                                This internal note is visible to staff only. This is an automated notification from the G2Pay Portal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
