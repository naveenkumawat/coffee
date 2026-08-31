<?php

namespace App\Services\Social;

use App\Enums\SocialIconKey;
use App\Models\SocialLink;
use App\Repositories\Social\SocialLinkRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialLinkService implements SocialLinkServiceInterface
{
    public function __construct(
        protected SocialLinkRepositoryInterface $links,
    ) {}

    public function store(array $data): SocialLink
    {
        return DB::transaction(fn (): SocialLink => $this->links->create($this->prepareAttributes($data)));
    }

    public function update(SocialLink $link, array $data): SocialLink
    {
        return DB::transaction(
            fn (): SocialLink => $this->links->update($link, $this->prepareAttributes($data, (int) $link->getKey())),
        );
    }

    public function delete(SocialLink $link): void
    {
        DB::transaction(function () use ($link): void {
            $link->forceFill(['is_active' => false])->save();
            $this->links->delete($link);
        });
    }

    public function setActive(SocialLink $link, bool $isActive): SocialLink
    {
        return $this->links->setActive($link, $isActive);
    }

    public function move(SocialLink $link, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Invalid reorder direction.',
            ]);
        }

        DB::transaction(fn () => $this->links->move($link, $direction));
    }

    public function customerFacingLinks(?string $canonicalWhatsappNumber = null): array
    {
        return $this->links->activeOrdered()
            ->map(function (SocialLink $link) use ($canonicalWhatsappNumber): ?array {
                $url = $this->resolvePublicUrl($link, $canonicalWhatsappNumber);

                if ($url === null) {
                    return null;
                }

                $iconKey = Str::lower(trim((string) $link->icon_key));

                return [
                    'label' => (string) $link->label,
                    'icon_key' => $iconKey !== '' ? $iconKey : 'link',
                    'url' => $url,
                    'sort_order' => (int) $link->sort_order,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?int $ignoreId = null): array
    {
        $platformKey = Str::lower(trim((string) ($data['platform_key'] ?? '')));
        $label = trim(strip_tags((string) ($data['label'] ?? '')));
        $url = $this->normalizeUrl($data['url'] ?? null);
        $iconKey = SocialIconKey::from((string) ($data['icon_key'] ?? ''))->value;

        if ($this->links->platformKeyExists($platformKey, $ignoreId)) {
            throw ValidationException::withMessages([
                'platform_key' => 'This platform key is already in use.',
            ]);
        }

        return [
            'platform_key' => $platformKey,
            'label' => $label,
            'url' => $url,
            'icon_key' => $iconKey,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    protected function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim(strip_tags($value));

        return $url === '' ? null : $url;
    }

    protected function resolvePublicUrl(SocialLink $link, ?string $canonicalWhatsapp): ?string
    {
        $url = $this->normalizeUrl($link->url);

        if ($url === null && $link->isWhatsapp()) {
            $url = self::whatsappUrlFromNumber($canonicalWhatsapp);
        }

        if ($url === null) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    public static function whatsappUrlFromNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if (strlen($digits) < 8) {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }
}
