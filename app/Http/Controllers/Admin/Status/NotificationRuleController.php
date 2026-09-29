<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationRule;
use App\Models\Status\StatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationRuleController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.notifications.manage');

        return view('admin.status.notifications.rules', [
            'rules' => StatusNotificationRule::with(['channel', 'service'])->orderBy('id')->get(),
            'channels' => StatusNotificationChannel::orderBy('name')->get(),
            'services' => StatusService::orderBy('name')->get(),
            'events' => NotificationEvent::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        $validated = $request->validate([
            'channel_id' => ['required', 'exists:status_notification_channels,id'],
            'service_id' => ['nullable', 'exists:status_services,id'],
            'event' => ['required', 'in:'.implode(',', array_column(NotificationEvent::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = StatusNotificationRule::create([
            'channel_id' => $validated['channel_id'],
            'service_id' => $validated['service_id'] ?? null,
            'event' => $validated['event'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        StatusAuditLog::record('notification.rule.created', $rule, null, ['event' => $rule->event->value]);

        return redirect()->route('admin.status.notifications.rules')->with('status', 'Rule created.');
    }

    public function destroy(Request $request, StatusNotificationRule $rule): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        StatusAuditLog::record('notification.rule.deleted', $rule, ['event' => $rule->event->value], null);

        $rule->delete();

        return redirect()->route('admin.status.notifications.rules')->with('status', 'Rule deleted.');
    }
}
