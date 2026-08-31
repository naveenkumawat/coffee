<?php

namespace App\Enums;

enum SocialIconKey: string
{
    case Facebook = 'facebook';
    case Whatsapp = 'whatsapp';
    case Instagram = 'instagram';
    case Youtube = 'youtube';
    case X = 'x';
    case Tiktok = 'tiktok';
    case Linkedin = 'linkedin';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Facebook->value => 'Facebook',
            self::Whatsapp->value => 'WhatsApp',
            self::Instagram->value => 'Instagram',
            self::Youtube->value => 'YouTube',
            self::X->value => 'X',
            self::Tiktok->value => 'TikTok',
            self::Linkedin->value => 'LinkedIn',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
