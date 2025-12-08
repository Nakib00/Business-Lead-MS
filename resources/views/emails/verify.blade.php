<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f7fa;
            color: #333333;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2b3445;
            font-size: 24px;
            margin: 0;
        }

        .content {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .greeting {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2b3445;
        }

        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #555555;
            margin-bottom: 30px;
        }

        .button-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .button {
            display: inline-block;
            background-color: #000000;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #333333;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #999999;
        }

        .footer a {
            color: #999999;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <!-- You can replace this title with an <img> tag for your logo -->
            <h1>Hub.desklago</h1>
        </div>

        <div class="content">
            <div class="greeting">Hello {{ $user->name }},</div>

            <div class="message">
                Welcome to <strong>Hub.desklago</strong>! We are excited to have you on board.<br><br>
                Please verify your email address to unlock full access to your account and start your journey with us.
            </div>

            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button" target="_blank">Verify Email Address</a>
            </div>

            <div class="message" style="margin-bottom: 0;">
                If you did not create an account, no further action is required.
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Hub.desklago. All rights reserved.<br>
        </div>
    </div>
</body>

</html>