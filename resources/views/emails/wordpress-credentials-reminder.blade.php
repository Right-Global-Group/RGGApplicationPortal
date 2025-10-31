<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder: WordPress Integration Details Needed</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">⏰</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                Reminder: Action Required
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #fbbf24;">{{ $account_name ?? 'Account Name' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                This is a friendly reminder that we're still waiting for your WordPress integration details for <strong style="color: #e2e8f0;">{{ $application_name ?? 'Application Name' }}</strong>.
                            </p>

                            <!-- Reminder Box -->
                            <div style="background-color: #451a03; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #fbbf24;">
                                    ⚠️ Your Integration is On Hold
                                </p>
                                <p style="margin: 0; color: #fde68a; font-size: 14px; line-height: 1.8;">
                                    We can't complete your payment gateway integration until we receive your WordPress details. This is the final step before your account goes live!
                                </p>
                            </div>

                            <!-- What We Need -->
                            <div style="background-color: #1e293b; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.5px;">
                                    We Still Need:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li><strong>WordPress Website URL</strong></li>
                                    <li><strong>WordPress Admin Email</strong></li>
                                    <li><strong>WordPress Admin Username</strong></li>
                                </ul>
                            </div>

                            <!-- Call to Action -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $application_url ?? '#' }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);">
                                            Provide Details Now
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Timeline -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    What Happens Next:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li>You provide your WordPress details (takes 2 minutes)</li>
                                    <li>We integrate your payment gateway (1-2 business days)</li>
                                    <li>Your account goes live and you can start accepting payments!</li>
                                </ul>
                            </div>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                If you're experiencing any issues or have questions, please don't hesitate to contact us.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px 40px; border-top: 1px solid #1e293b;">
                            <p style="margin: 0; font-size: 14px; color: #64748b; text-align: center;">
                                This is an automated reminder from the G2Pay Portal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>