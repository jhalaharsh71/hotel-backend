<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $bookingService;

    public function __construct(Booking $booking, BookingService $bookingService)
    {
        $this->booking = $booking;
        $this->bookingService = $bookingService;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Service Added to Your Booking #' . $this->booking->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.service-added',
            with: [
                'booking' => $this->booking,
                'bookingService' => $this->bookingService,
                'customerName' => $this->booking->customer_name,
                'bookingId' => $this->booking->id,
                'serviceName' => $this->bookingService->service?->name,
                'servicePrice' => $this->bookingService->unit_price,
                'quantity' => $this->bookingService->quantity,
                'totalPrice' => $this->bookingService->total_price,
                'updatedTotalAmount' => $this->booking->total_amount,
            ],
        );
    }
}
