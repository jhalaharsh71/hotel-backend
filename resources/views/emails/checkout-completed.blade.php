<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Completed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 16px;
            color: #0f172a;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .info-item {
            background-color: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
        }
        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 16px;
            color: #0f172a;
            font-weight: 600;
        }
        .summary {
            background-color: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            padding: 16px;
        }
        .summary-title {
            font-size: 14px;
            color: #059669;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #d1fae5;
            font-size: 14px;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-item label {
            color: #059669;
        }
        .summary-item value {
            color: #0f172a;
            font-weight: 600;
        }
        .thank-you {
            background-color: #eef2ff;
            border: 1px solid #d1d5f7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .thank-you p {
            margin: 0;
            color: #2563eb;
            font-size: 14px;
            line-height: 1.6;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Checkout Completed</h1>
            <p>Thank you for staying with us!</p>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {{ $customerName }},
            </div>

            <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                Your checkout has been completed successfully. We hope you had a wonderful stay with us!
            </p>

            <div class="thank-you">
                <p>
                    📋 Your booking has been marked as completed. Thank you for choosing our hotel!
                </p>
            </div>

            <div class="section">
                <div class="section-title">Booking Details</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Booking ID</div>
                        <div class="info-value">#{{ $bookingId }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Room Number</div>
                        <div class="info-value">#{{ $roomNumber }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Check-in Date</div>
                        <div class="info-value">{{ \Carbon\Carbon::parse($checkInDate)->format('d M, Y') }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Check-out Date</div>
                        <div class="info-value">{{ \Carbon\Carbon::parse($checkOutDate)->format('d M, Y') }}</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="summary">
                    <div class="summary-title">💰 Payment Summary</div>
                    <div class="summary-item">
                        <label>Total Amount Paid:</label>
                        <value>₹{{ number_format($totalAmountPaid, 2) }}</value>
                    </div>
                </div>
            </div>

            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin-top: 20px;">
                If you have any questions or feedback about your stay, please don't hesitate to reach out to us.
            </p>
        </div>

        <div class="footer">
            <p>🏨 Hotel Management System</p>
            <p>We appreciate your business!</p>
        </div>
    </div>
</body>
</html>
