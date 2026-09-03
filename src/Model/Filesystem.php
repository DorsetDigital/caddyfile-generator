<?php

namespace src\Model;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\ORM\DataObject;

/**
 * Class \src\Model\Filesystem
 *
 * @property ?string $Title
 * @property ?string $BasePath
 * @property bool $DefaultOption
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\VirtualHost[] Hosts()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Filesystem extends DataObject
{
    private static $table_name = 'HostFilesystem';
    private static $db = [
        'Title' => 'Varchar(255)',
        'BasePath' => 'Varchar(255)',
        'DefaultOption' => 'Boolean',
    ];

    private static $has_many = [
        'Hosts' => VirtualHost::class,
    ];
}