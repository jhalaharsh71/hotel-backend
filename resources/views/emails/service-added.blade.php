<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Added</title>
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        .service-detail {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .service-title {
            font-size: 16px;
            color: #92400e;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .service-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #fcd34d;
            font-size: 14px;
        }
        .service-item:last-child {
            border-bottom: none;
        }
        .service-item label {
            color: #92400e;
        }
        .service-item value {
            color: #0f172a;
            font-weight: 600;
        }
        .summary {
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 16px;
        }
        .summary-title {
            font-size: 14px;
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .summary-item label {
            color: #1e40af;
        }
        .summary-item value {
            color: #0f172a;
            font-weight: 600;
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
            <h1>✨ Service Added</h1>
            <p>Your booking has been updated</p>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {{ $customerName }},
            </div>

            <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                A new service has been added to your booking. Here are the details:
            </p>

            <div class="section">
                <div class="section-title">Booking Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Booking ID</div>
                        <div class="info-value">#{{ $bookingId }}</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="service-detail">
                    <div class="service-title">📦 Service Details</div>
                    <div class="service-item">
                        <label>Service Name:</label>
                        <value>{{ $serviceName }}</value>
                    </div>
                    <div class="service-item">
                        <label>Unit Price:</label>
                        <value>₹{{ number_format($servicePrice, 2) }}</value>
                    </div>
                    <div class="service-item">
                        <label>Quantity:</label>
                        <value>{{ $quantity }}</value>
                    </div>
                    <div class="service-item">
                        <label>Total Price:</label>
                        <value>₹{{ number_format($totalPrice, 2) }}</value>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="summary">
                    <div class="summary-title">💰 Updated Booking Total</div>
                    <div class="summary-item">
                        <label>New Total Amount:</label>
                        <value>₹{{ number_format($updatedTotalAmount, 2) }}</value>
                    </div>
                </div>
            </div>

            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin-top: 20px;">
                If you have any questions about the service or need any assistance, please contact us.
            </p>
        </div>

        <div class="footer">
            <p>🏨 Hotel Management System</p>
            <p>Your comfort is our priority</p>
        </div>
    </div>
</body>
</html>
