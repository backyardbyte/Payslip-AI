<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send notification to user based on their preferences.
     */
    public function send(User $user, string $eventType, array $data = []): void
    {
        try {
            // Get user's enabled channels for this event type
            $enabledChannels = NotificationPreference::getPreferencesForEvent($user->id, $eventType);

            if (empty($enabledChannels)) {
                Log::info("No enabled notification channels for user {$user->id} and event {$eventType}");
                return;
            }

            foreach ($enabledChannels as $channel) {
                $this->sendToChannel($user, $eventType, $channel, $data);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send notification: {$e->getMessage()}", [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'data' => $data,
            ]);
        }
    }

    /**
     * Send notification to multiple users.
     */
    public function sendToUsers(array $userIds, string $eventType, array $data = []): void
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->send($user, $eventType, $data);
        }
    }

    /**
     * Send notification to all users with a specific role.
     */
    public function sendToRole(string $roleName, string $eventType, array $data = []): void
    {
        $users = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->get();

        foreach ($users as $user) {
            $this->send($user, $eventType, $data);
        }
    }

    /**
     * Send notification to specific channel.
     */
    private function sendToChannel(User $user, string $eventType, string $channel, array $data): void
    {
        $template = NotificationTemplate::getTemplate($eventType, $channel);

        if (!$template) {
            Log::warning("No template found for event {$eventType} and channel {$channel}");
            return;
        }

        // Merge user data with provided data
        $notificationData = array_merge($data, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);

        $rendered = $template->render($notificationData);

        switch ($channel) {
            case 'in_app':
                $this->sendInAppNotification($user, $eventType, $rendered, $notificationData);
                break;

            case 'email':
                $this->sendEmailNotification($user, $rendered, $notificationData);
                break;

            case 'sms':
                $this->sendSmsNotification($user, $rendered, $notificationData);
                break;

            case 'push':
                $this->sendPushNotification($user, $rendered, $notificationData);
                break;

            default:
                Log::warning("Unknown notification channel: {$channel}");
        }
    }

    /**
     * Send in-app notification.
     */
    private function sendInAppNotification(User $user, string $eventType, array $rendered, array $data): void
    {
        $user->notify(new CustomNotification([
            'type' => $eventType,
            'title' => $rendered['subject'],
            'message' => $rendered['content'],
            'data' => $data,
            'channel' => 'database',
        ]));

        Log::info("In-app notification sent", [
            'user_id' => $user->id,
            'event_type' => $eventType,
        ]);
    }

    /**
     * Send email notification.
     */
    private function sendEmailNotification(User $user, array $rendered, array $data): void
    {
        try {
            Mail::send([], [], function ($message) use ($user, $rendered) {
                $message->to($user->email, $user->name)
                    ->subject($rendered['subject'])
                    ->html($rendered['content']);
            });

            Log::info("Email notification sent", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send email notification: {$e->getMessage()}", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }
    }

    /**
     * Send SMS notification.
     */
    private function sendSmsNotification(User $user, array $rendered, array $data): void
    {
        // SMS implementation would go here
        // This is a placeholder for future SMS integration
        Log::info("SMS notification would be sent", [
            'user_id' => $user->id,
            'message' => $rendered['content'],
        ]);
    }

    /**
     * Send push notification.
     */
    private function sendPushNotification(User $user, array $rendered, array $data): void
    {
        // Push notification implementation would go here
        // This is a placeholder for future push notification integration
        Log::info("Push notification would be sent", [
            'user_id' => $user->id,
            'title' => $rendered['subject'],
            'message' => $rendered['content'],
        ]);
    }

    /**
     * Send batch completion notification.
     */
    public function sendBatchCompleted(User $user, array $batchData): void
    {
        $this->send($user, 'batch_completed', [
            'batch_name' => $batchData['name'],
            'batch_id' => $batchData['batch_id'],
            'total_files' => $batchData['total_files'],
            'successful_files' => $batchData['successful_files'],
            'failed_files' => $batchData['failed_files'],
            'processing_time' => $batchData['processing_time'] ?? 'N/A',
        ]);
    }

    /**
     * Send batch failed notification.
     */
    public function sendBatchFailed(User $user, array $batchData): void
    {
        $this->send($user, 'batch_failed', [
            'batch_name' => $batchData['name'],
            'batch_id' => $batchData['batch_id'],
            'error_message' => $batchData['error_message'] ?? 'Unknown error',
            'processed_files' => $batchData['processed_files'] ?? 0,
            'total_files' => $batchData['total_files'],
        ]);
    }

    /**
     * Send payslip processed notification.
     */
    public function sendPayslipProcessed(User $user, array $payslipData): void
    {
        $this->send($user, 'payslip_processed', [
            'file_name' => $payslipData['name'],
            'processing_time' => $payslipData['processing_time'] ?? 'N/A',
            'extracted_data' => $payslipData['extracted_data'] ?? [],
        ]);
    }

    /**
     * Send payslip failed notification.
     */
    public function sendPayslipFailed(User $user, array $payslipData): void
    {
        $this->send($user, 'payslip_failed', [
            'file_name' => $payslipData['name'],
            'error_message' => $payslipData['error_message'] ?? 'Processing failed',
        ]);
    }

    /**
     * Get user's notification statistics.
     */
    public function getNotificationStats(User $user): array
    {
        $notifications = $user->notifications();

        return [
            'total' => $notifications->count(),
            'unread' => $notifications->whereNull('read_at')->count(),
            'read' => $notifications->whereNotNull('read_at')->count(),
            'recent' => $notifications->latest()->limit(5)->get(),
        ];
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()->find($notificationId);

        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for user.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
} 