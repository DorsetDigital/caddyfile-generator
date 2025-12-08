<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Admin\VirtualHost;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \BiffBangPow\Admin\SitesAdmin
 *
 */
class BasicAuthAdmin extends ModelAdmin
{
    private static $managed_models = [
        BasicAuthCreds::class,
    ];
    private static $menu_title = 'Basic Auth admin';
    private static $url_segment = 'auth-admin';
    private static $menu_priority = 85;

}
