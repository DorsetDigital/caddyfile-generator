<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;

/**
 * Class \DorsetDigital\Caddy\Model\PHPBackend
 *
 * @property ?string $Title
 * @property ?string $URI
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\VirtualHost[] VirtualHosts()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
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
    
    public function getCMSFields() {
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