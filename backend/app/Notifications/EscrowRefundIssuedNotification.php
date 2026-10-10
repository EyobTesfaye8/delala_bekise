<?php

namespace App\Notifications;

use App\Models\EscrowTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EscrowRefundIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public EscrowTransaction $escrow
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database']; // In a real app, maybe add 'sms' channel
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Refund Issued: Job Already Awarded')
            ->line('Your payment for the job "'.$this->escrow->job->title.'" was received.')
            ->line('However, this job was just awarded to another Delala.')
            ->line('A refund for '.number_format($this->escrow->commission_amount_santim / 100, 2).' ETB has been initiated to your payment method.')
            ->action('View Job', url('/jobs/'.$this->escrow->job_id))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'escrow_id' => $this->escrow->id,
            'message' => 'Job already awarded. Refund issued.',
        ];
    }
}
