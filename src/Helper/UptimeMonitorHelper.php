<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Client\FarpointClient;
use DorsetDigital\Caddy\Client\UptimeClientInterface;
use DorsetDigital\Caddy\Model\UptimeMonitor;
use DorsetDigital\Caddy\Model\VirtualHost;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;

class UptimeMonitorHelper
{
    use Injectable;

    private UptimeClientInterface $client;

    public function __construct(UptimeClientInterface $client)
    {
        $this->client = $client;
    }

    public function cleanUpMonitors(): string
    {
        $messages = [];
        $monitors = $this->getRetiredMonitors();

        if ($monitors->count() < 1) {
            $messages[] = 'No monitors found to clean up.';
        }

        foreach ($monitors as $monitor) {
            $monitorID = (string) $monitor->MonitorID;

            if (!Uuid::isValid($monitorID)) {
                $monitor->update(['MonitorID' => null])->write();
                $messages[] = sprintf('Legacy monitor ID %s was cleared.', $monitorID);
                continue;
            }

            if ($this->deleteMonitor($monitorID)) {
                $monitor->update(['MonitorID' => null])->write();
                $messages[] = sprintf('Monitor ID %s was deleted.', $monitorID);
            } else {
                $messages[] = sprintf('Failed to delete Monitor ID %s.', $monitorID);
            }
        }

        return implode("\n", $messages);
    }

    public function getRetiredMonitors()
    {
        return UptimeMonitor::get()->filter([
            'Active' => 0,
            'MonitorID:Not' => null,
        ]);
    }

    public function deleteMonitor($monitorID)
    {
        return $this->client->deleteMonitor($monitorID);
    }

    public function addNewMonitors(): string
    {
        $messages = [];
        $required = $this->getRequiredMonitors();

        if ($required->count() < 1) {
            $messages[] = 'No uptime monitors to create';
        }

        /** @var UptimeMonitor $monitor */
        foreach ($required as $monitor) {
            $site = $monitor->VirtualHost();

            if (!$site || !$site->exists()) {
                $messages[] = sprintf(
                    'Uptime monitor record %d has no VirtualHost and was skipped.',
                    $monitor->ID
                );
                continue;
            }

            $payload = $this->buildMonitorPayload($site);
            $monitorID = $this->createMonitor(
                $payload['name'],
                $payload['url'],
                $this->monitorOptions($payload)
            );

            if ($monitorID) {
                $monitor->update(['MonitorID' => $monitorID])->write();
                $messages[] = sprintf(
                    'Created monitor for %s, ID: %s',
                    $site->HostName,
                    $monitorID
                );
            } else {
                $messages[] = sprintf(
                    'Failed to create monitor for %s.',
                    $site->HostName
                );
            }
        }

        return implode("\n", $messages);
    }

    /**
     * Returns active monitors which do not yet have a Farpoint UUID.
     *
     * Legacy external monitor IDs are treated as unconfigured so they are
     * recreated in Farpoint on the next deployment or forced sync.
     */
    public function getRequiredMonitors()
    {
        $required = ArrayList::create();

        foreach (UptimeMonitor::get()->filter(['Active' => 1]) as $monitor) {
            if (!$monitor->MonitorID || !Uuid::isValid((string) $monitor->MonitorID)) {
                $required->push($monitor);
            }
        }

        return $required;
    }

    public function createMonitor($name, $url, array $options = [])
    {
        return $this->client->createMonitor($name, $url, $options);
    }

    public function getMonitor($monitorID)
    {
        return $this->client->getMonitor($monitorID);
    }

    public function updateMonitor($monitorID, array $data = [])
    {
        return $this->client->updateMonitor($monitorID, $data);
    }

    public function syncFarpoint(): string
    {
        if (!$this->client instanceof FarpointClient) {
            return 'Farpoint sync is unavailable because the configured uptime client is not Farpoint.';
        }

        $messages = [];
        $remoteMonitors = $this->client->listMonitors();
        $remoteById = [];

        foreach ($remoteMonitors as $remote) {
            if (!empty($remote['id'])) {
                $remoteById[$remote['id']] = $remote;
            }
        }

        $claimedRemoteIds = [];

        /** @var UptimeMonitor $monitor */
        foreach (UptimeMonitor::get() as $monitor) {
            $site = $monitor->VirtualHost();

            if (!$site || !$site->exists()) {
                continue;
            }

            $monitorID = (string) $monitor->MonitorID;

            if (!$monitor->Active) {
                if (Uuid::isValid($monitorID) && isset($remoteById[$monitorID])) {
                    if ($this->deleteMonitor($monitorID)) {
                        $messages[] = sprintf(
                            'Deleted disabled monitor for %s.',
                            $site->HostName
                        );
                    } else {
                        $messages[] = sprintf(
                            'Failed to delete disabled monitor for %s.',
                            $site->HostName
                        );
                        $claimedRemoteIds[$monitorID] = true;
                        continue;
                    }
                }

                if ($monitorID !== '') {
                    $monitor->update(['MonitorID' => null])->write();
                }

                continue;
            }

            $payload = $this->buildMonitorPayload($site);

            if (Uuid::isValid($monitorID) && isset($remoteById[$monitorID])) {
                $updated = $this->updateMonitor($monitorID, $payload);

                if ($updated) {
                    $messages[] = sprintf(
                        'Updated monitor for %s.',
                        $site->HostName
                    );
                } else {
                    $messages[] = sprintf(
                        'Failed to update monitor for %s.',
                        $site->HostName
                    );
                }

                $claimedRemoteIds[$monitorID] = true;
                continue;
            }

            $newID = $this->createMonitor(
                $payload['name'],
                $payload['url'],
                $this->monitorOptions($payload)
            );

            if ($newID) {
                $monitor->update(['MonitorID' => $newID])->write();
                $claimedRemoteIds[$newID] = true;
                $messages[] = sprintf(
                    'Created monitor for %s, ID: %s.',
                    $site->HostName,
                    $newID
                );
            } else {
                $messages[] = sprintf(
                    'Failed to create monitor for %s.',
                    $site->HostName
                );
            }
        }

        foreach ($remoteById as $remoteID => $remote) {
            if (isset($claimedRemoteIds[$remoteID])) {
                continue;
            }

            if ($this->deleteMonitor($remoteID)) {
                $messages[] = sprintf(
                    'Deleted orphaned Farpoint monitor %s.',
                    $remote['name'] ?? $remoteID
                );
            } else {
                $messages[] = sprintf(
                    'Failed to delete orphaned Farpoint monitor %s.',
                    $remote['name'] ?? $remoteID
                );
            }
        }

        return $messages
            ? implode("\n", $messages)
            : 'Farpoint is already in sync with the local uptime settings.';
    }

    private function buildMonitorPayload(VirtualHost $site): array
    {
        return array_merge(
            [
                'name' => $site->Title ?: $site->HostName,
                'url' => sprintf(
                    '%s://%s',
                    $site->EnableHTTPS ? 'https' : 'http',
                    $site->HostName
                ),
                'enabled' => true,
            ],
            $site->getUptimeMonitorConfig()
        );
    }

    private function monitorOptions(array $payload): array
    {
        unset($payload['name'], $payload['url']);

        return $payload;
    }
}
