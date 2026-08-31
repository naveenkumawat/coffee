<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class StaffNotificationController extends Controller
{
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user('admin');
        abort_unless($user !== null, 403);

        $record = $user->notifications()->whereKey($notification)->first();
        abort_unless($record instanceof DatabaseNotification, 404);

        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user('admin');
        abort_unless($user !== null, 403);

        $user->unreadNotifications->markAsRead();

        return back();
    }
}
