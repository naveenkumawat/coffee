<?php

namespace App\Enums;

enum PromotionFulfilmentScope: string
{
    case Any = 'any';
    case Dining = 'dining';
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any fulfilment',
            self::Dining, self::DineIn => 'Dining only',
            self::Takeaway => 'Takeaway only',
            self::Delivery => 'Delivery only',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Any->value => self::Any->label(),
            self::Dining->value => 'Dining only',
            self::Takeaway->value => self::Takeaway->label(),
            self::Delivery->value => self::Delivery->label(),
        ];
    }

    public function matchesContext(?string $context): bool
    {
        if ($this === self::Any) {
            return true;
        }

        if ($context === null || $context === '') {
            return false;
        }

        if ($this === self::Dining || $this === self::DineIn) {
            return in_array($context, [self::Dining->value, self::DineIn->value], true);
        }

        return $this->value === $context;
    }
}
