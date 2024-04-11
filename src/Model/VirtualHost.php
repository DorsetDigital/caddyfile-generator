<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Helper\CaddyHelper;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\HTML;

/**
 * Class \BiffBangPow\Model\VirtualHost
 *
 * @property string $Title
 * @property int $HostType
 * @property string $HostName
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
 * @property string $BlockPreview
 * @property int $TLSKeyID
 * @property int $TLSCertID
 * @method \SilverStripe\Assets\File TLSKey()
 * @method \SilverStripe\Assets\File TLSCert()
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


    private static $table_name = 'VirtualHost';
    private static $db = [
        'Title' => 'Varchar',
        'HostType' => 'Int',
        'HostName' => 'Varchar',
        'EnableHTTPS' => 'Boolean',
        'TLSMethod' => 'Int',
        'DocumentRoot' => 'Varchar',
        'SiteProxy' => 'Varchar',
        'RedirectTo' => 'Varchar',
        'ProxyHost' => 'Varchar',
        'EnablePHP' => 'Boolean',
        'PHPVersion' => 'Enum("8.1,8.2,8.3")',
        'HostRedirect' => 'Int',
        'ManualConfig' => 'Text',
        'BlockPreview' => 'Text'
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
        'HostTypeName' => 'Site Type'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach (array_keys(self::$db) as $dataField) {
            $fields->removeByName($dataField);
        }
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Friendly Name'),
            DropdownField::create('HostType', 'Host Type', $this->getHostTypes()),
            TextField::create('HostName', 'Hostname')
                ->hideIf('HostType')->isEqualTo(self::HOST_TYPE_MANUAL)->end(),
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

        if ($this->BlockPreview) {
            $fields->addFieldsToTab('Root.Main', [
                HeaderField::create('Preview (as saved)'),
                LiteralField::create(
                    'blockpreview',
                    HTML::createTag('div', [
                        'class' => 'alert alert-secondary'
                    ],
                        HTML::createTag('pre', [], CaddyHelper::buildServerBlock($this))
                    )
                )
            ]);
        }

        return $fields;
    }

public function onBeforeWrite()
{
    parent::onBeforeWrite();
    $this->BlockPreview = CaddyHelper::buildServerBlock($this);
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


    public function getHostTypeName()
    {
        $types = VirtualHost::getHostTypes();
        return $types[$this->HostType];
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

    public function getCaddyRoot()
    {
        $config = SiteConfig::current_site_config();
        $basePath = trim($config->VirtualHostCaddyRoot, '/');

        return sprintf('/%s/%s', $basePath, $this->DocumentRoot);
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
