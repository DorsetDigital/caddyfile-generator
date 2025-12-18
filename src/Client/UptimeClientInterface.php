<?php

namespace DorsetDigital\Caddy\Client;

interface UptimeClientInterface
{
    public function createMonitor($name, $url);

    public function deleteMonitor($monitorID);

    public function getMonitor($monitorID);

    public function updateMonitor($monitorID);
}