<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class NormaliseUptimeMonitorSettingsTask extends BuildTask
{
    protected static string $description =
        'Normalises legacy uptime monitoring settings added before Farpoint defaults were populated';

    protected static string $commandName = 'normalise-uptime-monitor-settings';

    protected string $title = 'Normalise uptime monitor settings';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $updated = 0;

        foreach (VirtualHost::get() as $site) {
            $changed = false;

            if (
                !$site->UptimeMonitorUseDefaultInterval
                && (int) $site->UptimeMonitorIntervalSeconds < 60
            ) {
                $site->UptimeMonitorUseDefaultInterval = true;
                $changed = true;
            }

            if (
                !$site->UptimeMonitorUseDefaultFailureThreshold
                && (int) $site->UptimeMonitorFailureThreshold < 1
            ) {
                $site->UptimeMonitorUseDefaultFailureThreshold = true;
                $changed = true;
            }

            if (!$changed) {
                continue;
            }

            $site->write();
            $site->publishSingle();
            $updated++;

            $output->writeln(sprintf(
                'Normalised ID %d: %s',
                $site->ID,
                $site->Title ?: $site->HostName ?: '(unnamed host)'
            ));
        }

        $output->writeln(sprintf(
            'Complete. Normalised %d VirtualHost record(s).',
            $updated
        ));

        return Command::SUCCESS;
    }
}
