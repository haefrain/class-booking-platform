<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Support\AcademyTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentAutoRefundedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->booking->session;
        $when = AcademyTime::format($session->starts_at);

        return (new MailMessage)
            ->subject('Payment received after cancellation — refunded')
            ->line(str_replace([':class', ':when'], [$session->classType->name, $when], 'We received your payment for :class on :when after the booking was cancelled, so we refunded it in full automatically.'))
            ->action('View class', url('/sessions/'.$session->id));
    }
}
