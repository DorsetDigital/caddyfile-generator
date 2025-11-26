<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Admin\VirtualHost;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \BiffBangPow\Admin\SitesAdmin
 *
 */
class SSLAdmin extends ModelAdmin
{
    private static $managed_models = [
        SSLCertificate::class,
    ];
    private static $menu_title = 'SSL admin';
    private static $url_segment = 'ssl-admin';
    private static $menu_priority = 95;

}
