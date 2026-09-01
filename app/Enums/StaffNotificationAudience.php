<?php

namespace App\Enums;

enum StaffNotificationAudience: string
{
    case Administrators = 'administrators';
    case Operators = 'operators';
    case Baristas = 'baristas';
    case Chefs = 'chefs';
    case Waiters = 'waiters';
}
