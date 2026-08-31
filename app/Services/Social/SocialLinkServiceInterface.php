<?php

namespace App\Services\Social;

use App\Models\SocialLink;

interface SocialLinkServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): SocialLink;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SocialLink $link, array $data): SocialLink;

    public function delete(SocialLink $link): void;

    public function setActive(SocialLink $link, bool $isActive): SocialLink;

    public function move(SocialLink $link, string $direction): void;

    /**
     * Customer-safe social links (active + configured URL), ordered.
     *
     * @return list<array{label: string, icon_key: string, url: string, sort_order: int}>
     */
    public function customerFacingLinks(?string $canonicalWhatsappNumber = null): array;
}
