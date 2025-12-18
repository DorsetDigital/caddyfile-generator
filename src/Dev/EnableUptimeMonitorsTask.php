<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class EnableUptimeMonitorsTask extends BuildTask
{
    protected string $title = 'Enable Uptime Monitors on all sites';
    protected static string $description = 'Enables uptime monitors on all sites, they will be activated during the next deployment run';
    protected static string $commandName = 'enable-uptime-monitors';

    public function execute(InputInterface $input, PolyOutput $output): int
    {
        //Get all the sites, this is a sledgehammer
        $allSites = VirtualHost::get();

        //Update them to enable monitoring, and save.  The write hooks should sort it all out
        foreach ($allSites as $site) {
            $site->update([
                'UptimeMonitorEnabled' => true,
            ])->write();
            $site->publishRecursive();
        }

        $output->writeln('Uptime Monitors enabled - please run a deployment to create them on production');

        return Command::SUCCESS;
    }
}