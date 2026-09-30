<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\HeaderPresetCategory;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusHeaderPreset;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HeaderPresetController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.services.view');

        return view('admin.status.header-presets.index', [
            'presets' => StatusHeaderPreset::orderBy('sort_order')->orderBy('name')->get(),
            'categories' => HeaderPresetCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.services.create');

        $validated = $request->validate($this->rules());

        $preset = StatusHeaderPreset::create($this->attributes($validated));

        StatusAuditLog::record('header-preset.created', $preset, null, ['name' => $preset->name]);

        return redirect()->route('admin.status.header-presets.index')->with('status', "Preset [{$preset->name}] created.");
    }

    public function update(Request $request, StatusHeaderPreset $preset): RedirectResponse
    {
        $this->authorize('status.services.update');

        $validated = $request->validate($this->rules($preset->id));

        $preset->forceFill($this->attributes($validated))->save();

        StatusAuditLog::record('header-preset.updated', $preset->fresh(), null, ['name' => $preset->name]);

        return redirect()->route('admin.status.header-presets.index')->with('status', "Preset [{$preset->name}] updated.");
    }

    public function destroy(StatusHeaderPreset $preset): RedirectResponse
    {
        $this->authorize('status.services.delete');

        StatusAuditLog::record('header-preset.deleted', $preset, ['name' => $preset->name], null);

        $preset->delete();

        return redirect()->route('admin.status.header-presets.index')->with('status', 'Preset deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'header_name' => ['required', 'string', 'max:255', Rule::unique('status_header_presets', 'header_name')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(HeaderPresetCategory::class)],
            'input_type' => ['required', 'in:text,password,select'],
            'options_text' => ['nullable', 'string', 'max:5000'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $options = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['options_text'] ?? '')) ?: [])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->map(fn ($option) => mb_substr($option, 0, 255))
            ->unique()
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'header_name' => $validated['header_name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'input_type' => $validated['input_type'],
            'options' => $validated['input_type'] === 'select' ? array_values($options) : null,
            'is_sensitive' => (bool) ($validated['is_sensitive'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
