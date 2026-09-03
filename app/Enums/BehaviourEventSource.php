<?php

namespace App\Enums;

enum BehaviourEventSource: string
{
    case Client = 'client';
    case Server = 'server';
}
