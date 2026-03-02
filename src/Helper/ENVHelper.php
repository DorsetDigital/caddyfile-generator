<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\ENVVar;
use DorsetDigital\Caddy\Model\VirtualHost;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class ENVHelper
{
    use Injectable;
    use Configurable;

    private VirtualHost $site;
    private array $variables;

    /**
     * @param VirtualHost $site
     */
    public function __construct()
    {
        return $this;
    }

    /**
     * @param VirtualHost $site
     * @return ENVHelper
     */
    public function setSite(VirtualHost $site): static
    {
        $this->site = $site;
        $this->variables = [];
        return $this;
    }

    /**
     * @return $this|string
     */
    public function generateENV(): string|static
    {
        if ($this->site->hasEnvironmentVars()) {
            $this->populateSilverstripeDBVars();
            $this->populateSiteEnvironmentVariables();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->getENVContents();
    }

    /**
     * @param $name
     * @return $this
     */
    public function removeVariable($name): static
    {
        if (isset($this->variables[$name])) {
            unset($this->variables[$name]);
        }
        return $this;
    }

    /**
     * @param bool $dryRun
     * @return false|string
     */
    public function writeToFile(bool $dryRun = false): false|string
    {
        if ($this->site->hasEnvironmentVars()) {
            try {
                $fullPath = $this->getAbsoluteENVPath();
                if (!$dryRun) {
                    file_put_contents($fullPath, $this->getENVContents());
                }
                return $fullPath;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * @param bool $dryRun
     * @return $this
     */
    public function cleanUp(bool $dryRun = false): static {
        if (!$this->site->hasEnvironmentVars()) {
            $envPath = $this->getAbsoluteENVPath();
            if ((is_file($envPath)) && (!$dryRun)) {
                unlink($envPath);
            }
        }
        return $this;
    }

    private function getENVContents(): string
    {
        $out = '';
        foreach ($this->variables as $name => $value) {
            $out .= sprintf('%s="%s"%s', $name, $value, PHP_EOL);
        }
        return $out;
    }

    private function getAbsoluteENVPath(): string {
        $path = sprintf('%s/.env', $this->site->getBaseDirectory());
        $fsHelper = FilesystemHelper::create();
        return $fsHelper->getFullHostPath($path);
    }

    private function populateSilverstripeDBVars(): void
    {
        if ($this->site->DBCredentialsID > 0) {
            $this->addVariable('SS_DATABASE_SERVER', $this->site->DBCredentials()->DBServer()->URI);
            $this->addVariable('SS_DATABASE_NAME', $this->site->DBCredentials()->DBName);
            $this->addVariable('SS_DATABASE_USERNAME', $this->site->DBCredentials()->DBUserName);
            $this->addVariable('SS_DATABASE_PASSWORD', $this->site->DBCredentials()->DBPassword);
        }
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function addVariable($name, $value = null): static
    {
        $this->variables[$name] = $value;
        return $this;
    }

    private function populateSiteEnvironmentVariables(): void
    {
        /**
         * @var ENVVar $var
         */
        foreach ($this->site->ENVVars() as $var) {
            $this->addVariable($var->VarName, $var->VarValue);
        }
    }
}