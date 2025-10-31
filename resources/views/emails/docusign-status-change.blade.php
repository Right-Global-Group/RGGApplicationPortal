<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocuSign Status Update</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0f172a; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 40px 30px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                DocuSign Status Update
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                Hello,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                There has been an update to the contract status for <strong style="color: #e2e8f0;">{{ $application_name }}</strong>
                            </p>

                            <!-- Status Update Box -->
                            <div style="background-color: #1e293b; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Status Update:
                                </p>
                                <p style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #e2e8f0;">
                                    {{ ucfirst($status) }}
                                </p>
                                <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #cbd5e1;">
                                    {{ $status_message }} 
                                </p>
                            </div>

                            <!-- Details -->
                            <div style="background-color: #0f172a; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #94a3b8;">
                                    Details:
                                </p>
                                <div style="color: #cbd5e1; font-size: 14px; line-height: 1.8;">
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: #e2e8f0;">Application:</strong> {{ $application_name }}
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: #e2e8f0;">Account:</strong> {{ $account_name }}
                                    </div>
                                    <div>
                                        <strong style="color: #e2e8f0;">Time:</strong> {{ $timestamp }}
                                    </div>
                                </div>
                            </div>

                            <!-- Call to Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $application_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);">
                                            View Application Status
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                                This is an automated notification to keep you informed of contract progress.
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