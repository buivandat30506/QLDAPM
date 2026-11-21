<?php

namespace App\Console\Commands;

use App\Modules\Operations\InfrastructureNormalizer;
use Illuminate\Console\Command;

class NormalizeInfrastructure extends Command
{
    protected $signature = 'airline:normalize-infrastructure {--apply : Apply a conflict-free verified one-to-one mapping}';

    protected $description = 'Inspect legacy infrastructure mappings; never infer parents or merge ambiguous identities';

    public function handle(InfrastructureNormalizer $normalizer): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Only local/test environments are supported.');

            return self::FAILURE;
        }
        $report = $normalizer->inspect();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($report['conflicts']) {
            return self::FAILURE;
        }
        if ($this->option('apply')) {
            $this->line(json_encode($normalizer->apply(), JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
