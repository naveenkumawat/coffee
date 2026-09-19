<?php

namespace App\Enums;

enum BrandDisplayMode: string
{
    case Logo = 'logo';
    case LogoName = 'logo_name';
    case LogoNameTagline = 'logo_name_tagline';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'Logo only',
            self::LogoName => 'Logo + name',
            self::LogoNameTagline => 'Logo + name + tagline',
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Logo,
            self::LogoName,
            self::LogoNameTagline,
        ];
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom(trim((string) $value)) ?? self::LogoNameTagline;
    }
}
