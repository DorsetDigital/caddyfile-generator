<?php

namespace DorsetDigital\Caddy\Extension;

use DorsetDigital\Caddy\Model\DatabaseServer;
use DorsetDigital\Caddy\Model\PHPBackend;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;
use src\Model\Filesystem;

/**
 * Class \DorsetDigital\Caddy\Extension\SiteConfigExtension
 *
 * @property \SilverStripe\SiteConfig\SiteConfig|\DorsetDigital\Caddy\Extension\SiteConfigExtension $owner
 * @property ?string $RedisHost
 * @property int $RedisPort
 * @property bool $RedisTLS
 * @property bool $RedisCluster
 * @property ?string $RedisUser
 * @property ?string $RedisPassword
 * @property ?string $RedisKeyPrefix
 * @property ?string $TLSFilesCaddyRoot
 * @property ?string $TLSFilesRoot
 * @property int $ConfigPollingInterval
 * @property ?string $ConfigURL
 * @property bool $EnableWAF
 * @property bool $IncludeOWASPRules
 * @property bool $EnableRateLimit
 * @property int $RateLimitEvents
 * @property int $RateLimitWindow
 * @property bool $EnableGatekeeper
 * @property ?string $GatekeeperAuthUpstream
 * @property ?string $GatekeeperAPIURL
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
        'TLSFilesCaddyRoot' => 'Varchar',
        'TLSFilesRoot' => 'Varchar',
        'ConfigPollingInterval' => 'Int',
        'ConfigURL' => 'Varchar',
        'EnableWAF' => 'Boolean',
        'IncludeOWASPRules' => 'Boolean',
        'WAFConfigCaddyPath' => 'Varchar',
        'EnableRateLimit' => 'Boolean',
        'RateLimitEvents' => 'Int',
        'RateLimitWindow' => 'Int',
        'EnableGatekeeper' => 'Boolean',
        'GatekeeperAuthUpstream' => 'Varchar(255)',
        'GatekeeperAPIURL' => 'Varchar(255)',
        'FarpointDefaultIntervalSeconds' => 'Int',
        'FarpointDefaultFailureThreshold' => 'Int',
        'FarpointTimeoutMs' => 'Int',
        'FarpointRecoveryConfirmationChecks' => 'Int',
        'FarpointDegradedConfirmationChecks' => 'Int',
        'FarpointExpectedStatus' => 'Int',
        'FarpointMinBodyBytes' => 'Int',
        'FarpointMustContain' => 'Text',
        'FarpointMustNotContain' => 'Text',
    ];

    private static $defaults = [
        'EnableRateLimit' => false,
        'RateLimitEvents' => 50,
        'RateLimitWindow' => 10,
        'EnableGatekeeper' => false,
        'GatekeeperAuthUpstream' => '127.0.0.1:9080',
        'GatekeeperAPIURL' => 'http://127.0.0.1:9081',
        'FarpointDefaultIntervalSeconds' => 60,
        'FarpointDefaultFailureThreshold' => 2,
        'FarpointTimeoutMs' => 15000,
        'FarpointRecoveryConfirmationChecks' => 2,
        'FarpointDegradedConfirmationChecks' => 2,
        'FarpointExpectedStatus' => 200,
        'FarpointMinBodyBytes' => 256,
    ];

    private static $has_one = [
        'CorazaConfig' => File::class,
        'CoreRuleSetConfig' => File::class,
        'CRSOverridesConfig' => File::class
    ];
    private static $owns = [
        'CorazaConfig',
        'CoreRuleSetConfig',
        'CRSOverridesConfig'
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
            UploadField::create('CRSOverridesConfig', 'CRS overrides configuration')
                ->setFolderName('WAF')
                ->setDescription('Loaded after the OWASP CRS rules for global false-positive exclusions and rule tuning'),
            CheckboxField::create('IncludeOWASPRules', 'Include OWASP rules'),
            TextField::create('WAFConfigCaddyPath')
                ->setDescription('WAF config files path inside a Caddy instance'),
            HeaderField::create('RateLimitConfig', 'Rate Limiting'),
            CheckboxField::create('EnableRateLimit', 'Enable rate limiting by default'),
            NumericField::create('RateLimitEvents', 'Default maximum requests')
                ->setDescription('Maximum matching requests per client IP during the configured window, per Caddy node')
                ->setScale(0),
            NumericField::create('RateLimitWindow', 'Default window (seconds)')
                ->setDescription('Sliding window used for the default rate limit')
                ->setScale(0),
            HeaderField::create('GatekeeperConfig', 'Gatekeeper'),
            CheckboxField::create('EnableGatekeeper', 'Enable Gatekeeper functionality')
                ->setDescription('Makes Gatekeeper available for individual standard and proxy hosts. It does not protect any host automatically.'),
            TextField::create('GatekeeperAuthUpstream', 'Authentication service')
                ->setDescription('Caddy upstream for Gatekeeper authentication and UI traffic. Normally 127.0.0.1:9080.'),
            TextField::create('GatekeeperAPIURL', 'Management API URL')
                ->setDescription('Local management API used to publish per-site Gatekeeper configuration. The bearer token is configured outside the CMS.'),
        ]);

        $fields->addFieldsToTab('Root.Monitoring', [
            HeaderField::create('FarpointDefaults', 'Farpoint defaults'),
            NumericField::create(
                'FarpointDefaultIntervalSeconds',
                'Default test frequency (seconds)'
            )
                ->setScale(0)
                ->setDescription('Used by hosts which are configured to use the system default. Minimum 60 seconds.'),
            NumericField::create(
                'FarpointDefaultFailureThreshold',
                'Default failure threshold'
            )
                ->setScale(0)
                ->setDescription('Consecutive failed checks required before a host is marked DOWN.'),

            HeaderField::create('FarpointRequestSettings', 'Request settings'),
            NumericField::create('FarpointTimeoutMs', 'Request timeout (milliseconds)')
                ->setScale(0),
            NumericField::create('FarpointExpectedStatus', 'Expected HTTP status')
                ->setScale(0),
            NumericField::create('FarpointMinBodyBytes', 'Minimum response body size (bytes)')
                ->setScale(0),
            TextareaField::create('FarpointMustContain', 'Response must contain')
                ->setRows(4)
                ->setDescription('Optional. One required string per line. Applied to all monitored hosts.'),
            TextareaField::create('FarpointMustNotContain', 'Response must not contain')
                ->setRows(4)
                ->setDescription('Optional. One forbidden string per line. Applied to all monitored hosts.'),

            HeaderField::create('FarpointStateSettings', 'State confirmation'),
            NumericField::create(
                'FarpointRecoveryConfirmationChecks',
                'Recovery confirmation checks'
            )
                ->setScale(0)
                ->setDescription('Consecutive healthy checks required before a host recovers to UP.'),
            NumericField::create(
                'FarpointDegradedConfirmationChecks',
                'Degraded confirmation checks'
            )
                ->setScale(0)
                ->setDescription('Consecutive slow checks required before entering DEGRADED. The degraded threshold itself is configured per host.'),
        ]);

        $fields->addFieldsToTab('Root.PHPBackends', [
            GridField::create('PHPBackends', 'PHP Backends', PHPBackend::get(), GridFieldConfig_RecordEditor::create())
        ]);

        $fields->addFieldsToTab('Root.DatabaseServers', [
            GridField::create('DBServers', 'Database Servers', DatabaseServer::get(), GridFieldConfig_RecordEditor::create())
        ]);

        $fields->addFieldsToTab('Root.Filesystems', [
            GridField::create('Filesystems', 'Filesystems', Filesystem::get(), GridFieldConfig_RecordEditor::create())
        ]);
    }

    public function onBeforeWrite()
    {
        if ($this->owner->RedisKeyPrefix == '') {
            $this->owner->RedisKeyPrefix = 'caddy:' . $this->generateRandomString(8);
        }
        if ((int) $this->owner->RateLimitEvents < 1) {
            $this->owner->RateLimitEvents = 50;
        }
        if ((int) $this->owner->RateLimitWindow < 1) {
            $this->owner->RateLimitWindow = 10;
        }
        if (!$this->owner->GatekeeperAuthUpstream) {
            $this->owner->GatekeeperAuthUpstream = '127.0.0.1:9080';
        }
        if (!$this->owner->GatekeeperAPIURL) {
            $this->owner->GatekeeperAPIURL = 'http://127.0.0.1:9081';
        }

        if ((int) $this->owner->FarpointDefaultIntervalSeconds < 60) {
            $this->owner->FarpointDefaultIntervalSeconds = 60;
        }
        if ((int) $this->owner->FarpointDefaultFailureThreshold < 1) {
            $this->owner->FarpointDefaultFailureThreshold = 2;
        }
        if ((int) $this->owner->FarpointTimeoutMs < 1000) {
            $this->owner->FarpointTimeoutMs = 15000;
        }
        if ((int) $this->owner->FarpointRecoveryConfirmationChecks < 1) {
            $this->owner->FarpointRecoveryConfirmationChecks = 2;
        }
        if ((int) $this->owner->FarpointDegradedConfirmationChecks < 1) {
            $this->owner->FarpointDegradedConfirmationChecks = 2;
        }
        if (
            (int) $this->owner->FarpointExpectedStatus < 100
            || (int) $this->owner->FarpointExpectedStatus > 599
        ) {
            $this->owner->FarpointExpectedStatus = 200;
        }
        if ((int) $this->owner->FarpointMinBodyBytes < 0) {
            $this->owner->FarpointMinBodyBytes = 0;
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
