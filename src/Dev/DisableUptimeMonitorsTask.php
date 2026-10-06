<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class DisableUptimeMonitorsTask extends BuildTask
{
    protected static string $description =
        'Disables uptime monitoring on all VirtualHosts and publishes the changes';

    protected static string $commandName = 'disable-uptime-monitors';

    protected string $title = 'Disable Uptime Monitors on all sites';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $updated = 0;

        foreach (VirtualHost::get() as $site) {
            if (!$site->UptimeMonitorEnabled) {
                continue;
            }

            $site->UptimeMonitorEnabled = false;
            $site->write();
            $site->publishSingle();
            $updated++;

            $output->writeln(sprintf(
                'Disabled ID %d: %s',
                $site->ID,
                $site->Title ?: $site->HostName ?: '(unnamed host)'
            ));
        }

        $output->writeln(sprintf(
            'Complete. Disabled uptime monitoring on %d VirtualHost record(s).',
            $updated
        ));

        return Command::SUCCESS;
    }
}
