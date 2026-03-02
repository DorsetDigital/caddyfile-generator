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
 * Class \DorsetDigital\Caddy\Model\DatabaseServer
 *
 * @property ?string $Title
 * @property ?string $URI
 * @property ?string $DBUser
 * @property ?string $DBPassword
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\DBCredentials[] DBCredentials()
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class DatabaseServer extends DataObject
{
    private static $table_name = 'DatabaseServer';
    private static $db = [
        'Title' => 'Varchar(255)',
        'URI' => 'Varchar(255)',
        'DBUser' => 'Varchar(255)',
        'DBPassword' => 'Varchar(255)',
    ];
    private static $has_many = [
        'DBCredentials' => DBCredentials::class
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['DBCredentials']);

        $dbCreds = $this->DBCredentials();
        if ($dbCreds->exists()) {
            $list = '<p>';

            /**
             * @var DBCredentials $dbCred
             */
            foreach ($dbCreds as $dbCred) {
                $link = $dbCred->getCMSEditLink();
                $list .= sprintf(
                    '<br><a href="%s">%s</a>',
                    $link,
                    htmlspecialchars($dbCred->Title)
                );
            }
            $list .= '</p>';

            $fields->addFieldsToTab(
                'Root.Main', [
                    HeaderField::create('Databases using this server'),
                    LiteralField::create('LinkedDBs', $list)
                ]
            );
        }

        return $fields;
    }
}