<?php

namespace DorsetDigital\Caddy\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

class CheckMateClient implements UptimeClientInterface
{
    use Injectable;
    use Configurable;

    private static $base_url = 'https://uptime.bbpdev.com/api/v1/';
    private static $default_monitor_options = [
        'type' => 'http',
        'interval' => 60000,
        'isActive' => true,
        'description' => 'Site monitor'
    ];

    private $token;
    private $client;
    private $username;
    private $password;
    private $notifications = null;

    public function __construct()
    {
        $this->username = Environment::getEnv('CHECKMATE_USERNAME');
        $this->password = Environment::getEnv('CHECKMATE_PASSWORD');

        if (empty($this->username) || empty($this->password)) {
            throw new \Exception("Cannot start Checkmate client - missing required credentials from environment");
        }

        $this->client = new Client([
            'base_uri' => $this->config()->get('base_url'),
            'timeout' => 10.0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);

        return $this;
    }

    /**
     * Fetch all team notifications from the API and cache them in the class property
     *
     * @return array Array of notification IDs
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchNotifications()
    {
        try {
            $response = $this->doRequest('GET', 'notifications/team');

            if (isset($response['data']) && is_array($response['data']) && !empty($response['data'])) {
                // Cache the full notification data
                $this->notifications = $response['data'];

                $this->getLogger()->info(
                    'Successfully fetched team notifications',
                    ['count' => count($this->notifications)]
                );

                // Extract and return just the IDs
                return array_map(function($notification) {
                    return $notification['_id'];
                }, $this->notifications);
            }

            $this->getLogger()->info('No team notifications found');
            $this->notifications = [];
            return [];

        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'Failed to fetch team notifications',
                ['error' => $e->getMessage()]
            );
            $this->notifications = [];
            return [];
        }
    }

    /**
     * Get cached notifications or fetch them if not already cached
     *
     * @return array Array of notification IDs
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getNotifications()
    {
        if ($this->notifications === null) {
            return $this->fetchNotifications();
        }

        return array_map(function($notification) {
            return $notification['_id'];
        }, $this->notifications);
    }

    /**
     * Create a monitor with notifications
     *
     * @param string $name Monitor name
     * @param string $url URL to monitor
     * @return string|bool Monitor ID on success, false on failure
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createMonitor($name, $url)
    {
        $monitor = $this->config()->get('default_monitor_options');
        $monitor['name'] = $name ?? $url;
        $monitor['url'] = $url;
        $monitor['description'] = sprintf('%s monitor', $name);

        // Fetch and add all available team notifications
        $notificationIds = $this->getNotifications();
        if (!empty($notificationIds)) {
            $monitor['notifications'] = $notificationIds;
            $this->getLogger()->info(
                'Adding notifications to monitor',
                ['monitor' => $name, 'notification_count' => count($notificationIds)]
            );
        }

        $monitorRes = $this->doRequest('POST', 'monitors', $monitor);

        // API returns success but null data, so we need to fetch all monitors to get the ID
        if (isset($monitorRes['success']) && $monitorRes['success']) {
            $this->getLogger()->info('Monitor created successfully', ['name' => $name, 'url' => $url]);

            // Fetch all monitors and find the one we just created
            try {
                $allMonitors = $this->doRequest('GET', 'monitors');

                if (isset($allMonitors['data']) && is_array($allMonitors['data'])) {
                    // Search in reverse order to find the most recently created monitor with matching URL
                    foreach (array_reverse($allMonitors['data']) as $mon) {
                        if (isset($mon['url']) && $mon['url'] === $url && isset($mon['_id'])) {
                            $this->getLogger()->info(
                                'Retrieved monitor ID after creation',
                                ['id' => $mon['_id'], 'url' => $url]
                            );
                            return $mon['_id'];
                        }
                    }
                }

                $this->getLogger()->warning(
                    'Monitor created but ID could not be retrieved',
                    ['url' => $url]
                );
                return true;

            } catch (\Exception $e) {
                $this->getLogger()->error(
                    'Failed to retrieve monitor ID after creation',
                    ['error' => $e->getMessage(), 'url' => $url]
                );
                return true;
            }
        }

        $this->getLogger()->error('Monitor creation failed', ['name' => $name, 'url' => $url]);
        return false;
    }

    /**
     * Delete a monitor
     *
     * @param string $monitorID Monitor ID to delete
     * @return bool True on success
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteMonitor($monitorID)
    {
        $response = $this->doRequest('DELETE', 'monitors/' . $monitorID);
        $success = isset($response['success']) && $response['success'];

        if ($success) {
            $this->getLogger()->info('Monitor deleted successfully', ['id' => $monitorID]);
        } else {
            $this->getLogger()->error('Monitor deletion failed', ['id' => $monitorID]);
        }

        return $success;
    }

    /**
     * Update a monitor
     *
     * @param string $monitorID Monitor ID to update
     * @param array $data Data to update
     * @return mixed API response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateMonitor($monitorID, $data = [])
    {
        $this->getLogger()->info('Updating monitor', ['id' => $monitorID]);
        return $this->doRequest('PATCH', 'monitors/' . $monitorID, $data);
    }

    /**
     * Get a specific monitor
     *
     * @param string $monitorID Monitor ID to retrieve
     * @return mixed API response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMonitor($monitorID)
    {
        return $this->doRequest('GET', 'monitors/' . $monitorID);
    }

    /**
     * Get authentication token, fetching from server if needed
     *
     * @return string Auth token
     */
    private function getToken()
    {
        if (!$this->token) {
            $this->getAuthTokenFromServer();
        }
        return $this->token;
    }

    /**
     * Authenticate with the API and retrieve an auth token
     *
     * @return bool True on success
     * @throws \Exception
     */
    private function getAuthTokenFromServer()
    {
        try {
            $response = $this->client->post('auth/login', [
                'json' => [
                    'email' => $this->username,
                    'password' => $this->password
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['data']['token'])) {
                $this->token = $data['data']['token'];
                $this->getLogger()->info('Successfully authenticated with Checkmate API');
                return true;
            }

            throw new \Exception('Login failed: No token received in response');

        } catch (RequestException $e) {
            $this->getLogger()->error('Authentication failed', ['error' => $e->getMessage()]);
            throw new \Exception('Login failed: ' . $e->getMessage());
        }
    }

    /**
     * Make an authenticated request to the API
     *
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $endpoint API endpoint
     * @param array|null $data Request data for POST/PATCH/PUT
     * @return mixed|array Decoded API response
     */
    private function doRequest($method, $endpoint, $data = null)
    {
        try {
            $options = [];
            $token = $this->getToken();

            if ($token) {
                $options['headers'] = [
                    'Authorization' => 'Bearer ' . $token,
                ];
            }

            // Add JSON body for POST/PUT/PATCH requests
            if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            // Handle empty responses (like for DELETE)
            if (empty($body)) {
                return ['success' => true];
            }

            $decoded = json_decode($body, true);

            // Check if we got HTML instead of JSON (likely a wrong endpoint)
            if ($decoded === null && stripos($body, '<!doctype html>') !== false) {
                $this->getLogger()->error(
                    'Received HTML response instead of JSON',
                    ['endpoint' => $endpoint, 'method' => $method]
                );
                throw new \Exception("Invalid endpoint or authentication error: {$endpoint}");
            }

            return $decoded;

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

            $this->getLogger()->error(
                'API request failed',
                [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $statusCode,
                    'error' => $errorBody ?: $e->getMessage()
                ]
            );
        }

        return null;
    }

    /**
     * Get the logger instance
     *
     * @return LoggerInterface
     */
    private function getLogger()
    {
        return Injector::inst()->get(LoggerInterface::class);
    }
}