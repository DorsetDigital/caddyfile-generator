<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Admin\VirtualHost;
use DorsetDigital\Caddy\Helper\BitbucketHelper;
use DorsetDigital\Caddy\Helper\CaddyHelper;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

class BuildCaddyFile extends BuildTask
{
    private static $segment = 'buildcaddyfile';
    protected $title = 'Build the CaddyFile';
    protected $description = 'Build the Caddyfile from the stored host definitions';

    public function run($request)
    {

        $fileContents = '';
        $allSites = Versioned::get_by_stage(VirtualHost::class, 'Live');
        /**
         * @var VirtualHost $site
         */
        foreach ($allSites as $site) {
            CaddyHelper::deployTLSFiles($site);
            $fileContents .= CaddyHelper::buildServerBlock($site);
        }

        $adminFileContents = $this->getGlobalOptions();

        echo "<p>Config files built.  Pushing.</p>";

        $helper = BitbucketHelper::create();
        $bitbucketRes = $helper->commitFile($fileContents, '/Caddyfile')->getMessage();
        $bitbucketRes = $helper->commitFile($adminFileContents, '/admin.json')->getMessage();
        $prRes = $helper->createPR()->getMessage();

        echo "<p>".$bitbucketRes."</p>\n";
        echo "<p>".$prRes."</p>\n";

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

        if ($config->RedisHost) {
            $opts['storage'] = [
                'module' => 'redis',
                'host' => $config->RedisHost,
                'port' => $config->RedisPort,
                'username' => $config->RedisUser,
                'password' => $config->RedisPassword,
                'db' => 0,
                'timeout' => 5,
                'key_prefix' => $config->RedisKeyPrefix,
                'tls_enabled' => false,
                'tls_insecure' => true

            ];
        }

        return json_encode($opts);
    }
}
