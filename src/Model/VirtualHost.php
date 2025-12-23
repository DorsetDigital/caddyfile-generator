<?php

namespace DorsetDigital\Caddy\Model;

use DorsetDigital\Caddy\Admin\SitesAdmin;
use DorsetDigital\Caddy\Helper\FilesystemHelper;
use Ramsey\Uuid\Uuid;
use SilverStripe\Admin\CMSEditLinkExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionedAdmin\Forms\HistoryViewerField;
use SilverStripe\View\HTML;
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
 * @property bool $RemoveForwardedHeader
 * @property bool $RedirectPaths
 * @property bool $RedirectPermanent
 * @property bool $UptimeMonitorEnabled
 * @property bool $EnableZeroDowntime
 * @property ?string $DocumentRootSuffix
 * @property int $TLSKeyID
 * @property int $TLSCertID
 * @property int $SSLCertificateID
 * @property int $AuthCredentialsID
 * @property int $UptimeMonitorID
 * @property int $PHPBackendID
 * @method \SilverStripe\Assets\File TLSKey()
 * @method \SilverStripe\Assets\File TLSCert()
 * @method \DorsetDigital\Caddy\Model\SSLCertificate SSLCertificate()
 * @method \DorsetDigital\Caddy\Model\BasicAuthCreds AuthCredentials()
 * @method \DorsetDigital\Caddy\Model\UptimeMonitor UptimeMonitor()
 * @method \DorsetDigital\Caddy\Model\PHPBackend PHPBackend()
 * @method \SilverStripe\ORM\DataList|\DorsetDigital\Caddy\Model\RedirectRule[] RedirectRules()
 * @mixin \SilverStripe\Versioned\Versioned
 * @mixin \SilverStripe\Admin\CMSEditLinkExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
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
    const CORAZA_CONFIG_FILENAME = 'coraza.conf';
    const CRS_CONFIG_FILENAME = 'crs.conf';

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
        'RemoveForwardedHeader' => 'Boolean',
        'RedirectPaths' => 'Boolean',
        'RedirectPermanent' => 'Boolean',
        'UptimeMonitorEnabled' => 'Boolean',
        'EnableZeroDowntime' => 'Boolean',
        'DocumentRootSuffix' => 'Varchar',
    ];

    private static $has_one = [
        'TLSKey' => File::class,
        'TLSCert' => File::class,
        'SSLCertificate' => SSLCertificate::class,
        'AuthCredentials' => BasicAuthCreds::class,
        'UptimeMonitor' => UptimeMonitor::class,
        'PHPBackend' => PHPBackend::class,
    ];

    private static $has_many = [
        'RedirectRules' => RedirectRule::class,
    ];

    private static $owns = [
        'TLSKey',
        'TLSCert'
    ];

    private static $defaults = [
        'EnableHTTPS' => true,
        'EnableZeroDowntime' => true,
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
        $fields->removeByName(['TLSKey', 'TLSCert', 'SSLCertificateID', 'AuthCredentialsID', 'RedirectRules', 'UptimeMonitorID', 'PHPBackendID']);

        $absoluteRoot = '';
        if ($this->DocumentRoot) {
            $fsHelper = FilesystemHelper::create();
            $absoluteRoot = $fsHelper->getFullHostPath($this->DocumentRoot);
        }

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            TextField::create('HostName', 'Hostname')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
            CheckboxField::create('UptimeMonitorEnabled', 'Add uptime monitoring'),
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
            TextField::create('DocumentRoot', 'Document Root')
                ->setDescription('Leave blank for auto-generation (recommended).  Relative to virtualhosts root directory, no leading or trailing slashes.')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            TextField::create('AbsoluteRoot', 'Absolute Root Path')
                ->setValue($absoluteRoot)
                ->setDescription('Absolute path to the root (for deployment)')
                ->setReadonly(true)
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            CheckboxField::create('EnableZeroDowntime', 'Configure for zero downtime deployment')
                ->setDescription('Ensures Caddy is pointing to the correct "current" release directory')
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

        if (SiteConfig::current_site_config()->EnableWAF) {
            $fields->insertAfter('HostName', CheckboxField::create('EnableWAF', 'Enable WAF')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end());
        }

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('AuthCredentialsID', 'Auth Access Credentials', BasicAuthCreds::get()->map('ID', 'Title'))
                ->setEmptyString('No auth required')
                ->hideUnless('HostType')->isEqualTo(self::HOST_TYPE_HOST)
                ->orIf('HostType')->isEqualTo(self::HOST_TYPE_PROXY)->end(),
        ]);

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
        if ($this->UptimeMonitorID < 1) {
            $enabled = ($this->UptimeMonitorEnabled == true);
            $monitor = UptimeMonitor::create([
                'Active' => $enabled,
            ]);
            $monitor->write();
            $this->UptimeMonitorID = $monitor->ID;
        } else {
            if ($this->UptimeMonitorEnabled != true) {
                $this->UptimeMonitor()->update([
                    'Active' => false,
                ])->write();
            }
        }

        if (($this->DocumentRoot == '') && ($this->HostType === self::HOST_TYPE_HOST)) {
            $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, $this->HostName)->toString();
            $this->DocumentRoot = $uuid;
        }
        if ($this->DocumentRootSuffix) {
            $this->DocumentRootSuffix = trim($this->DocumentRootSuffix, '/ ');
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

        //Check for exact host matches
        $siteCheck = self::get()->filter([
            'HostName' => $this->HostName,
        ])->exclude([
            'ID' => $this->ID,
        ]);

        if ($siteCheck->count() > 0) {
            $result->addError('This host already exists.');
        }

        //If this hostname begins with 'www' check for the apex domain existence along with a www redirect
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

        //If this host has a www redirect, we need to check if the www version of the host is already present
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

        //If this host is a www, and has a root to www redirect, we need to check for the apex domain elsewhere
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

        //Make sure we have PHP set up
        if (($this->EnablePHP) && ($this->PHPBackendID < 1)) {
            $result->addError("Please select a PHP version to use");
        }

        return $result;
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

    /**
     * @return string
     */
    private function getTLSCertFile()
    {
        return $this->DeployedCertificateFile;
    }

    /**
     * @return string
     */
    private function getTLSKeyFile()
    {
        return $this->DeployedKeyFile;
    }

    /**
     * See if we need a TLS config block
     * (only true if we're not in auto mode, and we're not on a maintenance page)
     * @return bool
     */
    public function getNeedsTLSConfig()
    {
        if (!$this->EnableHTTPS) {
            return false;
        }
        //If the site is in coming soon mode, or maintenance mode, then we're dealing with the dev hostname at this point
        //So we can let the auto tls kick in
        if (($this->SiteMode === self::SITE_MODE_COMING) || ($this->SiteMode === self::SITE_MODE_MAINTENANCE)) {
            return false;
        }
        return $this->TLSMethod !== self::TLS_AUTO;
    }

    /**
     * Check to see if we need a TLS config for a production domain that is in a temporary page status
     * This only gets called from the coming soon and maintenance templates which are added for the production domain
     * @return bool
     */
    public function getTemporaryNeedsTLSConfig()
    {
        if (!$this->EnableHTTPS) {
            return false;
        }
        return $this->TLSMethod !== self::TLS_AUTO;
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

    public function getCurrentCaddyRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostCaddyRoot, '/');

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
        $basePath = trim($config->VirtualHostCaddyRoot, '/');

        return sprintf('/%s/%s',
            $basePath,
            $this->getComputedDocumentRoot()
        );
    }

    private function getComputedDocumentRoot()
    {
        $releaseDir = ($this->EnableZeroDowntime) ? '/current' : null;
        $rootSuffix = ($this->DocumentRootSuffix) ? '/' . $this->DocumentRootSuffix : null;
        return sprintf('%s%s%s',
            $this->DocumentRoot,
            $releaseDir,
            $rootSuffix
        );
    }

    public function getCurrentPHPRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostPHPRoot, '/');

        $docRoot = match ($this->SiteMode) {
            self::SITE_MODE_PROD => $this->getComputedDocumentRoot(),
            self::SITE_MODE_MAINTENANCE => '_maintenance',
            self::SITE_MODE_COMING => '_comingsoon'
        };

        return sprintf('/%s/%s', $basePath, $docRoot);
    }

    public function getPHPRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostPHPRoot, '/');
        return sprintf('/%s/%s', $basePath, $this->getComputedDocumentRoot());
    }

    public function getPHPCGIURI()
    {
        return $this->PHPBackend()->URI;
    }

}
