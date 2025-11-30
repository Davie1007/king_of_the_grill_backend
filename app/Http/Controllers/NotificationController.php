<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in to view notifications.',
            ], 401);
        }

        $notifications = $user->unreadNotifications()->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'message' => $notification->data['message'],
                'link' => $notification->data['link'] ?? null,
                'created_at' => $notification->created_at->toDateTimeString(),
                'type' => $notification->data['type'] ?? 'general',
                'priority' => $notification->data['priority'] ?? 'normal',
            ];
        });

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in to mark notifications as read.',
            ], 401);
        }

        $notification = $user->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in to mark notifications as read.',
            ], 401);
        }

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}