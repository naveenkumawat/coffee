<?php

namespace App\Enums;

enum CustomerNotificationChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
