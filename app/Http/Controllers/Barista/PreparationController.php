<?php

namespace App\Http\Controllers\Barista;

use App\Enums\PreparationStation;
use App\Http\Controllers\Internal\StationPreparationController;

class PreparationController extends StationPreparationController
{
    protected function station(): PreparationStation
    {
        return PreparationStation::Bar;
    }

    protected function panel(): string
    {
        return 'barista';
    }

    protected function indexRouteName(): string
    {
        return 'barista.preparations.index';
    }

    protected function acceptRouteName(): string
    {
        return 'barista.preparations.accept';
    }

    protected function preparingRouteName(): string
    {
        return 'barista.preparations.preparing';
    }

    protected function readyRouteName(): string
    {
        return 'barista.preparations.ready';
    }

    protected function orderShowRouteName(): ?string
    {
        return 'barista.orders.show';
    }
}
