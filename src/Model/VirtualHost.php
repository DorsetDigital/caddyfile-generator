<?php

namespace DorsetDigital\Caddy\Model;

use DorsetDigital\Caddy\Admin\SitesAdmin;
use DorsetDigital\Caddy\Helper\ENVHelper;
use DorsetDigital\Caddy\Helper\FilesystemHelper;
use Ramsey\Uuid\Uuid;
use SilverStripe\Admin\CMSEditLinkExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\AssetControlExtension;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Shortcodes\FileLinkTracking;
use SilverStripe\CMS\Model\SiteTreeLinkTracking;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedStateExtension;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;
use SilverStripe\View\HTML;
use src\Model\Filesystem;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Class \BiffBangPow\Model\VirtualHost
 *
 * @property int $Version
 * @property ?string $Title
 * @property int $SiteMode
 * @property ?string $HostName
 * @property int $HostType
 * @property bool $EnableHTTPS
 * @property bool $CacheAssets
 * @property bool $AllowWordPressRoutes
 * @property int $TLSMethod
 * @property ?string $DocumentRoot
 * @property ?string $SiteProxy
 * @property ?string $RedirectTo
 * @property ?string $ProxyHost
 * @property bool $EnablePHP
 * @property int $HostRedirect
 * @property ?string $ManualConfig
 * @property ?string $DeployedKeyFile
 * @property ?string $DeployedCertificateFile
 * @property ?string $UpstreamHostHeader
 * @property bool $EnableWAF
 * @property int $RateLimitMode
 * @property int $RateLimitEvents
 * @property int $RateLimitWindow
 * @property bool $EnableGatekeeper
 * @property ?string $GatekeeperProtectedPaths
 * @property bool $RemoveForwardedHeader
 * @property bool $RedirectPaths
 * @property bool $RedirectPermanent
 * @property bool $UptimeMonitorEnabled
 * @property bool $UptimeMonitorUseDefaultInterval
 * @property int $UptimeMonitorIntervalSeconds
 * @property bool $UptimeMonitorUseDefaultFailureThreshold
 * @property int $UptimeMonitorFailureThreshold
 * @property bool $UptimeMonitorDegradedEnabled
 * @property int $UptimeMonitorDegradedThresholdMs
 * @property bool $EnableZeroDowntime
 * @property ?string $DocumentRootSuffix
 * @property bool $AddSilverstripeDBENV
 * @property ?string $CustomConfig
 * @property ?string $ENVSignature
 * @property int $TLSKeyID
 * @property int $TLSCertID
 * @property int $SSLCertificateID
 * @property int $AuthCredentialsID
 * @property int $UptimeMonitorID
 * @property int $PHPBackendID
 * @property int $DBCredentialsID
 * @property int $FilesystemID
 * @method \SilverStripe\Assets\File TLSKey()
 * @method \SilverStripe\Assets\File TLSCert()
 * @method \DorsetDigital\Caddy\Model\SSLCertificate SSLCertificate()
 * @method \DorsetDigital\Caddy\Model\BasicAuthCreds AuthCredentials()
 * @method \DorsetDigital\Caddy\Model\UptimeMonitor UptimeMonitor()
 * @method \DorsetDigital\Caddy\Model\PHPBackend PHPBackend()
 * @method \DorsetDigital\Caddy\Model\DBCredentials DBCredentials()
 * @method \src\Model\Filesystem Filesystem()
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\RedirectRule[] RedirectRules()
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\ENVVar[] ENVVars()
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\GatekeeperAccessRule[] GatekeeperAccessRules()
 * @mixin \SilverStripe\Admin\CMSEditLinkExtension
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\Versioned
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class VirtualHost extends DataObject
{
    const REDIRECT_NONE = 0;
    const REDIRECT_WWW_TO_ROOT = 1;
    const REDIRECT_ROOT_TO_WWW = 2;
    const HOST_TYPE_HOST = 0;
    const HOST_TYPE_REDIRECT = 1;
    const HOST_TYPE_PROXY = 2;
    const HOST_TYPE_MANUAL = 3;
    const TLS_AUTO = 0;
    const TLS_MANUAL = 1;
    const TLS_LOCAL = 2;
    const TLS_STORED = 3;
    const SITE_MODE_COMING = 0;
    const SITE_MODE_MAINTENANCE = 1;
    const SITE_MODE_PROD = 2;
    const RATE_LIMIT_INHERIT = 0;
    const RATE_LIMIT_ENABLED = 1;
    const RATE_LIMIT_DISABLED = 2;
    const CORAZA_CONFIG_FILENAME = 'coraza.conf';
    const CRS_CONFIG_FILENAME = 'crs.conf';
    const CRS_OVERRIDES_CONFIG_FILENAME = 'crs-overrides.conf';

    const HOST_DIRECTORY_MAINTENANCE = '_maintenance';
    const HOST_DIRECTORY_COMINGSOON = '_comingsoon';

    private static $dev_base_domain = 'example.com';

    private static $table_name = 'VirtualHost';
    private static $db = [
        'Title' => 'Varchar',
        'SiteMode' => 'Int',
        'HostName' => 'Varchar',
        'HostType' => 'Int',
        'EnableHTTPS' => 'Boolean',
        'CacheAssets' => 'Boolean',
        'AllowWordPressRoutes' => 'Boolean',
        'TLSMethod' => 'Int',
        'DocumentRoot' => 'Varchar',
        'SiteProxy' => 'Varchar',
        'RedirectTo' => 'Varchar',
        'ProxyHost' => 'Varchar',
        'EnablePHP' => 'Boolean',
        'HostRedirect' => 'Int',
        'ManualConfig' => 'Text',
        'DeployedKeyFile' => 'Varchar',
        'DeployedCertificateFile' => 'Varchar',
        'UpstreamHostHeader' => 'Varchar',
        'EnableWAF' => 'Boolean',
        'RateLimitMode' => 'Int',
        'RateLimitEvents' => 'Int',
        'RateLimitWindow' => 'Int',
        'EnableGatekeeper' => 'Boolean',
        'GatekeeperProtectedPaths' => 'Text',
        'RemoveForwardedHeader' => 'Boolean',
        'RedirectPaths' => 'Boolean',
        'RedirectPermanent' => 'Boolean',
        'UptimeMonitorEnabled' => 'Boolean',
        'UptimeMonitorUseDefaultInterval' => 'Boolean',
        'UptimeMonitorIntervalSeconds' => 'Int',
        'UptimeMonitorUseDefaultFailureThreshold' => 'Boolean',
        'UptimeMonitorFailureThreshold' => 'Int',
        'UptimeMonitorDegradedEnabled' => 'Boolean',
        'UptimeMonitorDegradedThresholdMs' => 'Int',
        'EnableZeroDowntime' => 'Boolean',
        'DocumentRootSuffix' => 'Varchar',
        'AddSilverstripeDBENV' => 'Boolean',
        'CustomConfig' => 'Text',
        'ENVSignature' => 'Varchar'
    ];

    private static $has_one = [
        'TLSKey' => File::class,
        'TLSCert' => File::class,
        'SSLCertificate' => SSLCertificate::class,
        'AuthCredentials' => BasicAuthCreds::class,
        'UptimeMonitor' => UptimeMonitor::class,
        'PHPBackend' => PHPBackend::class,
        'DBCredentials' => DBCredentials::class,
        'Filesystem' => Filesystem::class
    ];

    private static $has_many = [
        'RedirectRules' => RedirectRule::class,
        'ENVVars' => ENVVar::class,
        'GatekeeperAccessRules' => GatekeeperAccessRule::class,
    ];

    private static $owns = [
        'TLSKey',
        'TLSCert'
    ];

    private static $defaults = [
        'EnableHTTPS' => true,
        'EnableZeroDowntime' => true,
        'AllowWordPressRoutes' => false,
        'RateLimitMode' => self::RATE_LIMIT_INHERIT,
        'EnableGatekeeper' => false,
        'GatekeeperProtectedPaths' => "/admin\n/Security",
        'UptimeMonitorUseDefaultInterval' => true,
        'UptimeMonitorUseDefaultFailureThreshold' => true,
        'UptimeMonitorDegradedEnabled' => false,
    ];

    private static $summary_fields = [
        'Title' => 'Site',
        'HostName' => 'Hostname',
        'HostTypeName' => 'Site Type',
        'SiteModeName' => 'Mode',
        'EnableWAF.Nice' => 'WAF',
    ];

    private static $cascade_deletes = [
        'TLSKey',
        'TLSCert',
        'RedirectRules',
        'GatekeeperAccessRules',
    ];

    private static $default_sort = 'Title';

    private static $extensions = [
        Versioned::class,
        CMSEditLinkExtension::class
    ];

    private static $cms_edit_owner = SitesAdmin::class;

    public static function getStandardSites()
    {
        return self::get()->filter([
            'HostType' => self::HOST_TYPE_HOST
        ]);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach (array_keys(self::$db) as $dataField) {
            $fields->removeByName($dataField);
        }
        $fields->removeByName([
            'TLSKey', 'TLSCert', 'SSLCertificateID', 'AuthCredentialsID', 'RedirectRules',
            'UptimeMonitorID', 'PHPBackendID', 'DBCredentialsID', 'ENVVars', 'ENVSignature',
            'FilesystemID', 'GatekeeperAccessRules'
        ]);

        $absoluteRoot = '';
        if ($this->DocumentRoot) {
            $fsHelper = FilesystemHelper::create();
            $absoluteRoot = $fsHelper->getFullHostPath($this);
        }

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            TextField::create('HostName', 'Hostname')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            CheckboxField::create('CacheAssets', 'Cache assets')
                ->setDescription(_t(__CLASS__ . '.CacheAssetsDesc', 'Sets cache control headers for fonts, images and static assets')),
            DropdownField::create('SiteMode', 'Site Mode', $this->getSiteModes()),
            TextField::create('DevDomainURI', 'Dev Domain', $this->getDevURI())
                ->setReadonly(true)
                ->setDescription(_t(__CLASS__ . '.DevDomainDesc', 'The site will be accessible on this URL when not in live mode'))
                ->hideIf('SiteMode')->isEqualTo(self::SITE_MODE_PROD)
                ->orIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            DropdownField::create('HostType', 'Host Type', $this->getHostTypes()),
            CheckboxField::create('EnableHTTPS')
                ->setDescription(_t(__CLASS__ . '.EnableHTTPSDesc', 'Will fetch a certificate and also enables automatic HTTPS redirection'))
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            DropdownField::create('TLSMethod', 'TLS Method', $this->getTLSModes())
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            Wrapper::create(
                UploadField::create('TLSKey', 'SSL Private Key')
                    ->setFolderName('TLSKeys')
                    ->setAllowedExtensions(['key', 'txt'])
            )->hideUnless('TLSMethod')->isEqualTo(self::TLS_MANUAL)->end(),
            Wrapper::create(
                UploadField::create('TLSCert', 'SSL Certificate')
                    ->setFolderName('TLSCerts')
                    ->setDescription('Include server and intermediates in one file if they are needed')
                    ->setAllowedExtensions(['pem', 'txt', 'crt'])
            )->hideUnless('TLSMethod')->isEqualTo(self::TLS_MANUAL)->end(),
            DropdownField::create('SSLCertificateID', 'SSL Certificate', SSLCertificate::get()->map('ID', 'Title'))
                ->setEmptyString('Please select')
                ->hideUnless('TLSMethod')->isEqualTo(self::TLS_STORED)->end(),
            DropdownField::create('FilesystemID', 'Filesystem', Filesystem::get()->map('ID', 'Title'))
            ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            TextField::create('DocumentRoot', 'Document Root')
                ->setDescription('Leave blank for auto-generation (recommended).  Relative to virtualhosts root directory, no leading or trailing slashes.')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            TextField::create('AbsoluteRoot', 'Absolute Root Path')
                ->setValue($absoluteRoot)
                ->setDescription('Absolute path to the root (for deployment)')
                ->setReadonly(true)
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            CheckboxField::create('EnableZeroDowntime', 'Configure for zero downtime deployment')
                ->setDescription('Ensures Caddy is pointing to the correct "current" release directory.  Note:  Changing this once a site has been deployed is almost guaranteed to cause problems!')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            TextField::create('DocumentRootSuffix', 'Document Root Suffix')
                ->setDescription('Adds a suffix to the document root for Caddy, so that files can be served from a directory within the document root (eg. public)')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            CheckboxField::create('EnablePHP', 'Enable PHP')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            DropdownField::create(
                'PHPBackendID',
                'PHP Version',
                PHPBackend::get()->map('ID', 'Title')
            )->setEmptyString('Please select:')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)
                ->andIf('EnablePHP')->isChecked()
                ->end(),
            DropdownField::create('HostRedirect', 'Host-level redirect', $this->getHostRedirectOpts())
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            TextareaField::create('ManualConfig', 'Manual configuration')
                ->setDescription('Manual configuration.  Warning!  No validation is performed!')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_MANUAL)->end(),
            TextField::create('RedirectTo', 'Redirect to')
                ->setDescription('Include protocol.  No trailing slash needed.')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_REDIRECT)->end(),
            CheckboxField::create('RedirectPaths', 'Redirect paths')
                ->setDescription('If checked, will retain URL paths in the redirect, else it will just redirect to the base URL')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_REDIRECT)->end(),
            CheckboxField::create('RedirectPermanent', 'Permanent redirect (301)')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_REDIRECT)->end(),
            TextField::create('ProxyHost', 'Proxy Host')
                ->setDescription('Should contain only the scheme, hostname and port - no trailing slash!')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_PROXY)->end(),
            CheckboxField::create('RemoveForwardedHeader', 'Remove x-forwarded-host header')
                ->setDescription('This removes the header which may cause a 400 response on older droplet / nginx configurations')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_PROXY)->end(),
            TextField::create('UpstreamHostHeader', 'Upstream Host Header value')
                ->setDescription('Leave blank to use the client-supplied value')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_PROXY)->end()
        ]);

        if ($this->HostType == self::HOST_TYPE_HOST) {
            $fields->addFieldsToTab('Root.DatabaseAndEnvironment', [
                DropdownField::create('DBCredentialsID', 'DB Credentials', DBCredentials::getUnassignedCredentials($this->ID))
                    ->setEmptyString('Please select:')
                    ->setDescription('Note: this only shows credentials which are active and are not already assigned')
            ]);
            if ($this->DBCredentialsID > 0) {
                $fields->addFieldsToTab('Root.DatabaseAndEnvironment', [
                    TextField::create('ShowDBServerURI', 'DB Server')
                        ->setValue($this->DBCredentials()->DBServer()->URI)->setReadonly(true),
                    TextField::create('ShowDBName', 'Database name')
                        ->setValue($this->DBCredentials()->DBName)->setReadonly(true),
                    TextField::create('ShowDBUserName', 'Database username')
                        ->setValue($this->DBCredentials()->DBUserName)->setReadonly(true),
                    TextField::create('ShowDBPassword', 'Database password')
                        ->setValue($this->DBCredentials()->DBPassword)->setReadonly(true),
                ]);
            }
            $fields->addFieldsToTab('Root.DatabaseAndEnvironment', [
                HeaderField::create('Environment', 'Environment')
            ]);
            if ($this->DBCredentialsID > 0) {
                $fields->addFieldsToTab('Root.DatabaseAndEnvironment', [
                    CheckboxField::create('AddSilverstripeDBENV', 'Add Silverstripe DB ENV Vars to Server')
                ]);
            }
            $fields->addFieldsToTab('Root.DatabaseAndEnvironment', [
                GridField::create('ENVVars', 'ENV Vars', $this->ENVVars(),
                    GridFieldConfig::create()
                        ->addComponent(GridFieldButtonRow::create('before'))
                        ->addComponent(GridFieldToolbarHeader::create())
                        ->addComponent(GridFieldTitleHeader::create())
                        ->addComponent(GridFieldEditableColumns::create())
                        ->addComponent(GridFieldDeleteAction::create())
                        ->addComponent(GridFieldAddNewInlineButton::create())
                ),
                TextField::create('ENVSignature', 'Environment Signature')->setReadonly(true),
            ]);
        }

        $monitoringConfig = SiteConfig::current_site_config();

        $fields->addFieldsToTab('Root.Monitoring', [
            CheckboxField::create('UptimeMonitorEnabled', 'Enable uptime monitoring')
                ->setDescription('Adds this host to Farpoint uptime monitoring.'),

            HeaderField::create('UptimeAvailabilityMonitoring', 'Availability monitoring'),

            CheckboxField::create(
                'UptimeMonitorUseDefaultInterval',
                sprintf(
                    'Use system default test frequency (%d seconds)',
                    (int) $monitoringConfig->FarpointDefaultIntervalSeconds
                )
            )->displayIf('UptimeMonitorEnabled')->isChecked()->end(),

            Wrapper::create(
                NumericField::create('UptimeMonitorIntervalSeconds', 'Test frequency (seconds)')
                    ->setScale(0)
                    ->setDescription('How often Farpoint should check this host. Minimum 60 seconds.')
            )->displayIf('UptimeMonitorEnabled')->isChecked()
                ->andIf('UptimeMonitorUseDefaultInterval')->isNotChecked()->end(),

            CheckboxField::create(
                'UptimeMonitorUseDefaultFailureThreshold',
                sprintf(
                    'Use system default failure threshold (%d checks)',
                    (int) $monitoringConfig->FarpointDefaultFailureThreshold
                )
            )->displayIf('UptimeMonitorEnabled')->isChecked()->end(),

            Wrapper::create(
                NumericField::create('UptimeMonitorFailureThreshold', 'Failure threshold')
                    ->setScale(0)
                    ->setDescription('Number of consecutive failed checks required before the host is marked DOWN.')
            )->displayIf('UptimeMonitorEnabled')->isChecked()
                ->andIf('UptimeMonitorUseDefaultFailureThreshold')->isNotChecked()->end(),

            HeaderField::create('UptimeDegradedMonitoring', 'Degraded performance monitoring'),

            CheckboxField::create(
                'UptimeMonitorDegradedEnabled',
                'Enable degraded performance monitoring'
            )->displayIf('UptimeMonitorEnabled')->isChecked()->end(),

            Wrapper::create(
                NumericField::create(
                    'UptimeMonitorDegradedThresholdMs',
                    'Degraded threshold (milliseconds)'
                )
                    ->setScale(0)
                    ->setDescription('Response times above this value are considered degraded.')
            )->displayIf('UptimeMonitorEnabled')->isChecked()
                ->andIf('UptimeMonitorDegradedEnabled')->isChecked()->end(),
        ]);

        $securityFields = [
            HeaderField::create('AccessControlSecurity', 'Access Control'),
            DropdownField::create('AuthCredentialsID', 'Auth Access Credentials', BasicAuthCreds::get()->map('ID', 'Title'))
                ->setEmptyString('No auth required')
                ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)
                ->orIf('HostType')->isEqualTo(self::HOST_TYPE_PROXY)->end(),
            HeaderField::create('WordPressSecurity', 'WordPress'),
            CheckboxField::create('AllowWordPressRoutes', 'Allow WordPress routes')
                ->setDescription('Allows requests to common WordPress paths including /wp-admin, /wp-login.php, /wp-content, /wp-includes and /xmlrpc.php. Leave disabled unless this host serves or proxies a WordPress site.')
                ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)
                ->orIf('HostType')->isEqualTo(self::HOST_TYPE_PROXY)->end(),
        ];

        $rateLimitConfig = SiteConfig::current_site_config();
        $securityFields[] = HeaderField::create('RateLimitSecurity', 'Rate Limiting');
        $securityFields[] = DropdownField::create('RateLimitMode', 'Rate limiting', [
            self::RATE_LIMIT_INHERIT => sprintf(
                'Use global setting (%s)',
                $rateLimitConfig->EnableRateLimit ? 'enabled' : 'disabled'
            ),
            self::RATE_LIMIT_ENABLED => 'Enabled',
            self::RATE_LIMIT_DISABLED => 'Disabled',
        ])->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)->end();
        $securityFields[] = NumericField::create('RateLimitEvents', 'Maximum requests')
            ->setDescription(sprintf(
                'Leave blank to use global value: %d',
                (int) $rateLimitConfig->RateLimitEvents
            ))
            ->setScale(0)
            ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)->end();
        $securityFields[] = NumericField::create('RateLimitWindow', 'Window (seconds)')
            ->setDescription(sprintf(
                'Leave blank to use global value: %d seconds',
                (int) $rateLimitConfig->RateLimitWindow
            ))
            ->setScale(0)
            ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)->end();

        if (SiteConfig::current_site_config()->EnableWAF) {
            $securityFields[] = HeaderField::create('WAFSecurity', 'Web Application Firewall');
            $securityFields[] = CheckboxField::create('EnableWAF', 'Enable WAF')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end();
        }

        $fields->addFieldsToTab('Root.Security', $securityFields);

        $gatekeeperConfig = SiteConfig::current_site_config();
        if ($gatekeeperConfig->EnableGatekeeper) {
            $gatekeeperFields = [
                CheckboxField::create('EnableGatekeeper', 'Protect selected paths with Gatekeeper')
                    ->setDescription('Adds Gatekeeper authentication in front of the configured paths. Supported on standard and proxy hosts.')
                    ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)
                    ->orIf('HostType')->isEqualTo(self::HOST_TYPE_PROXY)->end(),
                TextareaField::create('GatekeeperProtectedPaths', 'Protected paths')
                    ->setValue($this->GatekeeperProtectedPaths ?: "/admin\n/Security")
                    ->setRows(5)
                    ->setDescription('One root-relative path per line. Subpaths are protected automatically; for example /admin also protects /admin/*. Raw Caddy matchers and wildcards are not accepted.')
                    ->hideUnless('EnableGatekeeper')->isChecked()->end(),
            ];

            $gatekeeperRules = GridField::create(
                'GatekeeperAccessRules',
                'Authorised email addresses and domains',
                $this->GatekeeperAccessRules(),
                GridFieldConfig_RecordEditor::create()
            );
            $gatekeeperFields[] = Wrapper::create($gatekeeperRules)
                ->displayIf('EnableGatekeeper')->isChecked()->end();

            $fields->addFieldsToTab('Root.Gatekeeper', $gatekeeperFields);
        }

        $fields->addFieldsToTab('Root.History', [
            HistoryViewerField::create('HistoryViewer', 'History Viewer')
        ]);

        $redirectGrid = GridField::create('Redirects', 'Redirects', $this->RedirectRules(),
            GridFieldConfig_RecordEditor::create());

        $fields->addFieldsToTab('Root.Redirects', [
                LiteralField::create('redirectnote',
                    HTML::createTag('p', [
                        'class' => 'alert alert-warning mb-4'
                    ],
                        "Are you sure you should be doing this?   Redirects should generally be added to the application, not to the hosting!  Redirects should be used sparingly and only when absolutely necessary - they use valuable memory in the hosting configuration system.")
                ),
                Wrapper::create($redirectGrid)
                    ->displayUnless('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end()
            ]
        );

        $fields->addFieldsToTab('Root.ExtraConfig', [
            TextareaField::create('CustomConfig', 'Extra config')
            ->setRows(10)
            ->setDescription('This config will be added to the host block.  NOTE: this config will be passed verbatim, make sure you check it is valid!')
        ]);

        return $fields;
    }

    private function getSiteModes()
    {
        return [
            self::SITE_MODE_COMING => _t(__CLASS__ . '.modecoming', 'Coming Soon'),
            self::SITE_MODE_MAINTENANCE => _t(__CLASS__ . '.modemaintenance', 'Maintenance Mode'),
            self::SITE_MODE_PROD => _t(__CLASS__ . '.modeprod', 'Live')
        ];
    }

    private function getDevURI()
    {
        $devDomain = $this->getDevDomain();
        return sprintf('https://%s', $devDomain);
    }

    private function getDevDomain()
    {
        if ($this->HostName) {
            $host = $this->cleanupString($this->HostName);
            $base = $this->config()->get('dev_base_domain');
            return strtolower(sprintf('%s.%s', $host, $base));
        }
    }

    private function cleanupString($in)
    {
        $output = preg_replace('/[^a-zA-Z0-9\s\.-]/', '', $in);
        $output = trim($output);
        $output = preg_replace('/\.+/', '-', $output);
        return preg_replace('/\s+/', '-', $output);
    }

    private function getHostTypes()
    {
        return [
            self::HOST_TYPE_HOST => _t(__CLASS__ . '.host', 'Standard host'),
            self::HOST_TYPE_REDIRECT => _t(__CLASS__ . '.redirecthost', 'Redirect host'),
            self::HOST_TYPE_PROXY => _t(__CLASS__ . '.proxyhost', 'Proxy host'),
            self::HOST_TYPE_MANUAL => _t(__CLASS__ . '.manualhost', 'Manual host configuration')
        ];
    }

    private function getTLSModes()
    {
        return [
            self::TLS_AUTO => _t(__CLASS__ . '.tlsauto', 'Automatic'),
            self::TLS_MANUAL => _t(__CLASS__ . 'tlsmanual', 'Manual certificate'),
            self::TLS_LOCAL => _t(__CLASS__ . '.tlslocal', 'Local / self-signed certificate'),
            self::TLS_STORED => _t(__CLASS__ . '.tlsstored', 'Existing certificate'),
        ];
    }

    private function getHostRedirectOpts()
    {
        return [
            self::REDIRECT_NONE => _t(__CLASS__ . '.noredirect', 'No host redirect'),
            self::REDIRECT_WWW_TO_ROOT => _t(__CLASS__ . '.wwwtoroot', 'Redirect www to root'),
            self::REDIRECT_ROOT_TO_WWW => _t(__CLASS__ . '.roottowww', 'Redirect root to www')
        ];
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $monitoringEnabled = (bool) $this->UptimeMonitorEnabled;
        if ($this->UptimeMonitorID < 1) {
            $monitor = UptimeMonitor::create([
                'Active' => $monitoringEnabled,
            ]);
            $monitor->write();
            $this->UptimeMonitorID = $monitor->ID;
        } else {
            $this->UptimeMonitor()->update([
                'Active' => $monitoringEnabled,
            ])->write();
        }

        if (($this->DocumentRoot == '') && ($this->HostType === self::HOST_TYPE_HOST)) {
            $cleanHost = $this->cleanupString($this->Title);
            $this->DocumentRoot = strtolower(uniqid($cleanHost.'-', false));
        }
        if ($this->DocumentRootSuffix) {
            $this->DocumentRootSuffix = trim($this->DocumentRootSuffix, '/ ');
        }

        if ($this->GatekeeperProtectedPaths) {
            $this->GatekeeperProtectedPaths = implode("\n", $this->getGatekeeperProtectedPathList());
        }
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if ($this->UptimeMonitorID > 0) {
            $this->UptimeMonitor()->update(['Active' => false])->write();
        }
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

    public function getCurrentHostName()
    {
        return ($this->SiteMode === self::SITE_MODE_PROD) ? $this->HostName : $this->getDevDomain();
    }

    public function getBaseURL()
    {
        if ($this->EnableHTTPS || ($this->SiteMode !== self::SITE_MODE_PROD)) {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }

        $hostname = ($this->SiteMode === self::SITE_MODE_PROD) ? $this->HostName : $this->getDevDomain();

        return sprintf('%s://%s', $protocol, $hostname);
    }

    public function getHostTypeName()
    {
        $types = self::getHostTypes();
        return $types[$this->HostType];
    }

    public function getSiteModeName()
    {
        $modes = self::getSiteModes();
        return $modes[$this->SiteMode];
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (($this->HostRedirect == VirtualHost::REDIRECT_WWW_TO_ROOT)
            && (str_starts_with($this->HostName, 'www'))) {
            $result->addError("Cannot redirect www to root if the host begins with www!");
        }

        if (($this->HostRedirect == VirtualHost::REDIRECT_ROOT_TO_WWW)
            && (!str_starts_with($this->HostName, 'www'))) {
            $result->addError("Cannot redirect to www if the host doesn't begin with www");
        }

        $siteCheck = self::get()->filter([
            'HostName' => $this->HostName,
        ])->exclude([
            'ID' => $this->ID,
        ]);

        if ($siteCheck->count() > 0) {
            $result->addError('This host already exists.');
        }

        if (str_starts_with($this->HostName, 'www.')) {
            $apex = substr($this->HostName, strlen('www.'));

            $siteCheck = self::get()->filter([
                'HostName' => $apex,
                'HostRedirect' => VirtualHost::REDIRECT_WWW_TO_ROOT,
            ])->exclude([
                'ID' => $this->ID,
            ]);

            if ($siteCheck->count() > 0) {
                $result->addError(sprintf('The www version of this domain is already covered by %s', $siteCheck->first()->Title));
            }
        }

        if ($this->HostRedirect == VirtualHost::REDIRECT_WWW_TO_ROOT) {
            $siteCheck = self::get()->filter([
                'HostName' => 'www.' . $this->HostName,
            ])->exclude([
                'ID' => $this->ID,
            ]);
            if ($siteCheck->count() > 0) {
                $result->addError(sprintf('The www version of this domain is already covered by %s, so you cannot redirect www to root.', $siteCheck->first()->Title));
            }
        }

        if ($this->HostRedirect == VirtualHost::REDIRECT_ROOT_TO_WWW) {
            $apex = substr($this->HostName, strlen('www.'));

            $siteCheck = self::get()->filter([
                'HostName' => $apex
            ])->exclude([
                'ID' => $this->ID,
            ]);

            if ($siteCheck->count() > 0) {
                $result->addError(sprintf('The root version of this domain is already covered by %s, so you cannot redirect the root to www', $siteCheck->first()->Title));
            }
        }

        if ($this->HostType == VirtualHost::HOST_TYPE_REDIRECT) {
            if ($this->RedirectTo == '') {
                $result->addError("Please add a redirection target");
            } else {
                if ((!str_starts_with($this->RedirectTo, 'http://')) && (!str_starts_with($this->RedirectTo, 'https://'))) {
                    $result->addError("Please make sure the redirection target includes the protocol (http or https)");
                }
            }
        }

        if ($this->HostType == VirtualHost::HOST_TYPE_PROXY) {
            if ($this->ProxyHost == '') {
                $result->addError("Please add a proxy host");
            } else {
                if ((!str_starts_with($this->ProxyHost, 'http://')) && (!str_starts_with($this->ProxyHost, 'https://'))) {
                    $result->addError("Please make sure the proxy host includes the protocol (http or https)");
                }
            }
        }

        if ($this->TLSMethod === self::TLS_MANUAL) {
            if (($this->TLSKeyID < 1) || ($this->TLSCertID < 1)) {
                $result->addError("Please add the required SSL key and certificate files");
            }
        }

        if (($this->TLSMethod === self::TLS_STORED) && ($this->SSLCertificateID < 1)) {
            $result->addError("Please select an SSL certificate from the list");
        }

        if (($this->EnablePHP) && ($this->PHPBackendID < 1)) {
            $result->addError("Please select a PHP version to use");
        }

        if ($this->EnableGatekeeper) {
            if (!in_array((int) $this->HostType, [self::HOST_TYPE_HOST, self::HOST_TYPE_PROXY], true)) {
                $result->addError('Gatekeeper can only be enabled for standard or proxy hosts.');
            }
            if ($this->AuthCredentialsID > 0) {
                $result->addError('Gatekeeper and Basic Auth cannot both be enabled on the same host.');
            }

            $paths = $this->getGatekeeperProtectedPathList();
            if (count($paths) < 1) {
                $result->addError('At least one Gatekeeper protected path is required.');
            }
            foreach ($paths as $path) {
                if (!$this->isValidGatekeeperProtectedPath($path)) {
                    $result->addError(sprintf('Invalid Gatekeeper protected path: %s', $path));
                }
            }
        }

        if ($this->UptimeMonitorEnabled) {
            if (
                !$this->UptimeMonitorUseDefaultInterval
                && (int) $this->UptimeMonitorIntervalSeconds < 60
            ) {
                $result->addError('Test frequency must be at least 60 seconds when overriding the system default.');
            }

            if (
                !$this->UptimeMonitorUseDefaultFailureThreshold
                && (int) $this->UptimeMonitorFailureThreshold < 1
            ) {
                $result->addError('Failure threshold must be at least 1 when overriding the system default.');
            }

            if (
                $this->UptimeMonitorDegradedEnabled
                && (int) $this->UptimeMonitorDegradedThresholdMs < 100
            ) {
                $result->addError(
                    'A degraded performance threshold of at least 100ms must be specified when degraded monitoring is enabled.'
                );
            }
        }

        return $result;
    }

    public function getUptimeMonitorConfig(): array
    {
        $config = SiteConfig::current_site_config();

        $monitorConfig = [
            'interval_seconds' => $this->UptimeMonitorUseDefaultInterval
                ? max(60, (int) $config->FarpointDefaultIntervalSeconds)
                : max(60, (int) $this->UptimeMonitorIntervalSeconds),
            'timeout_ms' => max(1000, (int) $config->FarpointTimeoutMs),
            'expected_status' => (int) $config->FarpointExpectedStatus,
            'min_body_bytes' => max(0, (int) $config->FarpointMinBodyBytes),
            'must_contain' => $this->normaliseUptimeTextRules(
                $config->FarpointMustContain
            ),
            'must_not_contain' => $this->normaliseUptimeTextRules(
                $config->FarpointMustNotContain
            ),
            'degraded' => [
                'enabled' => (bool) $this->UptimeMonitorDegradedEnabled,
                'confirmation_checks' => max(
                    1,
                    (int) $config->FarpointDegradedConfirmationChecks
                ),
            ],
            'failure_confirmation_checks' =>
                $this->UptimeMonitorUseDefaultFailureThreshold
                    ? max(1, (int) $config->FarpointDefaultFailureThreshold)
                    : max(1, (int) $this->UptimeMonitorFailureThreshold),
            'recovery_confirmation_checks' => max(
                1,
                (int) $config->FarpointRecoveryConfirmationChecks
            ),
        ];

        if ($this->UptimeMonitorDegradedEnabled) {
            $monitorConfig['degraded']['threshold_ms'] =
                (int) $this->UptimeMonitorDegradedThresholdMs;
        }

        return $monitorConfig;
    }

    private function normaliseUptimeTextRules(?string $value): array
    {
        $lines = preg_split('/\R/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static function ($line) {
            return $line !== '';
        }));
    }

    public function getTLSConfigValue()
    {
        if ($this->TLSMethod === self::TLS_LOCAL) {
            return 'internal';
        }
        if ($this->TLSMethod === self::TLS_MANUAL) {
            return $this->getTLSCertFile() . " " . $this->getTLSKeyFile();
        }
        if ($this->TLSMethod === self::TLS_STORED) {
            return $this->SSLCertificate()->getTLSConfigValue();
        }
    }

    private function getTLSCertFile()
    {
        return $this->DeployedCertificateFile;
    }

    private function getTLSKeyFile()
    {
        return $this->DeployedKeyFile;
    }

    public function getNeedsTLSConfig()
    {
        if (!$this->EnableHTTPS) {
            return false;
        }
        if (($this->SiteMode === self::SITE_MODE_COMING) || ($this->SiteMode === self::SITE_MODE_MAINTENANCE)) {
            return false;
        }
        return $this->TLSMethod !== self::TLS_AUTO;
    }

    public function getTemporaryNeedsTLSConfig()
    {
        if (!$this->EnableHTTPS) {
            return false;
        }
        return $this->TLSMethod !== self::TLS_AUTO;
    }

    public function getGatekeeperEnabled()
    {
        $config = SiteConfig::current_site_config();
        return (bool) $config->EnableGatekeeper
            && (bool) $this->EnableGatekeeper
            && in_array((int) $this->HostType, [self::HOST_TYPE_HOST, self::HOST_TYPE_PROXY], true);
    }

    public function getGatekeeperAuthUpstream()
    {
        $upstream = trim((string) SiteConfig::current_site_config()->GatekeeperAuthUpstream);
        return $upstream ?: '127.0.0.1:9080';
    }

    public function getGatekeeperProtectedPathList()
    {
        $lines = preg_split('/\R/', (string) $this->GatekeeperProtectedPaths) ?: [];
        $paths = [];

        foreach ($lines as $line) {
            $path = trim($line);
            if ($path === '') {
                continue;
            }
            if ($path !== '/') {
                $path = rtrim($path, '/');
            }
            $paths[$path] = $path;
        }

        return array_values($paths);
    }

    public function getGatekeeperPathMatcher()
    {
        $matches = [];
        foreach ($this->getGatekeeperProtectedPathList() as $path) {
            $matches[] = $path;
            $matches[] = $path === '/' ? '/*' : $path . '/*';
        }

        return implode(' ', array_values(array_unique($matches)));
    }

    private function isValidGatekeeperProtectedPath(string $path): bool
    {
        if (!str_starts_with($path, '/')) {
            return false;
        }
        if (str_starts_with(strtolower($path), '/.gatekeeper')) {
            return false;
        }
        if (preg_match('/[\\s*{}?#]/', $path)) {
            return false;
        }

        return (bool) preg_match('#^/[A-Za-z0-9._~!$&()+,;=:@%/-]*$#', $path);
    }

    public function getRateLimitEnabled()
    {
        return match ((int) $this->RateLimitMode) {
            self::RATE_LIMIT_ENABLED => true,
            self::RATE_LIMIT_DISABLED => false,
            default => (bool) SiteConfig::current_site_config()->EnableRateLimit,
        };
    }

    public function getRateLimitEffectiveEvents()
    {
        if ((int) $this->RateLimitEvents > 0) {
            return (int) $this->RateLimitEvents;
        }
        $global = (int) SiteConfig::current_site_config()->RateLimitEvents;
        return $global > 0 ? $global : 50;
    }

    public function getRateLimitEffectiveWindow()
    {
        if ((int) $this->RateLimitWindow > 0) {
            return (int) $this->RateLimitWindow;
        }
        $global = (int) SiteConfig::current_site_config()->RateLimitWindow;
        return $global > 0 ? $global : 10;
    }

    public function getRateLimitWindowDuration()
    {
        return $this->getRateLimitEffectiveWindow() . 's';
    }

    public function getRateLimitZoneName()
    {
        return 'dynamic_host_' . (int) $this->ID;
    }

    public function getWAFEnabled()
    {
        $config = SiteConfig::current_site_config();
        return ($config->EnableWAF && $this->EnableWAF);
    }

    public function getCorazaConfigFile()
    {
        $config = SiteConfig::current_site_config();
        if ($config->CorazaConfigID > 0) {
            $configPath = ($config->WAFConfigCaddyPath) ? rtrim($config->WAFConfigCaddyPath, '/') . '/' : '';
            return $configPath . self::CORAZA_CONFIG_FILENAME;
        }
        return false;
    }

    public function getCRSConfigFile()
    {
        $config = SiteConfig::current_site_config();
        if ($config->CoreRuleSetConfigID > 0) {
            $configPath = ($config->WAFConfigCaddyPath) ? rtrim($config->WAFConfigCaddyPath, '/') . '/' : '';
            return $configPath . self::CRS_CONFIG_FILENAME;
        }
        return false;
    }

    public function getCRSOverridesConfigFile()
    {
        $config = SiteConfig::current_site_config();
        if ($config->CRSOverridesConfigID > 0) {
            $configPath = ($config->WAFConfigCaddyPath) ? rtrim($config->WAFConfigCaddyPath, '/') . '/' : '';
            return $configPath . self::CRS_OVERRIDES_CONFIG_FILENAME;
        }
        return false;
    }

    public function getCurrentCaddyRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($this->getFilesystemRoot(), '/');

        $docRoot = match ($this->SiteMode) {
            self::SITE_MODE_PROD => $this->DocumentRoot,
            self::SITE_MODE_MAINTENANCE => self::HOST_DIRECTORY_MAINTENANCE,
            self::SITE_MODE_COMING => self::HOST_DIRECTORY_COMINGSOON
        };

        return sprintf('/%s/%s', $basePath, $docRoot);
    }

    public function getIsHTTPSUpstream()
    {
        $usScheme = parse_url($this->ProxyHost, PHP_URL_SCHEME);
        return ($usScheme == 'https');
    }

    public function getCaddyRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($this->getFilesystemRoot(), '/');

        return sprintf('/%s/%s',
            $basePath,
            $this->getComputedDocumentRoot()
        );
    }

    private function getComputedDocumentRoot()
    {
        $rootSuffix = ($this->DocumentRootSuffix) ? '/' . $this->DocumentRootSuffix : null;
        return sprintf('%s%s',
            $this->getBaseDirectory(),
            $rootSuffix
        );
    }

    public function getBaseDirectory()
    {
        $releaseDir = ($this->EnableZeroDowntime) ? '/'.FilesystemHelper::ZDT_SYMLINK_NAME : null;
        return sprintf('%s%s',
            $this->DocumentRoot,
            $releaseDir
        );
    }

    public function getFilesystemRoot() {
        if ($this->FilesystemID > 0) {
            return $this->Filesystem()->BasePath;
        }
        $defaultFS = Filesystem::get()->filter(['DefaultOption' => 1])->first();
        if ($defaultFS) {
            return $defaultFS->BasePath;
        }
    }

    public function getCurrentPHPRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($this->getFilesystemRoot(), '/');

        $docRoot = match ($this->SiteMode) {
            self::SITE_MODE_PROD => $this->getComputedDocumentRoot(),
            self::SITE_MODE_MAINTENANCE => '_maintenance',
            self::SITE_MODE_COMING => '_comingsoon'
        };

        return sprintf('/%s/%s', $basePath, $docRoot);
    }

    public function getPHPRoot()
    {
        $basePath = trim($this->getFilesystemRoot(), '/');
        return sprintf('/%s/%s', $basePath, $this->getComputedDocumentRoot());
    }

    public function getPHPCGIURI()
    {
        return $this->PHPBackend()->URI;
    }

    public function hasEnvironmentVars()
    {
        return ((($this->DBCredentialsID > 0) && ($this->AddSilverstripeDBENV)) || ($this->ENVVars()->count() > 0));
    }

    public function getENVPath() {
        $envDirectory = $this->EnableZeroDowntime
            ? sprintf('%s/%s', $this->DocumentRoot, FilesystemHelper::ZDT_SHARED_DIR_NAME)
            : $this->DocumentRoot;

        return sprintf(
            '%s/%s/.env',
            rtrim($this->getFilesystemRoot(), '/'),
            trim($envDirectory, '/')
        );
    }

}
