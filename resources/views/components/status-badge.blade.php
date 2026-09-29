@props(['status'])
@php
    use App\Enums\Status\CheckResultStatus;
    use App\Enums\Status\IncidentImpact;
    use App\Enums\Status\IncidentStatus;
    use App\Enums\Status\MaintenanceStatus;
    use App\Enums\Status\ServiceStatus;

    $enum = $status instanceof \BackedEnum
        ? $status
        : ServiceStatus::tryFrom((string) $status)
            ?? CheckResultStatus::tryFrom((string) $status)
            ?? IncidentStatus::tryFrom((string) $status)
            ?? IncidentImpact::tryFrom((string) $status)
            ?? MaintenanceStatus::tryFrom((string) $status);

    $label = $enum && method_exists($enum, 'label')
        ? $enum->label()
        : ucfirst(str_replace('_', ' ', $status instanceof \BackedEnum ? $status->value : (string) $status));
    $class = $enum && method_exists($enum, 'badgeClass') ? $enum->badgeClass() : 'bg-secondary-lt';
    $dot = $enum && method_exists($enum, 'color') ? $enum->color() : 'secondary';
@endphp
<span {{ $attributes->merge(['class' => "badge {$class}"]) }}><span class="status-dot bg-{{ $dot }} me-1"></span>{{ $label }}</span>
