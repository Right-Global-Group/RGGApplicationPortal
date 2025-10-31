<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Integration Details Needed</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🌐</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                WordPress Integration Ready
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello <strong style="color: #34d399;">{{ $account_name ?? 'Account Name' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Great news! Your application <strong style="color: #e2e8f0;">{{ $application_name ?? 'Application Name' }}</strong> has been approved by our gateway partner, and we're ready to integrate your payment gateway with your WordPress website.
                            </p>

                            <!-- Success Badge -->
                            <div style="text-align: center; margin: 25px 0;">
                                <span style="display: inline-block; background-color: #10b981; color: white; padding: 10px 20px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                                    ✓ Gateway Approved
                                </span>
                            </div>

                            <!-- What We Need -->
                            <div style="background-color: #1e293b; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #34d399; text-transform: uppercase; letter-spacing: 0.5px;">
                                    We Need Your WordPress Details:
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <li><strong>WordPress Website URL</strong> - Your site address</li>
                                    <li><strong>Admin Email</strong> - WordPress admin email address</li>
                                    <li><strong>Admin Username</strong> - WordPress admin username</li>
                                </ul>
                            </div>

                            <!-- Call to Action -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $application_url ?? '#' }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);">
                                            Provide WordPress Details
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Why We Need This -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    Why We Need This:
                                </p>
                                <p style="margin: 0; color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    We'll use these details to configure your payment gateway integration directly on your WordPress site. This ensures your customers can make payments securely through your website.
                                </p>
                            </div>

                            <!-- Security Note -->
                            <div style="background-color: #1e293b; border-radius: 8px; padding: 15px; margin: 25px 0; border: 1px solid #334155;">
                                <p style="margin: 0; color: #94a3b8; font-size: 13px; line-height: 1.6;">
                                    🔒 <strong>Security Note:</strong> We only need admin access temporarily to set up the payment gateway. After integration is complete, you can change your credentials if desired.
                                </p>
                            </div>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                Once we receive your WordPress details, we'll complete the integration within 1-2 business days.
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