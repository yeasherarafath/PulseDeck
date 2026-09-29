@props(['status'])
@php
    use App\Enums\Status\CheckResultStatus;
    use App\Enums\Status\ServiceStatus;

    $enum = $status instanceof ServiceStatus || $status instanceof CheckResultStatus
        ? $status
        : ServiceStatus::tryFrom((string) $status);

    $label = $enum?->label() ?? ucfirst(str_replace('_', ' ', (string) $status));
    $class = $enum && method_exists($enum, 'badgeClass') ? $enum->badgeClass() : 'bg-secondary-lt';
    $dot = $enum && method_exists($enum, 'color') ? $enum->color() : 'secondary';
@endphp
<span {{ $attributes->merge(['class' => "badge {$class}"]) }}><span class="status-dot bg-{{ $dot }} me-1"></span>{{ $label }}</span>
