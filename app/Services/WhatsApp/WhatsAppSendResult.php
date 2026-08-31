<?php

namespace App\Services\WhatsApp;

final class WhatsAppSendResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $safeErrorMessage = null,
        public bool $retryable = false,
    ) {}

    public static function ok(?string $providerMessageId): self
    {
        return new self(
            success: true,
            providerMessageId: $providerMessageId,
        );
    }

    public static function failure(
        string $safeErrorMessage,
        ?string $errorCode = null,
        bool $retryable = false,
    ): self {
        return new self(
            success: false,
            errorCode: $errorCode,
            safeErrorMessage: $safeErrorMessage,
            retryable: $retryable,
        );
    }
}
