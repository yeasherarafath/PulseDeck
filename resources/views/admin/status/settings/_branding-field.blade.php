@php($current = branding_asset($setting->value))
<div class="branding-dropzone" data-branding-dropzone>
    <input type="file" class="d-none" name="branding[{{ $setting->key }}]" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/x-icon,.ico" data-branding-input />
    <div class="branding-preview" data-branding-preview>
        @if ($current)
            <img src="{{ $current }}" alt="Current {{ $setting->key }}" />
        @else
            <span class="text-secondary">Drop image here or click to browse</span>
        @endif
    </div>
    <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
        <button type="button" class="btn btn-sm" data-branding-browse>Browse…</button>
        @if ($setting->value)
            <label class="form-check mb-0">
                <input type="checkbox" class="form-check-input" name="remove_branding[{{ $setting->key }}]" value="1" />
                <span class="form-check-label">Remove current ({{ basename($setting->value) }})</span>
            </label>
        @endif
    </div>
    <div class="form-hint">{{ $setting->description }} — PNG, JPG, SVG, WebP or ICO, max 2 MB.</div>
</div>
@error('branding.'.$setting->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
