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
                $fullPath = rtrim($host->getFilesystemRoot(), '/').'/'.$host->DocumentRoot;
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
            //Injector::inst()->get(LoggerInterface::class)->info(sprintf("Checking host %s for %s", $host->HostName, $host->DocumentRoot));
            $dirExists = $this->checkDirectoryForHost($host);
            if (!$dirExists) {
                $dirs[] = $host->DocumentRoot;
            }
        }
        //Injector::inst()->get(LoggerInterface::class)->info(print_r($dirs, true));
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
     * Returns the full path for the given directory.  No trailing slash
     * @param VirtualHost $host
     * @return string
     */
    public function getFullHostPath(VirtualHost $host)
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
     * Check to see if the file system structure is in place for zero-downtime deployments if needed
     * and create it if not
     * @param VirtualHost $site
     * @return bool
     */
    public function checkDeploymentStructure(VirtualHost $site): bool
    {
        if ((!$site->EnableZeroDowntime) || (!$site->DocumentRoot)) {
            return true;
        }

        //See if the "current" directory exists for the site
        //If not, we need to create a system directory and symlink it so deployer can do its thing
        $checkLinkPath = rtrim($site->getFilesystemRoot(), '/') . '/' . self::ZDT_SYMLINK_NAME;
        if ((is_dir($checkLinkPath)) || (is_link($checkLinkPath))) {
            return true;
        }

        $linkTarget = rtrim($site->getFilesystemRoot(), '/') . '/' . self::ZDT_BASEDIR_NAME;
        try {
            mkdir($linkTarget, 0755, true);
            symlink($linkTarget, $checkLinkPath);
            return true;
        }
        catch (Exception $e) {
            return false;
        }


    }

    function sanitiseDirectoryName(string $input, bool $lowercase = true): string
    {
        // Normalise whitespace
        $name = trim($input);
        $name = preg_replace('/\s+/', '-', $name);

        // Remove anything not safe for Linux directory names
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);

        // Collapse multiple dashes
        $name = preg_replace('/-+/', '-', $name);

        // Prevent "." and ".."
        if ($name === '.' || $name === '..') {
            $name = '';
        }

        // Trim leading/trailing dots and dashes
        $name = trim($name, '.-');

        // Lowercase if desired
        if ($lowercase) {
            $name = strtolower($name);
        }

        return $name;
    }


}