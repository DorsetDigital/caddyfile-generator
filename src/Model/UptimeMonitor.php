<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\Assets\AssetControlExtension;
use SilverStripe\Assets\Shortcodes\FileLinkTracking;
use SilverStripe\CMS\Model\SiteTreeLinkTracking;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Class \DorsetDigital\Caddy\Model\UptimeMonitor
 *
 * @property ?string $MonitorID
 * @property bool $Active
 * @method \DorsetDigital\Caddy\Model\VirtualHost VirtualHost()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class UptimeMonitor extends DataObject
{
    private static $table_name = 'UptimeMonitor';
    private static $db = [
        'MonitorID' => 'Varchar',
        'Active' => 'Boolean',
    ];
    private static $belongs_to = [
        'VirtualHost' => VirtualHost::class,
    ];
}