<?php

namespace DorsetDigital\Caddy\Client;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;

class GatekeeperClient
{
    use Injectable;

    private Client $client;

    public function __construct()
    {
        $config = SiteConfig::current_site_config();
        $apiToken = Environment::getEnv('GATEKEEPER_API_TOKEN');

        if (!$apiToken) {
            throw new Exception('Cannot start Gatekeeper client - GATEKEEPER_API_TOKEN is not configured');
        }

        if (!$config->GatekeeperAPIURL) {
            throw new Exception('Cannot start Gatekeeper client - management API URL is not configured');
        }

        $this->client = new Client([
            'base_uri' => rtrim((string) $config->GatekeeperAPIURL, '/') . '/',
            'timeout' => 10.0,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function listSites(): array
    {
        $response = $this->doRequest('GET', 'api/v1/sites');
        return is_array($response) ? $response : [];
    }

    public function putSite(string $siteID, array $data): array
    {
        $response = $this->doRequest(
            'PUT',
            'api/v1/sites/' . rawurlencode($siteID),
            $data
        );

        return is_array($response) ? $response : [];
    }

    public function deleteSite(string $siteID): void
    {
        $this->doRequest(
            'DELETE',
            'api/v1/sites/' . rawurlencode($siteID)
        );
    }

    private function doRequest(string $method, string $endpoint, ?array $data = null)
    {
        try {
            $options = [];

            if ($data !== null) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $body = (string) $response->getBody();

            if ($body === '') {
                return null;
            }

            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                throw new Exception('Gatekeeper returned an invalid JSON response');
            }

            return $decoded;
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $body = $e->hasResponse() ? trim((string) $e->getResponse()->getBody()) : '';

            $this->getLogger()->error('Gatekeeper API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $status,
                'response' => $body,
                'error' => $e->getMessage(),
            ]);

            $message = sprintf(
                'Gatekeeper API request failed%s%s',
                $status ? sprintf(' with HTTP %d', $status) : '',
                $body ? ': ' . $body : ''
            );

            throw new Exception($message, 0, $e);
        } catch (GuzzleException | Exception $e) {
            $this->getLogger()->error('Gatekeeper API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new Exception(
                'Gatekeeper API request failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function getLogger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }
}
