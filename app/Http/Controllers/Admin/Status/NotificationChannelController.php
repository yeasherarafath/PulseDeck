<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusNotificationChannel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationChannelController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.notifications.manage');

        return view('admin.status.notifications.channels', [
            'channels' => StatusNotificationChannel::withCount('rules')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:mail,webhook'],
            'recipients' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $channel = StatusNotificationChannel::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config' => $this->configFor($validated),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        StatusAuditLog::record('notification.channel.created', $channel, null, ['name' => $channel->name, 'type' => $channel->type->value]);

        return redirect()->route('admin.status.notifications.channels')->with('status', "Channel [{$channel->name}] created.");
    }

    public function update(Request $request, StatusNotificationChannel $channel): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'recipients' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $config = $this->configFor($validated, $channel->config ?? []);

        $channel->forceFill([
            'name' => $validated['name'],
            'config' => $config,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ])->save();

        StatusAuditLog::record('notification.channel.updated', $channel, null, ['name' => $channel->name]);

        return redirect()->route('admin.status.notifications.channels')->with('status', "Channel [{$channel->name}] updated.");
    }

    public function destroy(Request $request, StatusNotificationChannel $channel): RedirectResponse
    {
        $this->authorize('status.notifications.manage');

        StatusAuditLog::record('notification.channel.deleted', $channel, ['name' => $channel->name], null);

        $channel->delete();

        return redirect()->route('admin.status.notifications.channels')->with('status', 'Channel deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function configFor(array $validated, array $existing = []): array
    {
        $type = $validated['type'] ?? null;

        if ($type === NotificationChannelType::Mail->value || (! $type && isset($existing['to']))) {
            return ['to' => $this->emails($validated['recipients'] ?? '')];
        }

        $config = ['url' => $validated['url'] ?? $existing['url'] ?? null];

        // Blank secret keeps the stored one (forms never prefill it).
        if (! empty($validated['secret'])) {
            $config['secret'] = $validated['secret'];
        } elseif (isset($existing['secret'])) {
            $config['secret'] = $existing['secret'];
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    private function emails(string $raw): array
    {
        return array_values(array_filter(array_map(
            fn ($email) => trim($email),
            preg_split('/[,\n;]+/', $raw) ?: []
        ), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
    }
}
