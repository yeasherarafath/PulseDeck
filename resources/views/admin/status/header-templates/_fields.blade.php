@php($rows = $template ? ($template->headers ?? []) : ['Accept' => 'application/json'])
<div class="mb-3">
    <label class="form-label required">Name</label>
    <input type="text" class="form-control" name="name" value="{{ $template?->name }}" required placeholder="JSON API" />
</div>
<div class="mb-3">
    <label class="form-label">Description</label>
    <input type="text" class="form-control" name="description" value="{{ $template?->description }}" placeholder="Standard JSON API request." />
</div>
<div class="mb-3">
    <label class="form-label">Headers (values are stored encrypted; all rows are replaced on save)</label>
    @foreach ($rows as $headerName => $headerValue)
        <div class="row g-2 mb-2">
            <div class="col-md-5"><input type="text" class="form-control font-monospace" name="headers[{{ $loop->index }}][name]" value="{{ $headerName }}" placeholder="Accept" /></div>
            <div class="col-md-7"><input type="text" class="form-control font-monospace" name="headers[{{ $loop->index }}][value]" value="{{ is_string($headerValue) ? $headerValue : '' }}" placeholder="application/json" /></div>
        </div>
    @endforeach
    <div class="form-hint">Leave a name blank to drop that row. Add more rows as needed (up to 20).</div>
    @for ($i = count($rows); $i < min(count($rows) + 3, 20); $i++)
        <div class="row g-2 mb-2">
            <div class="col-md-5"><input type="text" class="form-control font-monospace" name="headers[{{ $i }}][name]" value="" placeholder="X-Custom" /></div>
            <div class="col-md-7"><input type="text" class="form-control font-monospace" name="headers[{{ $i }}][value]" value="" placeholder="value" /></div>
        </div>
    @endfor
</div>
<div class="mb-0">
    <label class="form-check form-switch">
        <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($template?->is_active ?? true) />
        <span class="form-check-label">Active (offered on the service form)</span>
    </label>
</div>
