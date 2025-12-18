<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\UptimeMonitor;
use DorsetDigital\Caddy\Client\UptimeClientInterface;
use SilverStripe\Core\Injector\Injectable;

class UptimeMonitorHelper
{
    use Injectable;

    private $client;

    public function __construct(UptimeClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function getRequiredMonitors() {
        return UptimeMonitor::get()->filter([
            'Active' => 1,
        ])->filterAny([
            'MonitorID:ExactMatch' => '',
            'MonitorID' => null
        ]);
    }

    public function getRetiredMonitors() {
        return UptimeMonitor::get()->filter([
            'Active' => 0,
            'MonitorID:Not' => null,
        ]);
    }

    public function cleanUpMonitors() {
        $messages = [];
        $monitors = $this->getRetiredMonitors();
        if ($monitors->count() < 1) {
            $messages[] = 'No monitors found to clean up.';
        }
        foreach ($monitors as $monitor) {
            $delete = $this->deleteMonitor($monitor->MonitorID);
            if ($delete) {
                $monitor->update(['MonitorID' => null])->write();
                $messages[] = sprintf('Monitor ID %s was deleted.', $monitor->MonitorID);
            }
        }
        return implode("\n", $messages);
    }

    public function addNewMonitors() {
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

    public function createMonitor($name, $url)
    {
        return $this->client->createMonitor($name, $url);
    }

    public function deleteMonitor($monitorID)
    {
        return $this->client->deleteMonitor($monitorID);
    }

    public function getMonitor($monitorID)
    {

    }

    public function updateMonitor($monitorID)
    {

    }

}