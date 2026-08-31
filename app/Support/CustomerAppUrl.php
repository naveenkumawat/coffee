<?php

namespace App\Support;

final class CustomerAppUrl
{
    public static function base(): string
    {
        return rtrim((string) config('coffee.pwa.url', config('app.url')), '/');
    }

    public static function to(string $path = '', array $query = []): string
    {
        $path = trim($path);

        $url = $path === '' || $path === '/'
            ? self::base()
            : self::base().'/'.ltrim($path, '/');

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    public static function menu(): string
    {
        return self::to('/menu');
    }

    public static function order(int|string $orderId): string
    {
        return self::to('/orders/'.$orderId);
    }

    public static function resetPassword(string $token, string $email): string
    {
        return self::to('/reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }
}
