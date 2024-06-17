<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\Assets\File;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

/**
 * Class \BiffBangPow\Model\VirtualHost
 *
 * @property int $Version
 * @property string $Title
 * @property int $SiteMode
 * @property string $HostName
 * @property int $HostType
 * @property bool $EnableHTTPS
 * @property int $TLSMethod
 * @property string $DocumentRoot
 * @property string $SiteProxy
 * @property string $RedirectTo
 * @property string $ProxyHost
 * @property bool $EnablePHP
 * @property string $PHPVersion
 * @property int $HostRedirect
 * @property string $ManualConfig
 * @property int $TLSKeyID
 * @property int $TLSCertID
 * @method \SilverStripe\Assets\File TLSKey()
 * @method \SilverStripe\Assets\File TLSCert()
 * @mixin \SilverStripe\Versioned\Versioned
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
        '8.3' => 9083
    ];
    const TLS_AUTO = 0;
    const TLS_MANUAL = 1;
    const TLS_LOCAL = 2;
    const SITE_MODE_COMING = 0;
    const SITE_MODE_MAINTENANCE = 1;
    const SITE_MODE_PROD = 2;

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
        'PHPVersion' => 'Enum("8.1,8.2,8.3")',
        'HostRedirect' => 'Int',
        'ManualConfig' => 'Text'
    ];

    private static $has_one = [
        'TLSKey' => File::class,
        'TLSCert' => File::class
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
            TextareaField::create('TLSKey', 'TLS Key')->hideUnless('TLSMethod')
                ->isEqualTo(self::TLS_MANUAL)->end(),
            TextareaField::create('TLSCert', 'TLS Cetificate')
                ->setDescription('Include intermediates here if needed')
                ->hideUnless('TLSMethod')->isEqualTo(self::TLS_MANUAL)->end(),
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
                ->hideUnless('HostType')->isEqualTo(VirtualHost::HOST_TYPE_PROXY)->end()
        ]);

        return $fields;
    }

    public function getCurrentHostName() {
        return ($this->SiteMode === self::SITE_MODE_PROD) ? $this->HostName : $this->getDevDomain();
    }

    public function getBaseURL() {
        if ($this->EnableHTTPS || ($this->SiteMode !== self::SITE_MODE_PROD)) {
            $protocol = 'https';
        }
        else {
            $protocol = 'http';
        }

        $hostname = ($this->SiteMode === self::SITE_MODE_PROD) ? $this->HostName : $this->getDevDomain();

        return sprintf('%s://%s', $protocol, $hostname);
    }


    private function getHostRedirectOpts()
    {
        return [
            self::REDIRECT_NONE => _t(__CLASS__ . '.noredirect', 'No host redirect'),
            self::REDIRECT_WWW_TO_ROOT => _t(__CLASS__ . '.wwwtoroot', 'Redirect www to root'),
            self::REDIRECT_ROOT_TO_WWW => _t(__CLASS__ . '.roottowww', 'Redirect root to www')
        ];
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
            self::TLS_LOCAL => _t(__CLASS__ . '.tlslocal', 'Local / self-signed certificate')
        ];
    }

    private function getSiteModes()
    {
        return [
            self::SITE_MODE_COMING => _t(__CLASS__ . '.modecoming', 'Coming Soon'),
            self::SITE_MODE_MAINTENANCE => _t(__CLASS__ . '.modemaintenance', 'Maintenance Mode'),
            self::SITE_MODE_PROD => _t(__CLASS__ . '.modeprod', 'Live')
        ];
    }

    private function getDevURI() {
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

    public function getHostTypeName()
    {
        $types = self::getHostTypes();
        return $types[$this->HostType];
    }

    public function getSiteModeName() {
        $modes = self::getSiteModes();
        return $modes[$this->SiteMode];
    }

    public function validate()
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

        return $result;
    }

    /**
     * @return string
     * @todo - Implement this function to return the absolute path to the key file
     * Needs to be tied-in to the deployment process
     */
    private function getTLSKeyFile()
    {
        return '/fake/path/to/file';
    }

    /**
     * @return string
     * @todo - Implement this function to return the absolute path to the cert file
     * Needs to be tied-in to the deployment process
     */
    private function getTLSCertFile()
    {
        return '/fake/path/to/file';
    }

    public function getTLSConfigValue()
    {
        if ($this->TLSMethod === self::TLS_LOCAL) {
            return 'internal';
        }
        if ($this->TLSMethod === self::TLS_MANUAL) {
            return $this->getTLSCertFile() . " " . $this->getTLSKeyFile();
        }
    }

    /**
     * See if we need a TLS config block
     * (only true if we're not in auto mode)
     * @return bool
     */
    public function getNeedsTLSConfig()
    {
        return $this->TLSMethod !== self::TLS_AUTO;
    }

    public function getCurrentCaddyRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostCaddyRoot, '/');

        $docRoot = match ($this->SiteMode) {
          self::SITE_MODE_PROD => $this->DocumentRoot,
          self::SITE_MODE_MAINTENANCE => '_maintenance',
          self::SITE_MODE_COMING => '_comingsoon'
        };

        return sprintf('/%s/%s', $basePath, $docRoot);
    }

    public function getCaddyRoot() {
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

    public function getPHPRoot() {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostCaddyRoot, '/');
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
