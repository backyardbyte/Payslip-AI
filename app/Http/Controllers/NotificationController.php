<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends ApiResponseController
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 15);
        $filter = $request->get('filter', 'all'); // all, unread, read

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->latest()->paginate($perPage);

        return $this->success([
            'notifications' => $notifications,
            'stats' => $this->notificationService->getNotificationStats($user),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $success = $this->notificationService->markAsRead($user, $id);

        if ($success) {
            return $this->success(['message' => 'Notification marked as read']);
        }

        return $this->error('Notification not found or already read', 404);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $count = $this->notificationService->markAllAsRead($user);

        return $this->success([
            'message' => "Marked {$count} notifications as read",
            'count' => $count,
        ]);
    }

    /**
     * Delete notification.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->delete();

        return $this->success(['message' => 'Notification deleted']);
    }

    /**
     * Get user's notification preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = NotificationPreference::where('user_id', $user->id)->get();

        // Group by event type
        $grouped = $preferences->groupBy('event_type')->map(function ($items) {
            return $items->keyBy('channel');
        });

        return $this->success([
            'preferences' => $grouped,
            'available_events' => NotificationTemplate::getEventTypes(),
            'available_channels' => NotificationTemplate::getChannels(),
        ]);
    }

    /**
     * Update user's notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.event_type' => 'required|string',
            'preferences.*.channel' => 'required|string|in:in_app,email,sms,push',
            'preferences.*.enabled' => 'required|boolean',
            'preferences.*.settings' => 'nullable|array',
        ]);

        foreach ($request->preferences as $preference) {
            NotificationPreference::updateOrCreate([
                'user_id' => $user->id,
                'event_type' => $preference['event_type'],
                'channel' => $preference['channel'],
            ], [
                'enabled' => $preference['enabled'],
                'settings' => $preference['settings'] ?? null,
            ]);
        }

        return $this->success(['message' => 'Notification preferences updated']);
    }

    /**
     * Get notification statistics.
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->notificationService->getNotificationStats($user);

        return $this->success($stats);
    }

    /**
     * Test notification (for development).
     */
    public function test(Request $request): JsonResponse
    {
        if (!app()->environment('local')) {
            return $this->error('Test notifications only available in local environment', 403);
        }

        $request->validate([
            'event_type' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $user = Auth::user();
        $this->notificationService->send($user, $request->event_type, $request->data ?? []);

        return $this->success(['message' => 'Test notification sent']);
    }

    /**
     * Get recent notifications for header display.
     */
    public function getRecent(): JsonResponse
    {
        $user = Auth::user();
        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get();

        $unreadCount = $user->unreadNotifications()->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }
} 