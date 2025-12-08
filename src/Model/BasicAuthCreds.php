<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

class BasicAuthCreds extends DataObject
{
    private static $table_name = 'BasicAuthCreds';
    private static $db = [
        'Title' => 'Varchar(255)',
        'Username' => 'Varchar(255)',
        'Password' => 'Varchar(255)',
    ];
    private static $has_many = [
        'VirtualHosts' => VirtualHost::class
    ];
    private static $default_sort = 'Title';
    private static $summary_fields = [
        'Title' => 'Credentials',
        'Username' => 'Username',
        'SitesForCredentials' => 'Sites',
    ];

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($this->VirtualHosts()->exists()) {
            throw new ValidationException(
                'Cannot delete these credentials because there are VirtualHosts using them.'
            );
        }
    }

    public function getSitesForCredentials()
    {
        $sites = $this->VirtualHosts()->column('Title');
        return implode(', ', $sites);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'Title',
            'VirtualHosts',
            'Username',
            'Password',
        ]);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            TextField::create('Username', 'Username'),
            TextField::create('Password', 'Password'),
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
                    HeaderField::create('Hosts using these credentials'),
                    LiteralField::create('LinkedVirtualHosts', $list)
                ]
            );
        }

        return $fields;
    }

    public function getHashedPassword() {
        // Caddy recommends cost 14
        $options = [
            'cost' => 14
        ];

        return password_hash($this->Password, PASSWORD_BCRYPT, $options);
    }
}