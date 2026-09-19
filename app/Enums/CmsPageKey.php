<?php

namespace App\Enums;

enum CmsPageKey: string
{
    case About = 'about';
    case Contact = 'contact';
    case Faq = 'faq';
    case Terms = 'terms';
    case Privacy = 'privacy';

    public function title(): string
    {
        return match ($this) {
            self::About => 'About',
            self::Contact => 'Contact / Visit',
            self::Faq => 'FAQ',
            self::Terms => 'Terms',
            self::Privacy => 'Privacy',
        };
    }

    public function customerPath(): string
    {
        return match ($this) {
            self::Contact => '/contact',
            default => '/'.$this->value,
        };
    }

    public function legacySettingKey(): WebsiteSettingKey
    {
        return match ($this) {
            self::About => WebsiteSettingKey::PagesAbout,
            self::Contact => WebsiteSettingKey::PagesContact,
            self::Faq => WebsiteSettingKey::PagesFaq,
            self::Terms => WebsiteSettingKey::PagesTerms,
            self::Privacy => WebsiteSettingKey::PagesPrivacy,
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::About,
            self::Contact,
            self::Faq,
            self::Terms,
            self::Privacy,
        ];
    }
}
