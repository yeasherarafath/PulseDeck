@extends('layouts.admin-tabler')

@section('page-pretitle', 'Notifications')
@section('page-title', 'Channels & subscriptions')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><span class="nav-link active">Channels</span></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.rules') }}">Rules</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.subscribers') }}">Subscribers</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.deliveries') }}">Deliveries</a></li>
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Rules</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($channels as $channel)
                        <tr>
                            <td>{{ $channel->name }}</td>
                            <td><span class="badge bg-blue-lt">{{ $channel->type->label() }}</span></td>
                            <td class="text-secondary small">
                                @if ($channel->type->value === 'mail')
                                    {{ implode(', ', $channel->config['to'] ?? []) ?: '—' }}
                                @else
                                    {{ $channel->config['url'] ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $channel->rules_count }}</td>
                            <td>{{ $channel->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#channel-{{ $channel->id }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.status.notifications.channels.destroy', $channel) }}" class="d-inline" onsubmit="return confirm('Delete this channel?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><p class="empty-title">No channels yet</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row row-deck row-cards">
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.status.notifications.channels.store') }}">
                @csrf
                <div class="card">
                    <div class="card-header"><h3 class="card-title">New channel</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="Ops email" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Type</label>
                            <select class="form-select" name="type">
                                <option value="mail">Email</option>
                                <option value="webhook">Webhook</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recipients (email, comma separated)</label>
                            <input type="text" class="form-control" name="recipients" placeholder="ops@example.com" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" class="form-control" name="url" placeholder="https://hooks.example.com/…" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Webhook secret (optional)</label>
                            <input type="password" class="form-control" name="secret" autocomplete="new-password" />
                        </div>
                        <label class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" checked />
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-primary">Create channel</button></div>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">How it works</h3>
                    <p class="text-secondary">Events flow through <strong>Rules</strong>: each rule links a channel to an event, optionally scoped to one service. Email channels also reach verified public subscribers for incident and outage events.</p>
                    <a href="{{ route('admin.status.notifications.rules') }}" class="btn">Manage rules</a>
                </div>
            </div>
        </div>
    </div>

    @foreach ($channels as $channel)
        <div class="modal modal-blur fade" id="channel-{{ $channel->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.status.notifications.channels.update', $channel) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $channel->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $channel->name }}" required />
                            </div>
                            @if ($channel->type->value === 'mail')
                                <div class="mb-3">
                                    <label class="form-label">Recipients (email, comma separated)</label>
                                    <input type="text" class="form-control" name="recipients" value="{{ implode(', ', $channel->config['to'] ?? []) }}" />
                                </div>
                            @else
                                <div class="mb-3">
                                    <label class="form-label">Webhook URL</label>
                                    <input type="url" class="form-control" name="url" value="{{ $channel->config['url'] ?? '' }}" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Webhook secret <span class="text-secondary">(blank keeps stored)</span></label>
                                    <input type="password" class="form-control" name="secret" value="" placeholder="••••••••" autocomplete="new-password" />
                                </div>
                            @endif
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($channel->is_active) />
                                <span class="form-check-label">Active</span>
                            </label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
