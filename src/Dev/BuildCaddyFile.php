<?php

namespace DorsetDigital\Caddy\Dev;

use DorsetDigital\Caddy\Admin\VirtualHost;
use DorsetDigital\Caddy\Helper\CaddyHelper;
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
        $fileContents = $this->getGlobalBlock();
        $allSites = Versioned::get_by_stage(VirtualHost::class, 'Live');
        /**
         * @var VirtualHost $site
         */
        foreach ($allSites as $site) {
            $fileContents .= CaddyHelper::buildServerBlock($site);
        }

        echo "<pre>".$fileContents."</pre>";
    }

    private function getGlobalBlock() {
        $config = SiteConfig::current_site_config();
        return $config->renderWith('Caddy/Global')->forTemplate();
    }
}
