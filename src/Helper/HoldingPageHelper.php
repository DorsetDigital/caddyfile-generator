<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\VirtualHost;
use RuntimeException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Publish self-contained, per-host holding HTML to the shared NFS mount.
 * No dependency on site releases or their current symlink.
 */
class HoldingPageHelper
{
    use Injectable;

    public function sync(VirtualHost $site): int
    {
        $updated = 0;
        foreach ([
            ['MaintenanceHTML', 'MaintenanceHTMLSignature', 'maintenance'],
            ['ComingSoonHTML', 'ComingSoonHTMLSignature', 'coming-soon'],
        ] as [$field, $signatureField, $directory]) {
            $html = (string) $site->$field;
            $signature = trim($html) !== '' ? md5($html) : null;

            // Clearing the field restores the default Caddy root. Keep the old
            // file for in-flight Caddy configurations, rather than deleting it.
            if ($signature === null) {
                if ($site->$signatureField) {
                    $site->$signatureField = null;
                    $site->write();
                    $site->publishSingle();
                    $updated++;
                }
                continue;
            }

            $root = trim((string) SiteConfig::current_site_config()->HoldingPagesRoot);
            if ($root === '' || $site->ID < 1 || !str_starts_with($root, '/')) {
                throw new RuntimeException('HoldingPagesRoot must be an absolute shared NFS path for custom holding pages');
            }

            $path = rtrim($root, '/') . '/' . $site->ID . '/' . $directory;
            $target = $path . '/index.html';

            if ($site->$signatureField === $signature && is_file($target)) {
                continue;
            }

            if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new RuntimeException('Could not create holding page directory: ' . $path);
            }

            $tmp = tempnam($path, '.holding-');
            if ($tmp === false) {
                throw new RuntimeException('Could not allocate temporary holding page file: ' . $path);
            }

            try {
                if (file_put_contents($tmp, $html) !== strlen($html)) {
                    throw new RuntimeException('Could not write holding page: ' . $target);
                }
                chmod($tmp, 0644);
                if (!rename($tmp, $target)) {
                    throw new RuntimeException('Could not activate holding page: ' . $target);
                }
            } finally {
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }

            $site->$signatureField = $signature;
            $site->write();
            $site->publishSingle();
            $updated++;
        }

        return $updated;
    }
}
