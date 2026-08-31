<?php

namespace App\Enums;

enum StaffNotificationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
    case Success = 'success';

    public function tone(): string
    {
        return match ($this) {
            self::Info => 'neutral',
            self::Warning => 'warning',
            self::Critical => 'danger',
            self::Success => 'success',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Info => 'badge-light-primary',
            self::Warning => 'badge-light-warning',
            self::Critical => 'badge-light-danger',
            self::Success => 'badge-light-success',
        };
    }
}
