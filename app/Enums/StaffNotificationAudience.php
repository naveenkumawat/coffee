<?php

namespace App\Enums;

enum StaffNotificationAudience: string
{
    case Administrators = 'administrators';
    case Baristas = 'baristas';
    case Waiters = 'waiters';
}
