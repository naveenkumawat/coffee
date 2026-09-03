<?php

namespace App\Console\Commands;

use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Console\Command;

class PersonalisationProfilesRebuildCommand extends Command
{
    protected $signature = 'coffee:personalisation-profiles-rebuild
                            {--customer= : Rebuild a single customer id}
                            {--visitor= : Rebuild a single visitor key}
                            {--stale : Rebuild profiles older than configured stale window}
                            {--limit=100 : Max stale profiles to rebuild}
                            {--reset-customer= : Delete derived profile for customer id (orders untouched)}
                            {--reset-visitor= : Delete derived profile for visitor key}';

    protected $description = 'Rebuild or reset derived personalisation profiles (P2.2)';

    public function handle(PersonalisationProfileServiceInterface $profiles): int
    {
        if ($resetCustomer = $this->option('reset-customer')) {
            $deleted = $profiles->resetForCustomer((int) $resetCustomer);
            $this->info($deleted ? 'Customer profile reset.' : 'No customer profile found.');

            return self::SUCCESS;
        }

        if ($resetVisitor = $this->option('reset-visitor')) {
            $deleted = $profiles->resetForVisitor((string) $resetVisitor);
            $this->info($deleted ? 'Visitor profile reset.' : 'No visitor profile found.');

            return self::SUCCESS;
        }

        if ($customerId = $this->option('customer')) {
            $profile = $profiles->rebuildForCustomer((int) $customerId);
            $this->info("Rebuilt customer profile #{$profile->id} (evidence=".($profile->has_sufficient_evidence ? 'yes' : 'no').').');

            return self::SUCCESS;
        }

        if ($visitorKey = $this->option('visitor')) {
            $profile = $profiles->rebuildForVisitor((string) $visitorKey);
            $this->info("Rebuilt visitor profile #{$profile->id} (evidence=".($profile->has_sufficient_evidence ? 'yes' : 'no').').');

            return self::SUCCESS;
        }

        if ($this->option('stale')) {
            $limit = max(1, (int) $this->option('limit'));
            $count = $profiles->rebuildStale($limit);
            $this->info("Rebuilt {$count} stale profile(s).");

            return self::SUCCESS;
        }

        $this->error('Specify --customer, --visitor, --stale, --reset-customer, or --reset-visitor.');

        return self::FAILURE;
    }
}
