<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Admin\VirtualHost;
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

    public static function getServerOptions(VirtualHost $site)
    {
        $handlers[] = [
            "handler" => "headers",
            "response" => [
                "set" => [
                    "X-Hosting" => [
                        "Biff Bang Pow Advanced Hosting"
                    ]
                ]
            ]
        ];

        if ($site->HostType === VirtualHost::HOST_TYPE_HOST) {
            $handlers[] = [
                "handler" => "file_server",
                "root" => $site->getCaddyRoot()
            ];

            if ($site->EnablePHP) {
                $handlers[] = [
                    "handler" => "subroute",
                    "routes" => [
                        [
                            "handle" => [
                                [
                                    "handler" => "reverse_proxy",
                                    "transport" => [
                                        "protocol" => "fastcgi",
                                        "root" => $site->getPHPRoot()
                                    ],
                                    "upstreams" => [
                                        ["dial" => $site->getPHPCGIURI()]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
            }
        }

        $options = [
            'match' => [
                ["host" => [$site->getCurrentHostName()]]
            ],
            "handle" => $handlers
        ];


        return $options;

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

    public static function deployTLSFiles(VirtualHost $site)
    {
        if ($site->TLSMethod !== VirtualHost::TLS_MANUAL) {
            return;
        }
        if (($site->TLSCertID < 1) || ($site->TLSKeyID < 1)) {
            throw new \Exception('Trying to provision TLS without the correct files');
        }
        $config = SiteConfig::current_site_config();
        if (!$config->TLSFilesRoot || !$config->TLSFilesCaddyRoot) {
            throw new \Exception('TLS file root paths are not set');
        }

        $updateSiteRecord = false;

        $siteKeyData = $site->TLSKey()->getString();
        $siteCertificateData = $site->TLSCert()->getString();

        $deployedKeyName = sprintf(
            '%s-%s.key',
            $site->ID,
            md5($siteKeyData)
        );

        $deployedCertificateName = sprintf(
            '%s-%s.pem',
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
