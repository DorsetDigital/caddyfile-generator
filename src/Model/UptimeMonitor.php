<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\ORM\DataObject;

class UptimeMonitor extends DataObject
{
    private static $table_name = 'UptimeMonitor';
    private static $db = [
        'MonitorID' => 'Varchar',
        'Active' => 'Boolean',
    ];
    private static $belongs_to = [
      'VirtualHost' => VirtualHost::class,
    ];
}