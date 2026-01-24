<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckoutCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Checkout Completed Successfully - Booking #' . $this->booking->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.checkout-completed',
            with: [
                'booking' => $this->booking,
                'customerName' => $this->booking->customer_name,
                'bookingId' => $this->booking->id,
                'roomNumber' => $this->booking->room?->room_number,
                'checkInDate' => $this->booking->check_in_date,
                'checkOutDate' => $this->booking->check_out_date,
                'totalAmountPaid' => $this->booking->paid_amount,
            ],
        );
    }
}
