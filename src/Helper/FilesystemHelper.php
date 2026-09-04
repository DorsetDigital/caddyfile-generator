<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\VirtualHost;
use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SiteConfig\SiteConfig;

class FilesystemHelper
{
    use Injectable;

    const ZDT_SYMLINK_NAME = 'current';
    const ZDT_BASEDIR_NAME = 'basedeploy';
    const ZDT_SHARED_DIR_NAME = 'shared';

    private $siteConfig;

    public function __construct()
    {
        $this->siteConfig = SiteConfig::current_site_config();
        return $this;
    }

    /**
     * Create any document roots which do not exist and are required
     * @return string
     * @throws Exception
     */
    public function createNewDocumentRoots()
    {
        $created = [];
        $hosts = VirtualHost::getStandardSites();
        /**
         * @var VirtualHost $host
         */
        foreach ($hosts as $host) {
            $dirExists = $this->checkDirectoryForHost($host);
            if (!$dirExists) {
                $fullPath = $this->getFullHostPath($host);
                if ($this->createDirectory($fullPath)) {
                    $created[] = $fullPath;
                }
            }
        }

        if (count($created) < 1) {
            return "No directories created";
        }

        return sprintf('Created directories: %s', implode(', ', $created));
    }


    /**
     * Get a list of relative paths for any new document roots which are required
     * @return array
     * @throws Exception
     */
    public function getNewHostDirectories()
    {
        $dirs = [];
        $hosts = VirtualHost::getStandardSites();
        foreach ($hosts as $host) {
            $dirExists = $this->checkDirectoryForHost($host);
            if (!$dirExists) {
                $dirs[] = $this->getFullHostPath($host);
            }
        }
        return $dirs;
    }

    /**
     * @param VirtualHost $host
     * @return bool
     * @throws Exception
     */
    public function checkDirectoryForHost(VirtualHost $host)
    {
        if (!$host->DocumentRoot) {
            throw new Exception('Document root directory is empty in virtualhost');
        }
        return is_dir($this->getFullHostPath($host));
    }

    /**
     * Returns the full path for the given host document root. No trailing slash.
     */
    public function getFullHostPath(VirtualHost $host): string
    {
        $basePath = $host->getFilesystemRoot();
        return sprintf('%s/%s',
            rtrim($basePath, '/'),
            trim($host->DocumentRoot, '/')
        );
    }

    public function createDirectory(string $dir)
    {
        if (is_dir($dir)) {
            return true;
        }
        try {
            mkdir($dir, 0755, true);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to create directory " . $dir . " - " . $e->getMessage());
        }
    }

    /**
     * Ensure the filesystem structure required for deployment exists.
     *
     * Zero-downtime sites use:
     *   <document root>/current -> <document root>/basedeploy
     *   <document root>/shared
     *
     * If a document root suffix is configured, ensure it exists beneath the
     * directory Caddy will actually serve from.
     */
    public function checkDeploymentStructure(VirtualHost $site): bool
    {
        if (!$site->DocumentRoot) {
            return true;
        }

        $siteRoot = $this->getFullHostPath($site);

        try {
            $this->createDirectory($siteRoot);

            $deploymentRoot = $siteRoot;

            if ($site->EnableZeroDowntime) {
                $linkTarget = $siteRoot . '/' . self::ZDT_BASEDIR_NAME;
                $checkLinkPath = $siteRoot . '/' . self::ZDT_SYMLINK_NAME;
                $sharedPath = $siteRoot . '/' . self::ZDT_SHARED_DIR_NAME;

                $this->createDirectory($linkTarget);
                $this->createDirectory($sharedPath);

                if (!is_dir($checkLinkPath) && !is_link($checkLinkPath)) {
                    symlink($linkTarget, $checkLinkPath);
                }

                $deploymentRoot = $checkLinkPath;
            }

            if ($site->DocumentRootSuffix) {
                $this->createDirectory(
                    rtrim($deploymentRoot, '/') . '/' . trim($site->DocumentRootSuffix, '/')
                );
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ensure a zero-downtime site's .env symlink points at the shared copy.
     */
    public function ensureENVLink(VirtualHost $site): bool
    {
        if (!$site->EnableZeroDowntime || !$site->DocumentRoot) {
            return true;
        }

        $siteRoot = $this->getFullHostPath($site);
        $sharedEnv = $siteRoot . '/' . self::ZDT_SHARED_DIR_NAME . '/.env';
        $currentEnv = $siteRoot . '/' . self::ZDT_SYMLINK_NAME . '/.env';

        if (is_link($currentEnv)) {
            return true;
        }

        if (file_exists($currentEnv)) {
            return false;
        }

        try {
            return symlink($sharedEnv, $currentEnv);
        } catch (Exception $e) {
            return false;
        }
    }

    function sanitiseDirectoryName(string $input, bool $lowercase = true): string
    {
        $name = trim($input);
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);

        if ($name === '.' || $name === '..') {
            $name = '';
        }

        $name = trim($name, '.-');

        if ($lowercase) {
            $name = strtolower($name);
        }

        return $name;
    }
}
