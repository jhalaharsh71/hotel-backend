<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        .header h1 {
            color: #667eea;
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 0;
            text-align: center;
        }
        .greeting {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }
        .otp-section {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin: 30px 0;
        }
        .otp-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
        }
        .otp-expiry {
            font-size: 12px;
            color: #999;
            margin-top: 15px;
        }
        .instructions {
            font-size: 14px;
            color: #666;
            margin: 20px 0;
            line-height: 1.8;
        }
        .security-notice {
            background-color: #fffbea;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
        .footer-link {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>Email Verification</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">
                Hi <strong>{{ $name }}</strong>,
            </p>

            <p class="instructions">
                Thank you for signing up with Hotel Management System. To complete your registration, please verify your email address using the OTP below:
            </p>

            <!-- OTP Section -->
            <div class="otp-section">
                <div class="otp-label">Your Verification Code</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">This code expires in 10 minutes</div>
            </div>

            <p class="instructions">
                Enter this code in the verification field on our website to complete your registration.
            </p>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong>⚠️ Security Notice:</strong> Never share this OTP with anyone. Hotel Management System team will never ask you for this code via email, phone, or SMS.
            </div>

            <p class="instructions">
                If you didn't request this email, please ignore it or <a href="mailto:support@hotelmanagement.com" class="footer-link">contact us</a>.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                © {{ date('Y') }} Hotel Management System. All rights reserved.<br>
                <a href="#" class="footer-link">Privacy Policy</a> | 
                <a href="#" class="footer-link">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
