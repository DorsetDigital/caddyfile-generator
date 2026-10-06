<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class EnableUptimeMonitorsTask extends BuildTask
{
    protected static string $description = 'Enables uptime monitors on all sites, they will be activated during the next deployment run';
    protected static string $commandName = 'enable-uptime-monitors';
    protected string $title = 'Enable Uptime Monitors on all sites';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $allSites = VirtualHost::get();

        foreach ($allSites as $site) {
            // Existing records may pre-date the Farpoint override fields.
            // Treat an empty/invalid override as "use the system default".
            if (
                !$site->UptimeMonitorUseDefaultInterval
                && (int) $site->UptimeMonitorIntervalSeconds < 60
            ) {
                $site->UptimeMonitorUseDefaultInterval = true;
            }

            if (
                !$site->UptimeMonitorUseDefaultFailureThreshold
                && (int) $site->UptimeMonitorFailureThreshold < 1
            ) {
                $site->UptimeMonitorUseDefaultFailureThreshold = true;
            }

            $site->UptimeMonitorEnabled = true;
            $site->write();
            $site->publishSingle();
        }

        $output->writeln('Uptime Monitors enabled - please run a deployment to create them on production');

        return Command::SUCCESS;
    }
}