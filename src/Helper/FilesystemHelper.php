<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;

class FilesystemHelper
{
    use Injectable;

    private $siteConfig;

    public function __construct()
    {
        $this->siteConfig = SiteConfig::current_site_config();
        return $this;
    }

    /**
     * Create any document roots which do not exist and are required
     * @return string
     * @throws \Exception
     */
    public function createNewDocumentRoots() {
        $requiredDirs = $this->getNewHostDirectories();
        if (count($requiredDirs) < 1) {
            return "No new directories to create";
        }
        $created = [];
        foreach ($requiredDirs as $dir) {
            $fullPath = $this->getFullHostPath($dir);
            if ($this->createDirectory($fullPath)) {
                $created[] = $dir;
            }
        }

        return sprintf('Created directories: %s', implode(', ', $created));
    }


    /**
     * Get a list of relative paths for any new document roots which are required
     * @return array
     * @throws \Exception
     */
    public function getNewHostDirectories() {
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

    public function createDirectory(string $dir)
    {
        if (is_dir($dir)) {
            return true;
        }
        try {
            mkdir($dir);
            return true;
        }
        catch (\Exception $e) {
            throw new \Exception("Failed to create directory ".$dir." - ".$e->getMessage());
        }
    }

    /**
     * @param VirtualHost $host
     * @return bool
     * @throws \Exception
     */
    public function checkDirectoryForHost(VirtualHost $host)
    {
        $path = $host->DocumentRoot;
        if (!$path) {
            throw new \Exception('Document root directory is empty in virtualhost');
        }
        return is_dir($this->getFullHostPath($path));
    }

    /**
     * Returns the full path for the given directory.  No trailing slash
     * @param string $directory
     * @return string
     */
    public function getFullHostPath($directory)
    {
        $basePath = $this->siteConfig->VirtualHostLocalRoot;
        return sprintf('%s/%s',
            rtrim($basePath, '/'),
            trim($directory, '/')
        );
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