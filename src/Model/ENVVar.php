<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\Assets\AssetControlExtension;
use SilverStripe\Assets\Shortcodes\FileLinkTracking;
use SilverStripe\CMS\Model\SiteTreeLinkTracking;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Class \DorsetDigital\Caddy\Model\ENVVar
 *
 * @property ?string $VarName
 * @property ?string $VarValue
 * @property int $VirtualHostID
 * @method \DorsetDigital\Caddy\Model\VirtualHost VirtualHost()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class ENVVar extends DataObject
{
    private static $table_name = 'ENVVar';
    private static $db = [
        'VarName' => 'Varchar',
        'VarValue' => 'Varchar'
    ];
    private static $has_one = [
        'VirtualHost' => VirtualHost::class
    ];
    private static $summary_fields = [
        'VarName' => 'Variable',
        'VarValue' => 'Value'
    ];
    private static $singular_name = 'ENV Variable';
    private static $plural_name = 'ENV Variables';
}