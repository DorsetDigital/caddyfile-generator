<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Client\FarpointClient;
use DorsetDigital\Caddy\Client\UptimeClientInterface;
use DorsetDigital\Caddy\Model\UptimeMonitor;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;

class UptimeMonitorHelper
{
    use Injectable;

    private $client;

    public function __construct(UptimeClientInterface $client)
    {
        $this->client = $client;
    }

    public function cleanUpMonitors()
    {
        $messages = [];
        $monitors = $this->getRetiredMonitors();
        if ($monitors->count() < 1) {
            $messages[] = 'No monitors found to clean up.';
        }
        foreach ($monitors as $monitor) {
            $monitorID = $monitor->MonitorID;

            if (!Uuid::isValid((string) $monitorID)) {
                $monitor->update(['MonitorID' => null])->write();
                $messages[] = sprintf('Legacy monitor ID %s was cleared.', $monitorID);
                continue;
            }

            $delete = $this->deleteMonitor($monitorID);
            if ($delete) {
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

    public function addNewMonitors()
    {
        $messages = [];
        $required = $this->getRequiredMonitors();
        if ($required->count() < 1) {
            $messages[] = 'No uptime monitors to create';
        }

        /**
         * @var UptimeMonitor $monitor
         */
        foreach ($required as $monitor) {
            $site = $monitor->VirtualHost();
            $protocol = $site->EnableHTTPS ? 'https' : 'http';
            $monitorID = $this->createMonitor(
                $site->Title,
                sprintf('%s://%s', $protocol, $site->HostName),
            );
            if ($monitorID) {
                $monitor->update([
                    'MonitorID' => $monitorID
                ])->write();
                $messages[] = sprintf("Created monitor for %s, ID: %s", $site->HostName, $monitorID);
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

    public function createMonitor($name, $url)
    {
        return $this->client->createMonitor($name, $url);
    }

    public function getMonitor($monitorID)
    {
        return $this->client->getMonitor($monitorID);
    }

    public function updateMonitor($monitorID, array $data = [])
    {
        return $this->client->updateMonitor($monitorID, $data);
    }

}