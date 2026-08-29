<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\Agent;

final class AgentsDetector
{
    public function __construct(
        private readonly BoostManager $boostManager
    ) {
    }

    /**
     * @return list<string>
     */
    public function discoverSystemInstalledAgents(): array
    {
        $agents = [];

        foreach ($this->getAgents() as $agent) {
            if ($agent->detectOnSystem()) {
                $agents[] = $agent->name();
            }
        }

        return $agents;
    }

    /**
     * @return list<string>
     */
    public function discoverProjectInstalledAgents(string $basePath): array
    {
        $agents = [];

        foreach ($this->getAgents() as $agent) {
            if ($agent->detectInProject($basePath)) {
                $agents[] = $agent->name();
            }
        }

        return $agents;
    }

    /**
     * @return list<Agent>
     */
    public function getAgents(): array
    {
        return array_values($this->boostManager->instantiateAgents());
    }
}
