<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
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
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        .content {
            padding: 30px 0;
        }
        .greeting {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }
        .booking-details {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .booking-details h2 {
            color: #667eea;
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            flex: 1;
        }
        .detail-value {
            color: #333;
            flex: 1;
            text-align: right;
        }
        .booking-id {
            background-color: #667eea;
            color: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 20px 0;
            font-size: 16px;
            font-weight: bold;
        }
        .amount-section {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .total-amount {
            font-size: 20px;
            color: #2e7d32;
            font-weight: bold;
            text-align: center;
        }
        .payment-status {
            background-color: #fff3e0;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #ff9800;
        }
        .payment-status p {
            margin: 5px 0;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #eee;
            color: #666;
            font-size: 12px;
        }
        .footer p {
            margin: 5px 0;
        }
        .thank-you {
            text-align: center;
            color: #667eea;
            font-size: 16px;
            margin: 20px 0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>🏨 Booking Confirmed</h1>
            <p>Your reservation has been successfully created</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Dear {{ $booking->customer_name }},</p>
            
            <p>
                Thank you for choosing us! Your booking has been confirmed and we're excited to welcome you.
                Please find your booking details below.
            </p>

            <!-- Booking ID -->
            <div class="booking-id">
                Booking ID: #{{ $booking->id }}
            </div>

            <!-- Guest & Stay Details -->
            <div class="booking-details">
                <h2>✨ Booking Details</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Guest Name:</span>
                    <span class="detail-value">{{ $booking->customer_name }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">{{ $booking->phone ?? 'N/A' }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">{{ $booking->email ?? 'N/A' }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Number of Guests:</span>
                    <span class="detail-value">{{ $booking->no_of_people }}</span>
                </div>
            </div>

            <!-- Room & Dates -->
            <div class="booking-details">
                <h2>🛏️ Room & Stay Information</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Room Number:</span>
                    <span class="detail-value">{{ $booking->room->room_number ?? 'N/A' }}</span>
                </div>
                
                @if($booking->room->room_type)
                <div class="detail-row">
                    <span class="detail-label">Room Type:</span>
                    <span class="detail-value">{{ $booking->room->room_type }}</span>
                </div>
                @endif
                
                <div class="detail-row">
                    <span class="detail-label">Check-in Date:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('d M, Y') }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Check-out Date:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($booking->check_out_date)->format('d M, Y') }}</span>
                </div>
                
                @php
                    $checkIn = \Carbon\Carbon::parse($booking->check_in_date);
                    $checkOut = \Carbon\Carbon::parse($booking->check_out_date);
                    $nights = $checkOut->diffInDays($checkIn);
                @endphp
                
                <div class="detail-row">
                    <span class="detail-label">Number of Nights:</span>
                    <span class="detail-value">{{ $nights }} night(s)</span>
                </div>
            </div>

            <!-- Billing Summary -->
            <div class="booking-details">
                <h2>💰 Billing Summary</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">₹{{ number_format($booking->total_amount, 2) }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Paid Amount:</span>
                    <span class="detail-value">₹{{ number_format($booking->paid_amount, 2) }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Due Amount:</span>
                    <span class="detail-value">₹{{ number_format($booking->due_amount, 2) }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Payment Mode:</span>
                    <span class="detail-value">{{ $booking->mode_of_payment }}</span>
                </div>
            </div>

            <!-- Payment Status -->
            @if($booking->due_amount > 0)
            <div class="payment-status">
                <p><strong>⚠️ Payment Due:</strong></p>
                <p>An amount of ₹{{ number_format($booking->due_amount, 2) }} is due. Please arrange payment before your check-in.</p>
            </div>
            @else
            <div class="amount-section">
                <p style="text-align: center; color: #2e7d32; font-weight: bold;">✅ Payment Complete - Full amount received</p>
            </div>
            @endif

            <!-- Thank You Message -->
            <div class="thank-you">
                We look forward to your arrival!
            </div>

            <p>
                If you have any questions or need to make changes to your booking, please don't hesitate to contact us.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Hotel Management System</strong></p>
            <p>Thank you for choosing our hotel. We hope you have a wonderful stay!</p>
            <p style="color: #999;">This is an automated email. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
