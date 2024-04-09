<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Admin\VirtualHost;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \BiffBangPow\Admin\SitesAdmin
 *
 */
class SitesAdmin extends ModelAdmin
{
    private static $managed_models = [
        VirtualHost::class
    ];
    private static $menu_title = 'VirtualHost admin';
    private static $url_segment = 'virtualhost-admin';

}
