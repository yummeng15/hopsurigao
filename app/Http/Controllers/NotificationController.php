<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Format a database notification for client consumption.
     *
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : json_decode((string) $notification->data, true) ?? [];

        return [
            'id'             => $notification->id,
            'type'           => $data['type'] ?? 'notification',
            'title'          => $data['title'] ?? 'Notification',
            'message'        => $data['message'] ?? '',
            'action_url'     => $data['action_url'] ?? '#',
            'action_text'    => $data['action_text'] ?? 'View Details',
            'related_entity' => $data['related_entity'] ?? null,
            'related_id'     => $data['related_id'] ?? null,
            'priority'       => $data['priority'] ?? 'normal',
            'icon'           => $data['icon'] ?? 'bell',
            'read_at'        => $notification->read_at?->toISOString(),
            'is_read'        => $notification->read_at !== null,
            'created_at'     => $notification->created_at?->toISOString(),
            'time_ago'       => $notification->created_at?->diffForHumans(),
            'metadata'       => $data['metadata'] ?? [],
        ];
    }

    /**
     * Get unread notification count for badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['unread_count' => 0]);
        }

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Get recent notifications feed for the dropdown panel.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'notifications' => [],
                'unread_count'  => 0,
            ]);
        }

        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn(DatabaseNotification $n) => $this->formatNotification($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Notification History Page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all'); // all, unread, read
        $priority = $request->input('priority');
        $search = $request->input('search');

        $query = $user->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($priority && in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $query->where('data->priority', $priority);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('data->title', 'like', "%{$search}%")
                  ->orWhere('data->message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate(15)->through(
            fn(DatabaseNotification $n) => $this->formatNotification($n)
        );

        $unreadCount = $user->unreadNotifications()->count();
        $totalCount = $user->notifications()->count();
        $urgentCount = $user->notifications()
            ->whereNull('read_at')
            ->where('data->priority', 'urgent')
            ->count();

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'filters'       => [
                'filter'   => $filter,
                'priority' => $priority,
                'search'   => $search,
            ],
            'counts'        => [
                'total'        => $totalCount,
                'unread'       => $unreadCount,
                'urgent'       => $urgentCount,
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'success'      => true,
            'notification' => $this->formatNotification($notification),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success'      => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success'      => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Clear all read notifications.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->notifications()->whereNotNull('read_at')->delete();

        return response()->json([
            'success'      => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}

