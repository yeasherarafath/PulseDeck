<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusHeaderTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeaderTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.services.view');

        return view('admin.status.header-templates.index', [
            'templates' => StatusHeaderTemplate::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.services.create');

        $validated = $request->validate($this->rules());

        $template = StatusHeaderTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'headers' => $this->headersMap($validated),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        StatusAuditLog::record('header-template.created', $template, null, ['name' => $template->name]);

        return redirect()->route('admin.status.header-templates.index')->with('status', "Template [{$template->name}] created.");
    }

    public function update(Request $request, StatusHeaderTemplate $template): RedirectResponse
    {
        $this->authorize('status.services.update');

        $validated = $request->validate($this->rules($template->id));

        // Values are replaced wholesale (encrypted cast); blanks do not keep
        // old secrets here unlike service auth — the form always shows the
        // current count of rows to edit explicitly.
        $template->forceFill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'headers' => $this->headersMap($validated),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ])->save();

        StatusAuditLog::record('header-template.updated', $template->fresh(), null, ['name' => $template->name]);

        return redirect()->route('admin.status.header-templates.index')->with('status', "Template [{$template->name}] updated.");
    }

    public function destroy(StatusHeaderTemplate $template): RedirectResponse
    {
        $this->authorize('status.services.delete');

        StatusAuditLog::record('header-template.deleted', $template, ['name' => $template->name], null);

        $template->delete();

        return redirect()->route('admin.status.header-templates.index')->with('status', 'Template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:status_header_templates,name,'.$ignoreId],
            'description' => ['nullable', 'string', 'max:2000'],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function headersMap(array $validated): array
    {
        $map = [];

        foreach ($validated['headers'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $map[$name] = (string) ($row['value'] ?? '');
        }

        return $map;
    }
}
