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
use SilverStripe\Forms\DropdownField;
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

    public function getSearchContext()
    {
        $context = parent::getSearchContext();

        if ($this->modelClass === VirtualHost::class) {
            $context->getFields()->push(
                DropdownField::create(
                    'q[HostTypeFilter]',
                    'Host Type',
                    [
                        VirtualHost::HOST_TYPE_HOST => 'Standard host',
                        VirtualHost::HOST_TYPE_REDIRECT => 'Redirect host',
                        VirtualHost::HOST_TYPE_PROXY => 'Proxy host',
                        VirtualHost::HOST_TYPE_MANUAL => 'Manual host configuration',
                    ]
                )->setEmptyString('All host types')
            );
        }

        return $context;
    }

    public function getList()
    {
        $list = parent::getList();

        if ($this->modelClass === VirtualHost::class) {
            $params = $this->getRequest()->requestVar('q');
            $hostType = $params['HostTypeFilter'] ?? '';

            if ($hostType !== '' && $hostType !== null) {
                $list = $list->filter('HostType', (int) $hostType);
            }
        }

        return $list;
    }

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
