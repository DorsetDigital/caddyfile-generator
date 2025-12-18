<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;



class SitesAdmin extends ModelAdmin
{
    private static $managed_models = [
        VirtualHost::class
    ];

    private static $menu_title = 'VirtualHost admin';
    private static $url_segment = 'virtualhosts';
    private static $menu_priority = 100;

}
