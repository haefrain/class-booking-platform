<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Support\AcademyTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeatLostAfterPaymentNotification extends Notification
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
            ->subject('Your payment arrived too late — refunded')
            ->line(str_replace([':class', ':when'], [$session->classType->name, $when], 'Your payment for :class on :when arrived after the seat was released. We refunded you in full — the class page shows live availability.'))
            ->action('View class', url('/sessions/'.$session->id));
    }
}
