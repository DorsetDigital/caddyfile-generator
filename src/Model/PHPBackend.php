<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\Assets\AssetControlExtension;
use SilverStripe\Assets\Shortcodes\FileLinkTracking;
use SilverStripe\CMS\Model\SiteTreeLinkTracking;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Class \DorsetDigital\Caddy\Model\PHPBackend
 *
 * @property ?string $Title
 * @property ?string $URI
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\VirtualHost[] VirtualHosts()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class PHPBackend extends DataObject
{
    private static $table_name = 'PHPBackend';
    private static $db = [
        'Title' => 'Varchar',
        'URI' => 'Varchar',
    ];
    private static $has_many = [
        'VirtualHosts' => VirtualHost::class,
    ];
    private static $singular_name = 'PHP Backend';
    private static $plural_name = 'PHP Backends';
    private static $default_sort = 'Title ASC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'VirtualHosts',
        ]);

        $virtualHosts = $this->VirtualHosts();
        if ($virtualHosts->exists()) {
            $list = '<p>';
            /**
             * @var VirtualHost $vh
             */
            foreach ($virtualHosts as $vh) {
                $link = $vh->getCMSEditLink();
                $list .= sprintf(
                    '<br><a href="%s">%s</a>',
                    $link,
                    htmlspecialchars($vh->Title)
                );
            }
            $list .= '</p>';

            $fields->addFieldsToTab(
                'Root.Main', [
                    HeaderField::create('Hosts using this backend:'),
                    LiteralField::create('LinkedVirtualHosts', $list)
                ]
            );
        }

        return $fields;
    }
}