# Notification System Documentation

## Overview

The Notification System is a comprehensive multi-channel notification solution for the Payslip-AI application that enables real-time communication with users through various channels including in-app notifications, email, SMS, and push notifications.

## Features

### ✅ **Core Features Implemented**

1. **Multi-Channel Notifications**
   - In-app notifications with real-time updates
   - Email notifications with HTML templates
   - SMS notifications (placeholder for future integration)
   - Push notifications (placeholder for future integration)

2. **User Preferences Management**
   - Granular control over notification types and channels
   - Per-event type configuration
   - Quick action presets (Enable All, Essential Only, Disable All)
   - Real-time preference updates

3. **Template System**
   - Dynamic template rendering with variables
   - Channel-specific templates
   - HTML email templates with styling
   - Template management and versioning

4. **Real-time Notifications**
   - Live notification bell with unread count
   - Auto-polling for new notifications
   - Mark as read functionality
   - Notification history and management

5. **Event Integration**
   - Batch processing notifications
   - Payslip processing notifications
   - System maintenance alerts
   - Security notifications (login alerts, password changes)

## Database Schema

### Tables Created

1. **`notifications`** (Laravel's default notification table)
   - Stores all in-app notifications
   - UUID primary key
   - Polymorphic relationship to users
   - Read/unread status tracking

2. **`notification_preferences`**
   - User-specific notification preferences
   - Channel and event type combinations
   - Enable/disable flags
   - Additional settings per channel

3. **`notification_templates`**
   - Template definitions for different events and channels
   - Variable placeholders for dynamic content
   - Active/inactive status
   - Subject and content templates

## API Endpoints

### Notification Management

```
GET    /api/notifications/                    # List user's notifications
GET    /api/notifications/recent              # Get recent notifications for header
GET    /api/notifications/stats               # Get notification statistics
POST   /api/notifications/{id}/read           # Mark notification as read
POST   /api/notifications/mark-all-read       # Mark all notifications as read
DELETE /api/notifications/{id}                # Delete notification
```

### Preference Management

```
GET    /api/notifications/preferences         # Get user's preferences
POST   /api/notifications/preferences         # Update user's preferences
```

### Development/Testing

```
POST   /api/notifications/test                # Send test notification (local only)
```

### Request Examples

#### Get Recent Notifications
```javascript
fetch('/api/notifications/recent')
.then(response => response.json())
.then(data => {
    console.log('Notifications:', data.data.notifications)
    console.log('Unread count:', data.data.unread_count)
})
```

#### Update Preferences
```javascript
fetch('/api/notifications/preferences', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        preferences: [
            {
                event_type: 'batch_completed',
                channel: 'email',
                enabled: true
            },
            {
                event_type: 'batch_completed',
                channel: 'in_app',
                enabled: true
            }
        ]
    })
})
```

## Frontend Components

### 1. NotificationBell Component
- **Location**: `resources/js/components/NotificationBell.vue`
- **Features**:
  - Real-time notification bell with unread count
  - Dropdown with recent notifications
  - Mark as read functionality
  - Auto-refresh every 30 seconds

### 2. NotificationPreferences Page
- **Location**: `resources/js/pages/NotificationPreferences.vue`
- **Features**:
  - Comprehensive preference management
  - Event type and channel matrix
  - Quick action presets
  - Real-time preference updates

## Backend Components

### 1. NotificationService
- **Location**: `app/Services/NotificationService.php`
- **Features**:
  - Multi-channel notification sending
  - Template rendering with variables
  - User preference checking
  - Batch and individual notification methods

### 2. Models

#### NotificationPreference Model
- **Location**: `app/Models/NotificationPreference.php`
- **Features**:
  - User preference management
  - Default preference setup
  - Preference checking methods

#### NotificationTemplate Model
- **Location**: `app/Models/NotificationTemplate.php`
- **Features**:
  - Template management
  - Variable rendering
  - Channel-specific templates

### 3. CustomNotification Class
- **Location**: `app/Notifications/CustomNotification.php`
- **Features**:
  - Laravel notification implementation
  - Multi-channel support
  - Queue integration

## Event Types

### Batch Processing Events
- **batch_completed**: When batch processing completes successfully
- **batch_failed**: When batch processing fails
- **batch_cancelled**: When batch processing is cancelled

### Payslip Processing Events
- **payslip_processed**: When individual payslips are processed successfully
- **payslip_failed**: When payslip processing fails

### System Events
- **system_maintenance**: System maintenance announcements
- **login_alert**: New login attempts to user account
- **password_changed**: When user password is changed
- **account_locked**: When user account is locked

## Notification Channels

### In-App Notifications
- Real-time notifications in the application
- Persistent until marked as read
- Icon-based visual indicators
- Action URLs for navigation

### Email Notifications
- HTML formatted emails
- Professional templates with branding
- Variable substitution
- Automatic fallback for failed sends

### SMS Notifications (Future)
- Text message notifications
- Character limit optimization
- Delivery confirmation
- International number support

### Push Notifications (Future)
- Browser push notifications
- Mobile app notifications
- Rich content support
- Delivery tracking

## Template System

### Template Variables

Common variables available in all templates:
- `{{user_name}}` - User's display name
- `{{user_email}}` - User's email address
- `{{app_name}}` - Application name
- `{{app_url}}` - Application URL
- `{{timestamp}}` - Current timestamp

Event-specific variables:
- **Batch events**: `{{batch_name}}`, `{{batch_id}}`, `{{total_files}}`, `{{successful_files}}`, `{{failed_files}}`, `{{processing_time}}`
- **Payslip events**: `{{file_name}}`, `{{processing_time}}`, `{{error_message}}`
- **System events**: `{{maintenance_start}}`, `{{maintenance_end}}`, `{{maintenance_reason}}`
- **Security events**: `{{login_location}}`, `{{login_time}}`, `{{login_ip}}`, `{{login_device}}`

### Template Examples

#### Batch Completed Email Template
```html
<h2>Batch Processing Completed</h2>
<p>Hello {{user_name}},</p>
<p>Your batch processing operation has been completed successfully.</p>

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3>Batch Details:</h3>
    <ul>
        <li><strong>Batch Name:</strong> {{batch_name}}</li>
        <li><strong>Total Files:</strong> {{total_files}}</li>
        <li><strong>Successfully Processed:</strong> {{successful_files}}</li>
        <li><strong>Failed:</strong> {{failed_files}}</li>
        <li><strong>Processing Time:</strong> {{processing_time}}</li>
    </ul>
</div>

<p>You can view the detailed results in your dashboard.</p>
<p>Best regards,<br>{{app_name}} Team</p>
```

## Integration with Batch Processing

### Automatic Notifications

The notification system is fully integrated with the batch processing system:

1. **Batch Completion**: Automatically sends notifications when batches complete
2. **Batch Failure**: Sends immediate notifications when batches fail
3. **Progress Updates**: Can be configured for milestone notifications
4. **Error Reporting**: Detailed error information in failure notifications

### Usage in Jobs

```php
// In ProcessBatch job
private function sendCompletionNotification(): void
{
    $notificationService = app(NotificationService::class);
    $user = $this->batchOperation->user;
    
    $notificationService->sendBatchCompleted($user, [
        'name' => $this->batchOperation->name,
        'batch_id' => $this->batchOperation->batch_id,
        'total_files' => $this->batchOperation->total_files,
        'successful_files' => $this->batchOperation->successful_files,
        'failed_files' => $this->batchOperation->failed_files,
        'processing_time' => $processingTime,
    ]);
}
```

## Configuration

### Default Preferences

When a user is created, default notification preferences are automatically set:

```php
// Essential notifications enabled by default
$defaultPreferences = [
    ['channel' => 'in_app', 'event_type' => 'batch_completed'],
    ['channel' => 'email', 'event_type' => 'batch_completed'],
    ['channel' => 'in_app', 'event_type' => 'batch_failed'],
    ['channel' => 'email', 'event_type' => 'batch_failed'],
    ['channel' => 'in_app', 'event_type' => 'payslip_failed'],
    ['channel' => 'email', 'event_type' => 'payslip_failed'],
    ['channel' => 'in_app', 'event_type' => 'system_maintenance'],
    ['channel' => 'email', 'event_type' => 'system_maintenance'],
    ['channel' => 'in_app', 'event_type' => 'login_alert'],
    ['channel' => 'email', 'event_type' => 'login_alert'],
];
```

### Email Configuration

Ensure your Laravel mail configuration is properly set up in `config/mail.php`:

```php
'default' => env('MAIL_MAILER', 'smtp'),

'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
],
```

## Usage Examples

### 1. Send Custom Notification

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);
$user = User::find(1);

$notificationService->send($user, 'batch_completed', [
    'batch_name' => 'Monthly Reports',
    'total_files' => 25,
    'successful_files' => 23,
    'failed_files' => 2,
]);
```

### 2. Send to Multiple Users

```php
$notificationService->sendToUsers([1, 2, 3], 'system_maintenance', [
    'maintenance_start' => '2025-01-22 02:00:00',
    'maintenance_end' => '2025-01-22 04:00:00',
    'maintenance_reason' => 'Database optimization',
]);
```

### 3. Send to Role

```php
$notificationService->sendToRole('admin', 'system_maintenance', [
    'maintenance_start' => '2025-01-22 02:00:00',
    'maintenance_end' => '2025-01-22 04:00:00',
]);
```

### 4. Check User Preferences

```php
$enabled = NotificationPreference::isEnabled(1, 'email', 'batch_completed');
if ($enabled) {
    // Send email notification
}
```

## Database Seeding

### Notification Templates Seeder

Run the seeder to populate default notification templates:

```bash
php artisan db:seed --class=NotificationTemplatesSeeder
```

### User Default Preferences

Default preferences are automatically created when users are registered. To set defaults for existing users:

```php
foreach (User::all() as $user) {
    NotificationPreference::setDefaults($user->id);
}
```

## Performance Considerations

### 1. Queue Integration
- All notifications are queued for background processing
- Prevents blocking of main application flow
- Configurable retry mechanisms

### 2. Caching
- User preferences are cached for 5 minutes
- Template lookups are optimized
- Notification counts are cached

### 3. Database Optimization
- Proper indexing on notification tables
- Cleanup of old notifications
- Efficient preference queries

## Security Features

### 1. User Isolation
- Users can only see their own notifications
- Preference changes are user-specific
- No cross-user data leakage

### 2. Input Validation
- All notification data is validated
- Template variables are sanitized
- XSS protection in templates

### 3. Rate Limiting
- API endpoints are rate limited
- Prevents notification spam
- Protects against abuse

## Monitoring & Logging

### 1. Notification Metrics
- Delivery success rates
- Channel performance
- User engagement tracking
- Error rate monitoring

### 2. Logging
- Comprehensive notification logs
- Error tracking and reporting
- Performance metrics
- User activity tracking

## Troubleshooting

### Common Issues

1. **Notifications Not Sending**
   ```bash
   # Check queue workers
   php artisan queue:work --verbose
   
   # Check mail configuration
   php artisan config:cache
   ```

2. **Email Delivery Issues**
   ```bash
   # Test mail configuration
   php artisan tinker
   Mail::raw('Test email', function($msg) {
       $msg->to('test@example.com')->subject('Test');
   });
   ```

3. **Missing Templates**
   ```bash
   # Reseed notification templates
   php artisan db:seed --class=NotificationTemplatesSeeder
   ```

## Future Enhancements

### Planned Features

1. **Advanced Scheduling**
   - Digest notifications
   - Quiet hours configuration
   - Timezone-aware delivery

2. **Rich Content**
   - Attachment support
   - Inline images
   - Interactive elements

3. **Analytics Dashboard**
   - Notification performance metrics
   - User engagement analytics
   - A/B testing for templates

4. **Mobile Integration**
   - Native mobile app notifications
   - Deep linking support
   - Offline notification queue

## Support

For issues or questions regarding the notification system:

1. Check the logs: `storage/logs/laravel.log`
2. Monitor queue status: `php artisan queue:monitor`
3. Review notification preferences in the dashboard
4. Test notifications in development environment

## Version History

- **v1.0.0** - Initial notification system implementation
- **v1.1.0** - Added template system and preferences
- **v1.2.0** - Integrated with batch processing system

---

*Last Updated: January 21, 2025* 