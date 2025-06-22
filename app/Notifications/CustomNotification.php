<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $notificationData;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->notificationData = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (isset($this->notificationData['channel'])) {
            switch ($this->notificationData['channel']) {
                case 'database':
                    $channels[] = 'database';
                    break;
                case 'email':
                    $channels[] = 'mail';
                    break;
                case 'sms':
                    // Add SMS channel when implemented
                    break;
                case 'push':
                    // Add push notification channel when implemented
                    break;
            }
        } else {
            $channels[] = 'database'; // Default to database
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->notificationData['title'] ?? 'Notification')
            ->line($this->notificationData['message'] ?? '')
            ->when(isset($this->notificationData['action_url']), function ($mail) {
                return $mail->action(
                    $this->notificationData['action_text'] ?? 'View Details',
                    $this->notificationData['action_url']
                );
            });
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->notificationData['type'] ?? 'general',
            'title' => $this->notificationData['title'] ?? '',
            'message' => $this->notificationData['message'] ?? '',
            'data' => $this->notificationData['data'] ?? [],
            'action_url' => $this->notificationData['action_url'] ?? null,
            'action_text' => $this->notificationData['action_text'] ?? null,
            'icon' => $this->getIcon($this->notificationData['type'] ?? 'general'),
            'priority' => $this->notificationData['priority'] ?? 'normal',
        ];
    }

    /**
     * Get icon based on notification type.
     */
    private function getIcon(string $type): string
    {
        return match ($type) {
            'batch_completed' => 'check-circle',
            'batch_failed' => 'x-circle',
            'batch_cancelled' => 'minus-circle',
            'payslip_processed' => 'file-check',
            'payslip_failed' => 'file-x',
            'system_maintenance' => 'settings',
            'login_alert' => 'shield-alert',
            'password_changed' => 'key',
            'account_locked' => 'lock',
            default => 'bell',
        };
    }
} 