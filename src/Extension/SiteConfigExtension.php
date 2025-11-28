<?php

namespace DorsetDigital\Caddy\Extension;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

/**
 * Class \DorsetDigital\Caddy\Extension\SiteConfigExtension
 *
 * @property \SilverStripe\SiteConfig\SiteConfig|\DorsetDigital\Caddy\Extension\SiteConfigExtension $owner
 * @property ?string $RedisHost
 * @property int $RedisPort
 * @property ?string $RedisUser
 * @property ?string $RedisPassword
 * @property ?string $RedisKeyPrefix
 * @property ?string $VirtualHostCaddyRoot
 * @property ?string $VirtualHostPHPRoot
 * @property ?string $PHPCGIIP
 * @property ?string $TLSFilesCaddyRoot
 * @property ?string $TLSFilesRoot
 * @property int $ConfigPollingInterval
 * @property ?string $ConfigURL
 * @property bool $EnableWAF
 * @property bool $IncludeOWASPRules
 * @property ?string $WAFConfigCaddyPath
 * @property int $CorazaConfigID
 * @property int $CoreRuleSetConfigID
 * @method \SilverStripe\Assets\File CorazaConfig()
 * @method \SilverStripe\Assets\File CoreRuleSetConfig()
 */
class SiteConfigExtension extends Extension
{
    private static $db = [
        'RedisHost' => 'Varchar',
        'RedisPort' => 'Int',
        'RedisTLS' => 'Boolean',
        'RedisCluster' => 'Boolean',
        'RedisUser' => 'Varchar',
        'RedisPassword' => 'Varchar',
        'RedisKeyPrefix' => 'Varchar',
        'VirtualHostCaddyRoot' => 'Varchar',
        'VirtualHostPHPRoot' => 'Varchar',
        'PHPCGIIP' => 'Varchar',
        'TLSFilesCaddyRoot' => 'Varchar',
        'TLSFilesRoot' => 'Varchar',
        'ConfigPollingInterval' => 'Int',
        'ConfigURL' => 'Varchar',
        'EnableWAF' => 'Boolean',
        'IncludeOWASPRules' => 'Boolean',
        'WAFConfigCaddyPath' => 'Varchar',
    ];

    private static $has_one = [
        'CorazaConfig' => File::class,
        'CoreRuleSetConfig' => File::class
    ];
    private static $owns = [
        'CorazaConfig',
        'CoreRuleSetConfig'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.CaddyAdmin', [
            LiteralField::create('warning', '<p class="alert-danger p-4 mb-4">Literally every setting on this page can break the entire platform.  Don\'t change anything unless you know what you\'re doing!'),
            HeaderField::create('Redis Server'),
            TextField::create('RedisHost', 'Redis Host Address')
                ->setDescription('Connection will be made via TCP, do not include a protocol'),
            NumericField::create('RedisPort')->setHTML5(true)->setScale(0),
            CheckboxField::create('RedisTLS', 'Use TLS'),
            CheckboxField::create('RedisCluster', 'Use cluster mode'),
            TextField::create('RedisUser', 'Redis Username')
                ->setDescription('Leave blank if not required'),
            TextField::create('RedisPassword', 'Redis Password')
                ->setDescription('Leave blank if not required'),
            TextField::create('RedisKeyPrefix', 'Redis Key Prefix')
                ->setDescription("A random key will be created if you don't add one.   Once set, this should NOT be changed."),
            HeaderField::create('Global settings'),
            TextField::create('PHPCGIIP', 'IP address of PHP CGI cluster'),
            TextField::create('VirtualHostCaddyRoot', 'Caddy Virtualhost root')
                ->setDescription('Absolute path to the virtualhost root inside a Caddy instance'),
            TextField::create('VirtualHostPHPRoot', 'PHP Virtualhost root')
                ->setDescription('Absolute path to the virtualhost root inside a PHP instance'),
            TextField::create('TLSFilesRoot', 'TLS files root')
                ->setDescription('Absolute path to the TLS file storage root on THIS device'),
            TextField::create('TLSFilesCaddyRoot', 'Caddy TLS files root')
                ->setDescription('Absolute path to the TLS file storage root inside a Caddy instance'),
            HeaderField::create('Caddy Config'),
            TextField::create('ConfigURL', 'Config URL')
                ->setDescription('Full URL of the dynamic caddy configuration endpoint'),
            NumericField::create('ConfigPollingInterval', 'Configuration Polling Interval')
                ->setDescription('Number of seconds between automatic configuration polling')
                ->setScale(0),
            HeaderField::create('Firewall Config'),
            CheckboxField::create('EnableWAF', 'Enable WAF functionality'),
            UploadField::create('CorazaConfig', 'Coraza configuration')
                ->setFolderName('WAF'),
            UploadField::create('CoreRuleSetConfig', 'Core rule set configuration')
                ->setFolderName('WAF'),
            CheckboxField::create('IncludeOWASPRules', 'Include OWASP rules'),
            TextField::create('WAFConfigCaddyPath')
                ->setDescription('WAF config files path inside a Caddy instance'),
        ]);
    }

    public function onBeforeWrite()
    {
        if ($this->owner->RedisKeyPrefix == '') {
            $this->owner->RedisKeyPrefix = 'caddy:' . $this->generateRandomString(8);
        }
    }

    private function generateRandomString($length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        $maxIndex = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $maxIndex);
            $randomString .= $characters[$randomIndex];
        }
        return $randomString;
    }
}
