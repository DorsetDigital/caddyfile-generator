<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Admin\VirtualHost;
use DorsetDigital\Caddy\Helper\BitbucketHelper;
use DorsetDigital\Caddy\Helper\CaddyHelper;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCaddyFile extends BuildTask
{
    private static $segment = 'buildcaddyfile';
    protected string $title = 'Build the CaddyFile';
    protected static string $description = 'Build the Caddyfile from the stored host definitions';

    public function execute(InputInterface $input, PolyOutput $output): int
    {

        $fileContents = $this->getGlobalBlock();
        $allSites = Versioned::get_by_stage(VirtualHost::class, 'Live');
        /**
         * @var VirtualHost $site
         */
        foreach ($allSites as $site) {
            CaddyHelper::deployTLSFiles($site);
            $fileContents .= CaddyHelper::buildServerBlock($site);
        }

        $adminFileContents = $this->getGlobalOptions();
        $hostDirsList = implode("\n", CaddyHelper::generateHostDirsList())."\n";

        echo "<p>Config files built.  Pushing.</p>";

        $helper = BitbucketHelper::create();
        $bitbucketRes[] = $helper->commitFile($fileContents, '/Caddyfile')->getMessage();
        $bitbucketRes[] = $helper->commitFile($adminFileContents, '/admin.json')->getMessage();
        $bitbucketRes[] = $helper->commitFile($hostDirsList, '/host-dirs')->getMessage();

        $config = SiteConfig::current_site_config();
        if ($config->EnableWAF) {
            //Deploy the WAF config files to the repo too (directives are managed elsewhere)
            if ($config->CorazaConfigID > 0) {
                $bitbucketRes[] = $helper->commitFile($config->CorazaConfig()->getString(), '/waf/'.VirtualHost::CORAZA_CONFIG_FILENAME)->getMessage();
            }
            if ($config->CoreRuleSetConfigID > 0) {
                $bitbucketRes[] = $helper->commitFile($config->CoreRuleSetConfig()->getString(), '/waf/'.VirtualHost::CRS_CONFIG_FILENAME)->getMessage();
            }
        }


        $prRes = $helper->createPR()->getMessage();

        $output->writeForHtml("<pre>".implode("\n", $bitbucketRes)."</pre>\n");
        $output->writeForHtml("<p>".$prRes."</p>\n");
        return 1;
    }

    private function getGlobalBlock()
    {
        $config = SiteConfig::current_site_config();
        return $config->renderWith('Caddy/Global')->forTemplate();
    }

    private function getGlobalOptions()
    {
        $opts = [];
        $config = SiteConfig::current_site_config();
        $opts['admin'] = [
            'config' => [
                'load' => [
                    'module' => 'http',
                    'url' => $config->ConfigURL
                ],
                'load_delay' => sprintf('%ss', $config->ConfigPollingInterval)
            ]
        ];

        return json_encode($opts);
    }
}
