<?php

namespace App\Enums;

enum DiningRoundCancellationReason: string
{
    case CustomerCancelled = 'customer_cancelled';
    case WrongItem = 'wrong_item';
    case DuplicateOrder = 'duplicate_order';
    case PreparationError = 'preparation_error';
    case QualityIssue = 'quality_issue';
    case StaffError = 'staff_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerCancelled => 'Customer cancelled',
            self::WrongItem => 'Wrong item',
            self::DuplicateOrder => 'Duplicate order',
            self::PreparationError => 'Preparation error',
            self::QualityIssue => 'Quality issue',
            self::StaffError => 'Staff error',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }
}
