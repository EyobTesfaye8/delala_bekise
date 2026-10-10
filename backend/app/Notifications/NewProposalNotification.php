<?php

namespace App\Notifications;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProposalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Proposal $proposal
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Proposal Received')
            ->line('You have received a new proposal for your job "'.$this->proposal->job->title.'".')
            ->line('Delala: '.$this->proposal->delala->full_name)
            ->action('View Proposal', url('/proposals/'.$this->proposal->id))
            ->line('Review the proposal and proceed if it meets your needs.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'proposal_id' => $this->proposal->id,
            'message' => 'New proposal from '.$this->proposal->delala->full_name,
        ];
    }
}
