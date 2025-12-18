<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\SiteConfig\SiteConfig;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

class CaddyHelper
{

    public static function getServerTLSOptions(VirtualHost $site)
    {
        if (($site->TLSMethod === VirtualHost::TLS_AUTO) || (!$site->EnableHTTPS)) {
            return false;
        }

        $opts = [];
        if ($site->TLSMethod === VirtualHost::TLS_LOCAL) {
            $sni = [
                $site->HostName
            ];
            if ($site->HostName !== $site->getCurrentHostName()) {
                $sni[] = $site->getCurrentHostName();
            }
            $opts = [
                "match" => [
                    'sni' => $sni
                ],
                "certificate_selection" => [
                    "policy" => "internal"
                ]
            ];
        }
        if ($site->TLSMethod === VirtualHost::TLS_MANUAL) {
            $opts = [
                "match" => [
                    "sni" => [
                        $site->HostName
                    ]
                ],
                "certificate_selection" => [
                    "policy" => "manual",
                    "certificate_file" => $site->DeployedCertificateFile,
                    "key_file" => $site->DeployedKeyFile
                ]
            ];

        }
        return $opts;
    }


    public static function buildServerBlock(VirtualHost $site)
    {
        $template = match ($site->HostType) {
            VirtualHost::HOST_TYPE_HOST => 'Caddy/Host',
            VirtualHost::HOST_TYPE_REDIRECT => 'Caddy/RedirectHost',
            VirtualHost::HOST_TYPE_PROXY => 'Caddy/ProxyHost',
            VirtualHost::HOST_TYPE_MANUAL => 'Caddy/ManualHost'
        };

        $serverBlock = $site->renderWith($template)->forTemplate();

        //Now add the host-level redirect if needed
        if ($site->HostRedirect !== VirtualHost::REDIRECT_NONE) {
            $protocol = ($site->EnableHTTPS) ? 'https' : 'http';
            $apexHost = preg_replace('/^www\./i', '', $site->HostName);

            if ($site->HostRedirect === VirtualHost::REDIRECT_ROOT_TO_WWW) {
                $source = $apexHost;
                $target = sprintf('%s://www.%s', $protocol, $source);
            } else if ($site->HostRedirect === VirtualHost::REDIRECT_WWW_TO_ROOT) {
                $source = sprintf('www.%s', $apexHost);
                $target = sprintf('%s://%s', $protocol, $apexHost);
            }

            $site->Target = $target;
            $site->Source = $source;

            $serverBlock .= $site->renderWith('Caddy/HostRedirectBlock');
        }

        //If we're in maintenance or coming soon mode, we need to add the additional holding block for the production domain
        if ($site->SiteMode !== VirtualHost::SITE_MODE_PROD) {
            $template = match ($site->SiteMode) {
                VirtualHost::SITE_MODE_COMING => 'Caddy/ComingSoon',
                VirtualHost::SITE_MODE_MAINTENANCE => 'Caddy/Maintenance'
            };
            $serverBlock .= $site->renderWith($template)->forTemplate();
        }


        return $serverBlock;
    }

    /**
     * @param VirtualHost $site
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SilverStripe\ORM\ValidationException
     * @todo Deal with deploying the files to the hosting platform
     */
    public static function deployTLSFiles(VirtualHost $site)
    {
        $config = SiteConfig::current_site_config();
        if (!$config->TLSFilesRoot || !$config->TLSFilesCaddyRoot) {
            throw new \Exception('TLS file root paths are not configured in the settings.');
        }

        if ($site->TLSMethod === VirtualHost::TLS_MANUAL) {
            return self::deployManualTLSFiles($site);
        }

        if ($site->TLSMethod === VirtualHost::TLS_STORED) {
            return self::deployStoredTLSFiles($site);
        }
    }

    private static function deployStoredTLSFiles(VirtualHost $site) {
        if ($site->SSLCertificateID < 1) {
            throw new \Exception('SSL Certificate is not set for '.$site->Title);
        }

        $sslRecord = $site->SSLCertificate();
        $keyData = $sslRecord->TLSKey()->getString();
        $certificateData = $sslRecord->TLSCert()->getString();
        $updateRecord = false;
        $config = SiteConfig::current_site_config();

        $deployedKeyName = sprintf(
            'ssl-%s-%s.key',
            $sslRecord->ID,
            md5($keyData)
        );

        $deployedCertificateName = sprintf(
            'ssl-%s-%s.pem',
            $sslRecord->ID,
            md5($certificateData)
        );

        //Paths to the SSL storage from this machine
        $localKeyPath = rtrim($config->TLSFilesRoot, '/') . '/' . $deployedKeyName;
        $localCertificatePath = rtrim($config->TLSFilesRoot, '/') . '/' . $deployedCertificateName;

        //Paths to the SSL storage for Caddy to use
        $deployedKeyPath = rtrim($config->TLSFilesCaddyRoot, '/') . '/' . $deployedKeyName;
        $deployedCertificatePath = rtrim($config->TLSFilesCaddyRoot, '/') . '/' . $deployedCertificateName;

        //See if the files exist, are already deployed and match the signatures.
        //If not, copy the new cert files to the production store, with their unique filenames
        //And update the SSL record as required
        if (($deployedKeyPath !== $sslRecord->DeployedKeyFile) || (!is_file($localKeyPath))) {
            file_put_contents($localKeyPath, $keyData);
            $sslRecord->DeployedKeyFile = $deployedKeyPath;
            $updateRecord = true;
        }

        if (($deployedCertificatePath !== $sslRecord->DeployedCertificateFile) || (!is_file($localCertificatePath))) {
            file_put_contents($localCertificatePath, $certificateData);
            $sslRecord->DeployedCertificateFile = $deployedCertificatePath;
            $updateRecord = true;
        }

        if ($updateRecord) {
            $sslRecord->write();
        }

    }

    private static function deployManualTLSFiles(VirtualHost $site) {
        if (($site->TLSCertID < 1) || ($site->TLSKeyID < 1)) {
            throw new \Exception('Trying to provision TLS without the correct files');
        }
        $config = SiteConfig::current_site_config();
        $updateSiteRecord = false;

        $siteKeyData = $site->TLSKey()->getString();
        $siteCertificateData = $site->TLSCert()->getString();

        $deployedKeyName = sprintf(
            'site-%s-%s.key',
            $site->ID,
            md5($siteKeyData)
        );

        $deployedCertificateName = sprintf(
            'site-%s-%s.pem',
            $site->ID,
            md5($siteCertificateData)
        );

        $deployedKeyPath = rtrim($config->TLSFilesCaddyRoot, '/') . '/' . $deployedKeyName;
        $localKeyPath = rtrim($config->TLSFilesRoot, '/') . '/' . $deployedKeyName;

        if (($deployedKeyPath !== $site->DeployedKeyFile) || (!is_file($localKeyPath))) {
            Injector::inst()->get(LoggerInterface::class)->info('Private keys do not match or missing, writing new');
            Injector::inst()->get(LoggerInterface::class)->info('Local key path: ' . $localKeyPath);
            file_put_contents($localKeyPath, $siteKeyData);
            $updateSiteRecord = true;
            $site->DeployedKeyFile = $deployedKeyPath;
        }

        $deployedCertificatePath = rtrim($config->TLSFilesCaddyRoot, '/') . '/' . $deployedCertificateName;
        $localCertificatePath = rtrim($config->TLSFilesRoot, '/') . '/' . $deployedCertificateName;
        if (($deployedCertificatePath !== $site->DeployedCertificateFile) || (!is_file($localCertificatePath))) {
            Injector::inst()->get(LoggerInterface::class)->info('Certificate file does not match or missing, writing new');
            Injector::inst()->get(LoggerInterface::class)->info('Local certificate path: ' . $localCertificatePath);
            file_put_contents($localCertificatePath, $siteCertificateData);
            $updateSiteRecord = true;
            $site->DeployedCertificateFile = $deployedCertificatePath;
        }

        if ($updateSiteRecord) {
            $site->write();
        }
    }
}
