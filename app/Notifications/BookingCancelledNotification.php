<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Support\AcademyTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification
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
        // Mail only, deliberately: in-app state is derived from bookings, so
        // it can never go stale (blueprint D10).
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->booking->session;
        $className = $session->classType->name;
        $when = AcademyTime::format($session->starts_at);

        return (new MailMessage)
            ->subject('Booking cancelled')
            ->line(str_replace([':class', ':when'], [$className, $when], 'Your booking for :class on :when was cancelled.'))
            ->action('View class', url('/sessions/'.$session->id));
    }
}
