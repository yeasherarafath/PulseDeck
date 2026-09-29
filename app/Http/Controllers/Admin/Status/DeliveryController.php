<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusNotificationDelivery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('status.notifications.manage');

        $deliveries = StatusNotificationDelivery::with(['channel', 'service'])
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->input('event')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.status.notifications.deliveries', [
            'deliveries' => $deliveries,
            'events' => NotificationEvent::cases(),
            'filters' => $request->only(['event', 'status']),
        ]);
    }
}
