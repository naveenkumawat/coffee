<?php

namespace App\Services\WhatsApp;

final class WhatsAppTemplateMessage
{
    /**
     * @param  list<string>  $bodyParameters
     * @param  list<array{type: string, parameters?: list<array{type: string, text?: string}>}>|null  $extraComponents
     */
    public function __construct(
        public string $to,
        public string $templateName,
        public string $language,
        public array $bodyParameters = [],
        public ?string $templateKey = null,
        public ?array $extraComponents = null,
    ) {}
}
