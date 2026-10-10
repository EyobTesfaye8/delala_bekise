<?php

namespace App\Notifications;

use App\Models\EscrowTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EscrowFundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public EscrowTransaction $escrow
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Escrow Funded - Begin Work!')
            ->line('The buyer has funded the escrow for the job "'.$this->escrow->job->title.'".')
            ->line('Amount: '.number_format($this->escrow->commission_amount_santim / 100, 2).' ETB.')
            ->action('View Job', url('/jobs/'.$this->escrow->job_id))
            ->line('You can now safely begin the work.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'escrow_id' => $this->escrow->id,
            'message' => 'Buyer funded '.number_format($this->escrow->commission_amount_santim / 100, 2).' ETB. Begin work.',
        ];
    }
}
