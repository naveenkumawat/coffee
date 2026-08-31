<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppNotificationProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppCloudProvider implements WhatsAppNotificationProviderInterface
{
    public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult
    {
        $config = $this->configSnapshot();

        if (! $config['enabled']) {
            return WhatsAppSendResult::failure('WhatsApp notifications are disabled.', 'disabled');
        }

        if (! filled($config['access_token']) || ! filled($config['phone_number_id'])) {
            return WhatsAppSendResult::failure(
                'WhatsApp Cloud API credentials are incomplete.',
                'missing_config',
            );
        }

        if ($message->to === '' || $message->templateName === '') {
            return WhatsAppSendResult::failure(
                'WhatsApp destination or template name is missing.',
                'invalid_request',
            );
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            rtrim((string) $config['graph_base_url'], '/'),
            trim((string) $config['api_version'], '/'),
            $config['phone_number_id'],
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $message->to,
            'type' => 'template',
            'template' => [
                'name' => $message->templateName,
                'language' => [
                    'code' => $message->language,
                ],
            ],
        ];

        $components = [];

        if ($message->bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $text): array => [
                        'type' => 'text',
                        'text' => $text,
                    ],
                    $message->bodyParameters,
                ),
            ];
        }

        if (is_array($message->extraComponents) && $message->extraComponents !== []) {
            foreach ($message->extraComponents as $component) {
                $components[] = $component;
            }
        }

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken((string) $config['access_token'])
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) $config['connect_timeout'])
                ->timeout((int) $config['timeout'])
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('WhatsApp Cloud API connection failure.', [
                'template' => $message->templateName,
                'exception' => class_basename($exception),
            ]);

            return WhatsAppSendResult::failure(
                'WhatsApp provider connection failed.',
                'connection_error',
                retryable: true,
            );
        } catch (Throwable $exception) {
            Log::warning('WhatsApp Cloud API unexpected failure.', [
                'template' => $message->templateName,
                'exception' => class_basename($exception),
            ]);

            return WhatsAppSendResult::failure(
                'WhatsApp provider request failed unexpectedly.',
                'unexpected_error',
                retryable: true,
            );
        }

        if ($response->successful()) {
            $messageId = data_get($response->json(), 'messages.0.id');

            return WhatsAppSendResult::ok(is_string($messageId) ? $messageId : null);
        }

        $status = $response->status();
        $errorCode = (string) (data_get($response->json(), 'error.code') ?: $status);
        $safeMessage = $this->safeProviderError($response->json(), $status);
        $retryable = $status === 429 || $status >= 500;

        Log::warning('WhatsApp Cloud API rejected template send.', [
            'template' => $message->templateName,
            'http_status' => $status,
            'error_code' => $errorCode,
            'safe_message' => $safeMessage,
        ]);

        return WhatsAppSendResult::failure($safeMessage, $errorCode, $retryable);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     access_token: string|null,
     *     phone_number_id: string|null,
     *     api_version: string|null,
     *     graph_base_url: string|null,
     *     timeout: int,
     *     connect_timeout: int
     * }
     */
    protected function configSnapshot(): array
    {
        return [
            'enabled' => (bool) config('services.whatsapp.enabled', false),
            'access_token' => config('services.whatsapp.access_token'),
            'phone_number_id' => config('services.whatsapp.phone_number_id'),
            'api_version' => config('services.whatsapp.api_version', 'v21.0'),
            'graph_base_url' => config('services.whatsapp.graph_base_url', 'https://graph.facebook.com'),
            'timeout' => (int) config('services.whatsapp.timeout', 10),
            'connect_timeout' => (int) config('services.whatsapp.connect_timeout', 3),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function safeProviderError(?array $json, int $status): string
    {
        $message = data_get($json, 'error.message');

        if (! is_string($message) || trim($message) === '') {
            return 'WhatsApp provider returned HTTP '.$status.'.';
        }

        $message = trim($message);

        // Never retain tokens or long raw payloads in persisted diagnostics.
        if (mb_strlen($message) > 240) {
            $message = mb_substr($message, 0, 240).'…';
        }

        return $message;
    }
}
