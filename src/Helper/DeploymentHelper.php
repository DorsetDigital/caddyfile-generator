<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Admin\VirtualHost;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class DeploymentHelper
{
    use Injectable;

    private bool $dryRun = false;
    private array $messages;

    public function processDryRun() {
        $this->dryRun = true;
        return $this->processConfig();
    }

    public function processConfig()
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

        $fileContents = preg_replace("/^\s*[\r\n]+/m", "", $fileContents);

        $adminFileContents = $this->getGlobalOptions();
        $hostDirsList = implode("\n", CaddyHelper::generateHostDirsList())."\n";

        if ($this->dryRun) {
            $this->addMessage("Config files built.  DRY RUN - Not pushing to respository...");
            $this->addMessage('<pre>'.$fileContents.'</pre>');
            return true;
        }

        $this->addMessage("Config files built.  Pushing to respository...");

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

        $this->addMessage("<pre>".implode("\n", $bitbucketRes)."</pre>\n");
        $this->addMessage("<p>".$prRes."</p>\n");
        return true;
    }

    public function getMessages(): string {
        return implode("<br>\n", $this->messages);
    }

    private function addMessage($message) {
        $this->messages[] = $message;
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