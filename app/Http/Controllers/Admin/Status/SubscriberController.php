<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusSubscriber;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriberController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.notifications.manage');

        return view('admin.status.notifications.subscribers', [
            'subscribers' => StatusSubscriber::orderByDesc('id')->paginate(25),
        ]);
    }

    public function toggle(Request $request, StatusSubscriber $subscriber): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        $subscriber->forceFill(['is_active' => ! $subscriber->is_active])->save();

        return redirect()->back()->with('status', "Subscriber [{$subscriber->email}] updated.");
    }

    public function destroy(Request $request, StatusSubscriber $subscriber): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        StatusAuditLog::record('subscriber.deleted', $subscriber, ['email' => $subscriber->email], null);

        $subscriber->delete();

        return redirect()->back()->with('status', 'Subscriber removed.');
    }
}
