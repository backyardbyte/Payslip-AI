<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_type',
        'channel',
        'subject',
        'template',
        'variables',
        'active',
    ];

    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get template for specific event and channel.
     */
    public static function getTemplate(string $eventType, string $channel): ?self
    {
        return static::where('event_type', $eventType)
            ->where('channel', $channel)
            ->where('active', true)
            ->first();
    }

    /**
     * Render template with provided data.
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $content = $this->template;

        // Replace variables in template
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value, $subject);
            $content = str_replace($placeholder, $value, $content);
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    /**
     * Get available event types.
     */
    public static function getEventTypes(): array
    {
        return [
            'batch_completed' => 'Batch Processing Completed',
            'batch_failed' => 'Batch Processing Failed',
            'batch_cancelled' => 'Batch Processing Cancelled',
            'payslip_processed' => 'Payslip Processed Successfully',
            'payslip_failed' => 'Payslip Processing Failed',
            'system_maintenance' => 'System Maintenance Notice',
            'login_alert' => 'New Login Alert',
            'password_changed' => 'Password Changed',
            'account_locked' => 'Account Locked',
        ];
    }

    /**
     * Get available channels.
     */
    public static function getChannels(): array
    {
        return [
            'in_app' => 'In-App Notification',
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push Notification',
        ];
    }
} 