<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Admin\VirtualHost;

class CaddyHelper
{
    public static function buildServerBlock(VirtualHost $site)
    {
        $template = match ($site->HostType) {
            VirtualHost::HOST_TYPE_HOST => 'Caddy/Host',
            VirtualHost::HOST_TYPE_REDIRECT => 'Caddy/RedirectHost',
            VirtualHost::HOST_TYPE_PROXY => 'Caddy/ProxyHost',
            VirtualHost::HOST_TYPE_MANUAL => 'Caddy/ManualHost'
        };


        $serverBlock = $site->renderWith($template)->forTemplate();

        //Now add the host-level redirect if needed
        if ($site->HostRedirect !== VirtualHost::REDIRECT_NONE) {
            $protocol = ($site->EnableHTTPS) ? 'https' : 'http';
            $apexHost = preg_replace('/^www\./i', '', $site->HostName);

            if ($site->HostRedirect === VirtualHost::REDIRECT_ROOT_TO_WWW) {
                $source = $apexHost;
                $target = sprintf('%s://www.%s', $protocol, $source);
            } else if ($site->HostRedirect === VirtualHost::REDIRECT_WWW_TO_ROOT) {
                $source = sprintf('www.%s', $apexHost);
                $target = sprintf('%s://%s', $protocol, $apexHost);
            }

            $site->Target = $target;
            $site->Source = $source;

            $serverBlock .= $site->renderWith('Caddy/HostRedirectBlock');
        }

        //If we're in maintenance or coming soon mode, we need to add the additional holding block for the production domain
        if ($site->SiteMode !== VirtualHost::SITE_MODE_PROD) {
            $template = match ($site->SiteMode) {
                VirtualHost::SITE_MODE_COMING => 'Caddy/ComingSoon',
                VirtualHost::SITE_MODE_MAINTENANCE => 'Caddy/Maintenance'
            };
            $serverBlock .= $site->renderWith($template)->forTemplate();
        }


        return $serverBlock;
    }
}
