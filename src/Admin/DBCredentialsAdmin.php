<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\Admin\ModelAdmin;
use DorsetDigital\Caddy\Model\DBCredentials;

/**
 * Class \DorsetDigital\Caddy\Admin\DBCredentialsAdmin
 *
 */
class DBCredentialsAdmin extends ModelAdmin
{
    private static $managed_models = [
        DBCredentials::class
    ];
    private static $menu_title = 'DB Credentials';
    private static $url_segment = 'db-admin';
    private static $menu_priority = 90;
}
