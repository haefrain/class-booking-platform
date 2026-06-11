<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Support\AcademyTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReminderNotification extends Notification
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
            ->subject('Tomorrow: '.$session->classType->name)
            ->line("Quick reminder — your class {$session->classType->name} is tomorrow, {$when}.")
            ->line('Need to free your spot? Cancelling early lets someone on the waitlist join.')
            ->action('View my bookings', url('/my/bookings'));
    }
}
