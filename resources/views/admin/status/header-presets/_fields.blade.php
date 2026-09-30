<div class="mb-3">
    <label class="form-label required">Name</label>
    <input type="text" class="form-control" name="name" value="{{ $preset?->name }}" required placeholder="Accept" />
</div>
<div class="mb-3">
    <label class="form-label required">Header name</label>
    <input type="text" class="form-control font-monospace" name="header_name" value="{{ $preset?->header_name }}" required placeholder="Accept" />
</div>
<div class="mb-3">
    <label class="form-label">Description</label>
    <input type="text" class="form-control" name="description" value="{{ $preset?->description }}" placeholder="Media types the client accepts." />
</div>
<div class="row g-3">
    <div class="col-6">
        <label class="form-label required">Category</label>
        <select class="form-select" name="category">
            @foreach ($categories ?? \App\Enums\Status\HeaderPresetCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected($preset?->category?->value === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6">
        <label class="form-label required">Input type</label>
        <select class="form-select" name="input_type">
            @foreach (['text' => 'Text', 'password' => 'Password (sensitive)', 'select' => 'Select (predefined values)'] as $value => $label)
                <option value="{{ $value }}" @selected(($preset?->input_type ?? 'text') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mb-3 mt-3">
    <label class="form-label">Predefined values (one per line, for select inputs)</label>
    <textarea class="form-control font-monospace" name="options_text" rows="4" placeholder="application/json&#10;text/html">{{ $preset ? implode("\n", $preset->options ?? []) : '' }}</textarea>
</div>
<div class="row g-3">
    <div class="col-6">
        <label class="form-label">Sort order</label>
        <input type="number" class="form-control" name="sort_order" value="{{ $preset?->sort_order ?? 0 }}" min="0" />
    </div>
    <div class="col-6 d-flex flex-column justify-content-end gap-2">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="is_sensitive" value="1" @checked((bool) ($preset?->is_sensitive ?? false)) />
            <span class="form-check-label">Sensitive (value hidden)</span>
        </label>
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($preset?->is_active ?? true) />
            <span class="form-check-label">Active</span>
        </label>
    </div>
</div>
