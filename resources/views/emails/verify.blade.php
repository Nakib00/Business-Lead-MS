<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #333333;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            text-align: center;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            height: 50px;
            /* Adjust as needed */
            width: auto;
        }

        .headline {
            font-size: 24px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 10px;
        }

        .subheadline {
            font-size: 16px;
            color: #666666;
            margin-bottom: 30px;
        }

        .content {
            text-align: left;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }

        .greeting {
            font-weight: bold;
            margin-bottom: 15px;
        }

        .user-email-box {
            text-align: center;
            margin: 20px 0;
            font-size: 14px;
        }

        .user-email-box strong {
            color: #000000;
        }

        .email-tooltip {
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background-color: #C0FD49;
            color: #3A3E32;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
        }

        .expiry-note {
            text-align: center;
            font-size: 14px;
            color: #666666;
            margin-top: 15px;
        }

        .fallback-link-box {
            background-color: #F5F6F4;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            margin-top: 20px;
            color: #80C002;
            font-size: 14px;
            text-align: left;
        }

        .fallback-link-box a {
            color: #80C002;
            text-decoration: none;
        }

        .footer {
            margin-top: 40px;
            font-size: 14px;
            color: #666666;
            text-align: left;
        }

        .footer-links {
            color: #80C002;
            text-decoration: none;
        }

        .bottom-footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #999999;
        }

        .back-link {
            text-decoration: none;
            color: #666666;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <!-- Logo updated to absolute URL as requested -->
            <img src="https://hubbackend.desklago.com/storage/app/public/DesklaGo%20Hub.svg" alt="DesklaGo Hub">
        </div>

        <div class="headline">Confirm your email to activate your account</div>
        <div class="subheadline">This helps us keep your DesklaGo Hub account secure.</div>

        <div class="content">
            <div class="greeting">Hello {{ $user->name }},</div>

            <p>Welcome to <strong>DesklaGo Hub</strong> 👋<br>
                We're excited to have you on board.</p>

            <p>To activate your account and get full access, please verify your email address by clicking the button below:</p>

            <div class="user-email-box">
                You're verifying: <strong>{{ $user->email }}</strong>
                <!-- Note: The tooltip black box in the design seems to be a UI hint, likely not part of the email content itself, but I will simulate the visual if needed or ignore if it's just a cursor hover state in the screenshot. The user asked to "convert the design", the black box looks like a hover state or a specific UI element. I'll omit the black box 'Securely confirm your email' as it looks like a tooltip from the interaction in the screenshot, not part of the static email. -->
            </div>

            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button" target="_blank">Verify email address</a>
                <div class="expiry-note">This verification link will expire in <strong>24 hours</strong> for security reasons.</div>
            </div>

            <p>If the button doesn't work, copy and paste this link into your browser:</p>

            <div class="fallback-link-box">
                <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
            </div>

            <div class="footer">
                <p>Didn't sign up for DesklaGo Hub?<br>
                    You can safely ignore this email — no action is required.</p>

                <p>Need help? Contact us at<br>
                    <a href="mailto:support@hub.desklago.com" class="footer-links">support@hub.desklago.com</a>
                </p>

                <p>Thanks,<br>
                    <strong>The DesklaGo Hub Team</strong>
                </p>
            </div>
        </div>

        <div class="bottom-footer">
            <a href="#" class="back-link">← Back to DesklaGo Hub</a><br>
            &copy; 2025 DesklaGo Hub. Privacy • Terms • Contact
        </div>
    </div>
</body>

</html>