<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
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
 * @property ?string $PHPVersion
 * @property int $HostRedirect
 * @property ?string $ManualConfig
 * @property ?string $DeployedKeyFile
 * @property ?string $DeployedCertificateFile
 * @property ?string $UpstreamHostHeader
 * @property bool $EnableWAF
 * @property bool $RemoveForwardedHeader
 * @property int $TLSKeyID
 * @property int $TLSCertID
 * @method \SilverStripe\Assets\File TLSKey()
 * @method \SilverStripe\Assets\File TLSCert()
 * @mixin \SilverStripe\Versioned\Versioned
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
    const PHP_VERSION_PORTS = [
        '8.1' => 9081,
        '8.2' => 9082,
        '8.3' => 9083,
        '8.4' => 9084,
    ];
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
        'PHPVersion' => 'Enum("8.1,8.2,8.3,8.4")',
        'HostRedirect' => 'Int',
        'ManualConfig' => 'Text',
        'DeployedKeyFile' => 'Varchar',
        'DeployedCertificateFile' => 'Varchar',
        'UpstreamHostHeader' => 'Varchar',
        'EnableWAF' => 'Boolean',
        'RemoveForwardedHeader' => 'Boolean',
    ];

    private static $has_one = [
        'TLSKey' => File::class,
        'TLSCert' => File::class,
        'SSLCertificate' => SSLCertificate::class,
    ];

    private static $owns = [
        'TLSKey',
        'TLSCert'
    ];

    private static $defaults = [
        'EnableHTTPS' => true
    ];

    private static $summary_fields = [
        'Title' => 'Site',
        'HostName' => 'Hostname',
        'HostTypeName' => 'Site Type',
        'SiteModeName' => 'Mode'
    ];

    private static $cascade_deletes = [
        'TLSKey',
        'TLSCert'
    ];

    private static $default_sort = 'Title';

    private static $extensions = [
        Versioned::class
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach (array_keys(self::$db) as $dataField) {
            $fields->removeByName($dataField);
        }
        $fields->removeByName(['TLSKey', 'TLSCert', 'SSLCertificateID']);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            TextField::create('HostName', 'Hostname')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
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
            TextField::create('DocumentRoot')
                ->setDescription('Relative to virtualhosts root directory, no leading or trailing slashes')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            CheckboxField::create('EnablePHP', 'Enable PHP')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)->end(),
            DropdownField::create(
                'PHPVersion',
                'PHP Version',
                singleton(VirtualHost::class)->dbObject('PHPVersion')->enumValues()
            )
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_HOST)
                ->andIf('EnablePHP')->isChecked()
                ->end(),
            DropdownField::create('HostRedirect', 'Host-level redirect', $this->getHostRedirectOpts())
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)
                ->orIf('HostType')->isEqualTo(self::HOST_TYPE_REDIRECT)->end(),
            TextareaField::create('ManualConfig', 'Manual configuration')
                ->setDescription('Manual configuration.  Warning!  No validation is performed!')
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_MANUAL)->end(),
            TextField::create('RedirectTo', 'Redirect to')
                ->setDescription('Include protocol.  No trailing slash needed, path redirects will be included')
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
    public function getTemporaryNeedsTLSConfig() {
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
        return sprintf('/%s/%s', $basePath, $this->DocumentRoot);
    }

    public function getCurrentPHPRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostPHPRoot, '/');

        $docRoot = match ($this->SiteMode) {
            self::SITE_MODE_PROD => $this->DocumentRoot,
            self::SITE_MODE_MAINTENANCE => '_maintenance',
            self::SITE_MODE_COMING => '_comingsoon'
        };

        return sprintf('/%s/%s', $basePath, $docRoot);
    }

    public function getPHPRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostPHPRoot, '/');
        return sprintf('/%s/%s', $basePath, $this->DocumentRoot);
    }

    public function getPHPCGIURI()
    {
        $config = SiteConfig::current_site_config();
        return sprintf('%s:%d', $config->PHPCGIIP, $this->getPHPPort());
    }

    private function getPHPPort()
    {
        return self::PHP_VERSION_PORTS[$this->PHPVersion] ?? array_shift(self::PHP_VERSION_PORTS);
    }

}
