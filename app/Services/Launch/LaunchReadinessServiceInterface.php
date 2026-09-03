<?php

namespace App\Services\Launch;

interface LaunchReadinessServiceInterface
{
    public function evaluate(): LaunchReadinessReport;
}
