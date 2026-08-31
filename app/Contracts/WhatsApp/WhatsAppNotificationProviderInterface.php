<?php

namespace App\Contracts\WhatsApp;

use App\Services\WhatsApp\WhatsAppSendResult;
use App\Services\WhatsApp\WhatsAppTemplateMessage;

interface WhatsAppNotificationProviderInterface
{
    public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult;
}
