<?php

namespace DorsetDigital\Caddy\Client;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

class FarpointClient implements UptimeClientInterface
{
    use Injectable;
    use Configurable;

    private static string $base_url = 'https://farpoint.ddweb.workers.dev/api/v1/';

    private Client $client;
    private string $apiKey;

    public function __construct()
    {
        $apiKey = Environment::getEnv('FARPOINT_API_KEY');

        if (!$apiKey) {
            throw new Exception('Cannot start Farpoint client - FARPOINT_API_KEY is not configured');
        }

        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => rtrim((string) $this->config()->get('base_url'), '/') . '/',
            'timeout' => 15.0,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createMonitor($name, $url, array $options = [])
    {
        $payload = $options;
        $payload['name'] = $name ?: $url;
        $payload['url'] = $url;

        $response = $this->doRequest('POST', 'monitors', $payload);

        return $response['id'] ?? false;
    }

    public function deleteMonitor($monitorID)
    {
        $response = $this->doRequest('DELETE', 'monitors/' . rawurlencode((string) $monitorID));
        return $response !== null;
    }

    public function getMonitor($monitorID)
    {
        return $this->doRequest('GET', 'monitors/' . rawurlencode((string) $monitorID));
    }

    public function updateMonitor($monitorID, $data = [])
    {
        return $this->doRequest(
            'PATCH',
            'monitors/' . rawurlencode((string) $monitorID),
            $data
        );
    }

    public function listMonitors()
    {
        $response = $this->doRequest('GET', 'monitors');
        return $response['monitors'] ?? [];
    }

    public function getDashboard(array $query = [])
    {
        $endpoint = 'dashboard';
        if ($query) {
            $endpoint .= '?' . http_build_query($query);
        }

        return $this->doRequest('GET', $endpoint);
    }

    public function pauseMonitoring()
    {
        return $this->doRequest('POST', 'monitoring/pause');
    }

    public function resumeMonitoring()
    {
        return $this->doRequest('POST', 'monitoring/resume');
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
                return ['success' => true];
            }

            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                throw new Exception('Farpoint returned an invalid JSON response');
            }

            return $decoded;
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';

            $this->getLogger()->error('Farpoint API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $status,
                'response' => $body,
                'error' => $e->getMessage(),
            ]);
        } catch (GuzzleException | Exception $e) {
            $this->getLogger()->error('Farpoint API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getLogger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }
}
