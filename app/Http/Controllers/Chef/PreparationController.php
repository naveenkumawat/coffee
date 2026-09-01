<?php

namespace App\Http\Controllers\Chef;

use App\Enums\PreparationStation;
use App\Http\Controllers\Internal\StationPreparationController;

class PreparationController extends StationPreparationController
{
    protected function station(): PreparationStation
    {
        return PreparationStation::Kitchen;
    }

    protected function panel(): string
    {
        return 'chef';
    }

    protected function indexRouteName(): string
    {
        return 'chef.preparations.index';
    }

    protected function acceptRouteName(): string
    {
        return 'chef.preparations.accept';
    }

    protected function preparingRouteName(): string
    {
        return 'chef.preparations.preparing';
    }

    protected function readyRouteName(): string
    {
        return 'chef.preparations.ready';
    }
}
