<?php

namespace DorsetDigital\Caddy\Model;

use DorsetDigital\Caddy\Helper\MySQLDatabaseManager;
use SilverStripe\Admin\CMSEditLinkExtension;
use SilverStripe\Assets\AssetControlExtension;
use SilverStripe\Assets\Shortcodes\FileLinkTracking;
use SilverStripe\CMS\Model\SiteTreeLinkTracking;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Class \DorsetDigital\Caddy\Admin\DBCredentials
 *
 * @property ?string $Title
 * @property ?string $Prefix
 * @property ?string $DBUserName
 * @property ?string $DBPassword
 * @property ?string $DBName
 * @property int $Status
 * @property int $DBServerID
 * @method \DorsetDigital\Caddy\Model\DatabaseServer DBServer()
 * @method \DorsetDigital\Caddy\Model\VirtualHost VirtualHost()
 * @mixin \SilverStripe\Admin\CMSEditLinkExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class DBCredentials extends DataObject
{
    const STATUS_ACTIVE = 1;
    const STATUS_PENDING = 2;
    const STATUS_ERROR = 3;

    private static $table_name = 'DBCredentials';
    private static $singular_name = 'DB Credentials';
    private static $plural_name = 'DB Credentials';
    private static $db = [
        'Title' => 'Varchar(255)',
        'Prefix' => 'Varchar(255)',
        'DBUserName' => 'Varchar(255)',
        'DBPassword' => 'Varchar(255)',
        'DBName' => 'Varchar',
        'Status' => 'Int'
    ];
    private static $has_one = [
        'DBServer' => DatabaseServer::class,
    ];
    private static $belongs_to = [
        'VirtualHost' => VirtualHost::class
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'DBUserName' => 'DB User',
        'VirtualHost.Title' => 'Virtual host',
        'NiceStatus' => 'Status'
    ];
    private static $extensions = [
        CMSEditLinkExtension::class
    ];

    public static function getUnassignedCredentials($currentHostID = 0)
    {
        //Get all the hosts which have creds assigned, but exclude the current host if needed
        //So the creds ID will still appear for it
        $hostIDsWithDB = VirtualHost::get()
            ->filter([
                'DBCredentialsID:GreaterThan' => 0
            ])
            ->exclude('ID', $currentHostID)
            ->column('DBCredentialsID');

        //Ensure the array is not empty so we don't get an error on the exclude()
        $hostIDsWithDB = array_merge($hostIDsWithDB, [0]);

        return self::get()->filter([
            'Status' => self::STATUS_ACTIVE,
        ])->exclude([
            'ID' => $hostIDsWithDB
        ]);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Status', 'DBUserName', 'DBPassword', 'DBName', 'Prefix', 'DBServerID']);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title'),
            TextField::create('Prefix', 'Optional Prefix')
                ->setDescription('This will be added to the DB name and DB username if entered'),
            TextField::create('NiceStatus', 'Status')
                ->setValue($this->getNiceStatus())
                ->setReadonly(true)
        ]);
        if ($this->Status === self::STATUS_PENDING) {
            $fields->addFieldsToTab('Root.Main', [
                CheckboxField::create('CreateLive', 'Create user on production DB')
                    ->setDescription('User will be created when this box is checked, and the record is re-saved'),
                DropdownField::create('DBServerID', 'Database Server', DatabaseServer::get())
            ]);
        }
        if ($this->Status === self::STATUS_ACTIVE) {
            $confCode = strtoupper(uniqid());
            $fields->removeByName(['Prefix']);
            $fields->addFieldsToTab('Root.Main', [
                TextField::create('DBServerURI', 'DB Server')->setValue($this->DBServer()->URI)->setReadonly(true),
                TextField::create('DBName', 'Database name')->setReadonly(true),
                TextField::create('DBUserName', 'Database username')->setReadonly(true),
                TextField::create('DBPassword', 'Database password')->setReadonly(true),
                HeaderField::create('Delete user'),
                CheckboxField::create('DeleteLive', 'Delete user from production DB')
                    ->setDescription('The user will only be removed if this box is checked, and the record is re-saved'),
                TextField::create('ConfCodeConfirm', 'Confirmation Code')
                    ->setDescription('Please enter the following code in the box to confirm deletion: ' . $confCode),
                HiddenField::create('ConfCode')->setValue($confCode)
            ]);
        }

        if ($this->VirtualHost()->ID > 0) {
            $hostName = $this->VirtualHost()->Title;
        } else {
            $hostName = 'Not assigned';
        }

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('VirtualHostDisplay', 'Virtual host')
                ->setReadonly(true)
                ->setValue($hostName)
        ]);

        return $fields;
    }

    public function getNiceStatus()
    {
        if ($this->Status > 0) {
            $opts = $this->getStatusOpts();
            return $opts[$this->Status];
        }
        return null;
    }

    private function getStatusOpts()
    {
        return [
            self::STATUS_ACTIVE => _t(__CLASS__ . '.active', 'Active'),
            self::STATUS_PENDING => _t(__CLASS__ . '.pending', 'Pending'),
            self::STATUS_ERROR => _t(__CLASS__ . '.error', 'Error')
        ];
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->isInDB()) {
            $this->Status = self::STATUS_PENDING;
        } else {
            if (($this->CreateLive) && ($this->Status === self::STATUS_PENDING)) {
                //Add the user to the DB
                $creds = $this->addUserToProduction();

                if ($creds) {
                    $this->DBName = $creds['database'];
                    $this->DBPassword = $creds['password'];
                    $this->DBUserName = $creds['username'];

                    //Set the status to active
                    $this->Status = self::STATUS_ACTIVE;
                } else {
                    throw new ValidationException(
                        'There was an error creating the user on production.  Please see the error logs for details'
                    );
                }
            }
        }

        if ($this->DeleteLive) {
            if ($this->ConfCode !== $this->ConfCodeConfirm) {
                throw new ValidationException(
                    'Please ensure you have entered the confirmation code correctly to delete the user.'
                );
            }

            $dbManager = MySQLDatabaseManager::create();
            $dbManager->setConnectionDetails(
                $this->DBServer()->URI,
                $this->DBServer()->DBUser,
                $this->DBServer()->DBPassword
            );

            $res = $dbManager->deleteUser($this->DBUserName);
            if ($res['success'] !== true) {
                throw new ValidationException($res['message']);
            }
            $res = $dbManager->deleteDatabase($this->DBName);
            if ($res['success'] !== true) {
                throw new ValidationException($res['message']);
            }

            $this->Status = self::STATUS_PENDING;
            $this->DBUserName = null;
            $this->DBPassword = null;
            $this->DBName = null;
        }
    }

    private function addUserToProduction()
    {
        $dbManager = MySQLDatabaseManager::create();
        $dbManager->setConnectionDetails(
            $this->DBServer()->URI,
            $this->DBServer()->DBUser,
            $this->DBServer()->DBPassword
        );

        $result = $dbManager->createDatabaseWithUser($this->Prefix);

        if ($result['success']) {
            return $result['credentials'];
        }

        return false;
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($this->Status === self::STATUS_ACTIVE) {
            throw new ValidationException(
                'You must remove the credentials from production before you can delete this record!'
            );
        }
    }

    public function validate(): ValidationResult
    {
        return parent::validate(); // TODO: Change the autogenerated stub
    }


}
