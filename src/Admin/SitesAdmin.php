<?php

namespace DorsetDigital\Caddy\Admin;

use Colymba\BulkManager\BulkAction\ArchiveHandler;
use Colymba\BulkManager\BulkAction\DeleteHandler;
use Colymba\BulkManager\BulkAction\PublishHandler;
use Colymba\BulkManager\BulkAction\UnlinkHandler;
use Colymba\BulkManager\BulkAction\UnPublishHandler;
use Colymba\BulkManager\BulkManager;
use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldConfig;


/**
 * Class \DorsetDigital\Caddy\Admin\SitesAdmin
 *
 */
class SitesAdmin extends ModelAdmin
{
    private static $managed_models = [
        VirtualHost::class
    ];

    private static $menu_title = 'VirtualHost admin';
    private static $url_segment = 'virtualhosts';
    private static $menu_priority = 100;

    public function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();
        $config->addComponent(BulkManager::create()->setConfig('editableFields', [
            'CacheAssets',
            'EnableWAF',
            'UptimeMonitorEnabled',
            'SiteMode'
        ])
            ->addBulkAction(PublishHandler::class)
            ->addBulkAction(UnpublishHandler::class)
            ->addBulkAction(ArchiveHandler::class)
            ->removeBulkAction(UnlinkHandler::class)
            ->removeBulkAction(DeleteHandler::class)
        );
        return $config;
    }

}
