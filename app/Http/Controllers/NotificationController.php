<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    //  عرض إشعارات  
 public function ownerNotifications(Request $request)
{
    $user = $request->user();
    $notifications = Notification::where('user_id', $user->id)
        ->latest()
        ->get();
    return response()->json([
        'count' => $notifications->count(),
        'notifications' => $notifications,
    ]);
}


public function markAsRead($id) // لتحديث حالة القراءة
{
    $notification = Notification::findOrFail($id);
    $notification->update(['is_read' => true]);
    return response()->json(['message' => 'تم تحديد الإشعار كمقروء.']);
}



}
