<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => ($request->input('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications())
                ->latest()->paginate(20)->withQueryString(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Mark one notification read and go to its link. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
