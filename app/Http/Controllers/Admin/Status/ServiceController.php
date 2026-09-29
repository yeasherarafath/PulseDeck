<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\StatusServiceRequest;
use App\Http\Requests\Status\TestServiceRequest;
use App\Models\Status\StatusService;
use App\Models\Status\StatusServiceGroup;
use App\Services\Status\HeaderPresetManager;
use App\Services\Status\StatusServiceManager;
use App\Services\Status\UptimeCalculator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private StatusServiceManager $manager)
    {
        //
    }

    public function index(Request $request): View
    {
        $this->authorize('status.services.view');

        $services = StatusService::with('group')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('group'), fn ($query) => $query->where('group_id', $request->input('group')))
            ->when($request->filled('status'), fn ($query) => $query->where('current_status', $request->input('status')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', (bool) $request->input('active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.status.services.index', [
            'services' => $services,
            'groups' => StatusServiceGroup::ordered()->get(),
            'filters' => $request->only(['search', 'group', 'status', 'active']),
        ]);
    }

    public function create(HeaderPresetManager $presets): View
    {
        $this->authorize('status.services.create');

        return view('admin.status.services.create', $this->formData($presets));
    }

    public function store(StatusServiceRequest $request): RedirectResponse
    {
        $service = $this->manager->create($request->validatedForService(), $request->user());

        return redirect()
            ->route('admin.status.services.edit', $service)
            ->with('status', "Service [{$service->name}] created.");
    }

    public function show(StatusService $service, UptimeCalculator $uptime): View
    {
        $this->authorize('status.services.view');

        $checks = $service->checks()->orderByDesc('checked_at')->orderByDesc('id')->paginate(25);

        return view('admin.status.services.show', [
            'service' => $service,
            'checks' => $checks,
            'uptime' => $uptime->forService($service, 30),
            'avgResponse' => $uptime->averageResponseTime($service, 1),
        ]);
    }

    public function edit(StatusService $service, HeaderPresetManager $presets): View
    {
        $this->authorize('status.services.update');

        return view('admin.status.services.edit', $this->formData($presets, $service));
    }

    public function update(StatusServiceRequest $request, StatusService $service): RedirectResponse
    {
        $service = $this->manager->update($service, $request->validatedForService(), $request->user());

        return redirect()
            ->route('admin.status.services.edit', $service)
            ->with('status', "Service [{$service->name}] updated.");
    }

    public function destroy(Request $request, StatusService $service): RedirectResponse
    {
        $this->authorize('status.services.delete');

        $this->manager->delete($service, $request->user());

        return redirect()
            ->route('admin.status.services.index')
            ->with('status', "Service [{$service->name}] deleted.");
    }

    public function pause(Request $request, StatusService $service): RedirectResponse
    {
        $this->authorize('status.services.update');

        $service = $this->manager->toggleActive($service, $request->user());

        $state = $service->is_active ? 'resumed' : 'paused';

        return redirect()->back()->with('status', "Service [{$service->name}] {$state}.");
    }

    public function check(Request $request, StatusService $service): RedirectResponse
    {
        $this->authorize('status.monitoring.run');

        $this->manager->checkNow($service);

        return redirect()->back()->with('status', "Check queued for [{$service->name}].");
    }

    public function test(Request $request, StatusService $service): JsonResponse
    {
        return response()->json($this->manager->testRequest($service, []));
    }

    public function testUnsaved(TestServiceRequest $request): JsonResponse
    {
        return response()->json($this->manager->testRequest(null, $request->validatedForService()));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(HeaderPresetManager $presets, ?StatusService $service = null): array
    {
        $mapToRows = function (?array $map, bool $blankSensitive): array {
            $rows = [];

            foreach (($map ?? []) as $name => $value) {
                if (is_array($value) && (array_key_exists('name', $value) || array_key_exists('value', $value))) {
                    $rows[] = ['name' => (string) ($value['name'] ?? ''), 'value' => (string) ($value['value'] ?? '')];

                    continue;
                }

                $name = (string) $name;
                $rows[] = [
                    'name' => $name,
                    'value' => ($blankSensitive && $value !== '' && HeaderPresetManager::isSensitive($name)) ? '' : (string) $value,
                    'secret' => $blankSensitive && $value !== '' && HeaderPresetManager::isSensitive($name),
                ];
            }

            return $rows === [] ? [['name' => '', 'value' => '']] : $rows;
        };

        $old = fn (string $key, mixed $fallback) => old($key, $fallback);

        // Repeatable row lists must never render empty: the row JS clones
        // the first row, so an empty container bricks "+ Add" and templates.
        // (old() can hand back [] after a failed validation round-trip.)
        $oldRows = fn (string $key, array $fallback) => $old($key, $fallback) ?: $fallback;

        $authData = $old('auth', $this->storedAuth($service));

        if (is_array($authData) && ($authData['headers'] ?? null) === []) {
            $authData['headers'] = [['name' => '', 'value' => '']];
        }

        return [
            'service' => $service,
            'groups' => StatusServiceGroup::ordered()->get(),
            'presets' => $presets->groupedPresets(),
            'templates' => $presets->activeTemplates(),
            'headerRows' => $oldRows('headers', $mapToRows(is_array($service?->request_headers) ? $service->request_headers : [], true)),
            'queryRows' => $oldRows('query', $mapToRows(is_array($service?->query_params) ? $service->query_params : [], false)),
            'bodyFields' => $oldRows('body_fields', $mapToRows($this->storedBodyMap($service), false)),
            'authData' => $authData,
            'bodyAssertions' => $oldRows('body_assertions', $this->storedRows($service?->response_assertions, ['type' => 'contains', 'value' => ''])),
            'jsonAssertions' => $oldRows('json_assertions', $this->storedRows($service?->json_assertions, ['path' => '', 'operator' => 'equals', 'expected' => ''])),
            'headerAssertions' => $oldRows('header_assertions', $this->storedRows($service?->header_assertions, ['header' => '', 'operator' => 'contains', 'expected' => ''])),
            'expectedStatuses' => $old('expected_statuses', implode(', ', $service?->expected_status_codes ?? [200])),
            'globalMinDown' => (int) setting('min_failed_checks_down', 1),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function storedBodyMap(?StatusService $service): array
    {
        if (! $service || ! in_array($service->request_body_type?->value, ['form', 'urlencoded'], true)) {
            return [];
        }

        $decoded = json_decode((string) $service->request_body, true);

        return is_array($decoded) ? array_map(strval(...), $decoded) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function storedAuth(?StatusService $service): array
    {
        $auth = is_array($service?->authentication) ? $service->authentication : ['type' => 'none'];

        if (isset($auth['headers']) && is_array($auth['headers'])) {
            $rows = [];

            foreach ($auth['headers'] as $name => $value) {
                $rows[] = [
                    'name' => (string) $name,
                    'value' => $value !== '' ? '' : '',
                    'secret' => $value !== '',
                ];
            }

            $auth['headers'] = $rows === [] ? [['name' => '', 'value' => '']] : $rows;
        }

        foreach (['token', 'password', 'key'] as $secretKey) {
            if (! empty($auth[$secretKey])) {
                $auth[$secretKey] = '';
                $auth[$secretKey.'_kept'] = true;
            }
        }

        return $auth;
    }

    /**
     * @param  list<array<string, mixed>>  $stored
     * @return list<array<string, mixed>>
     */
    private function storedRows(?array $stored, array $blank): array
    {
        if (! is_array($stored)) {
            return [$blank];
        }

        $rows = array_values(array_filter($stored, fn ($row) => is_array($row)));

        return $rows === [] ? [$blank] : $rows;
    }
}
