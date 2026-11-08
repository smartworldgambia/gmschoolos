<?php

namespace App\Notifications;

use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ImportBatch $batch)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Student import '.$this->batch->status)
            ->line("File: {$this->batch->original_filename}")
            ->line("Success: {$this->batch->success_rows}, Failed: {$this->batch->failed_rows}")
            ->action('View imports', url(route('students.import.index')));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Student import '.$this->batch->status,
            'body' => "{$this->batch->success_rows} succeeded, {$this->batch->failed_rows} failed.",
            'action_url' => route('students.import.index'),
            'level' => $this->batch->status === 'completed' ? 'success' : 'warning',
            'import_batch_id' => $this->batch->id,
        ];
    }
}
