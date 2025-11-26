<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

class SSLCertificate extends DataObject
{
    private static $table_name = 'SSLCertificate';
    private static $singular_name = 'SSL Certificate';
    private static $plural_name = 'SSL Certificates';
    private static $db = [
        'Title' => 'Varchar(255)',
        'DeployedKeyFile' => 'Varchar',
        'DeployedCertificateFile' => 'Varchar',
    ];
    private static $has_one = [
        'TLSKey' => File::class,
        'TLSCert' => File::class
    ];
    private static $has_many = [
        'VirtualHosts' => VirtualHost::class
    ];
    private static $owns = [
        'TLSKey',
        'TLSCert'
    ];
    private static $cascade_deletes = [
        'TLSKey',
        'TLSCert'
    ];
    private static $default_sort = 'Title';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'DeployedKeyFile',
            'DeployedCertificateFile',
            'Title',
            'VirtualHosts',
        ]);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            UploadField::create('TLSKey', 'SSL Private Key')
                ->setFolderName('TLSKeys')
                ->setAllowedExtensions(['key', 'txt']),
            UploadField::create('TLSCert', 'SSL Certificate')
                ->setFolderName('TLSCerts')
                ->setDescription('Include server and intermediates in one file if they are needed')
                ->setAllowedExtensions(['pem', 'txt', 'crt'])

        ]);
        return $fields;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->TLSKeyID > 0) {
            $this->TLSKey()->protectFile();
        }
        if ($this->TLSCertID > 0) {
            $this->TLSCert()->protectFile();
        }
    }

    public function getTLSConfigValue()
    {
        return $this->getTLSCertFile() . " " . $this->getTLSKeyFile();
    }


    /**
     * @return string
     * @todo - Implement this function to return the absolute path to the cert file
     * Needs to be tied-in to the deployment process
     */
    private function getTLSCertFile()
    {
        return $this->DeployedCertificateFile;
    }

    /**
     * @return string
     * @todo - Implement this function to return the absolute path to the key file
     * Needs to be tied-in to the deployment process
     */
    private function getTLSKeyFile()
    {
        return $this->DeployedKeyFile;
    }
}