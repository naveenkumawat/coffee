<?php

namespace App\Enums;

enum StaffNotificationChannel: string
{
    case Database = 'database';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
