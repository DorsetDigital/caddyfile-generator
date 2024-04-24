<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\ORM\DataObject;

class DBCredentials extends DataObject
{
    const STATUS_ACTIVE = 0;
    const STATUS_PENDING = 1;
    const STATUS_PENDING_DELETION = 2;
    const STATUS_DELETED = 3;

    private static $table_name = 'DBCredentials';
    private static $db = [
        'DBUserName' => 'Varchar(255)',
        'DBPassword' => 'Varchar(255)',
        'DBName' => 'Varchar',
        'Status' => 'Int'
    ];
    private static $has_one = [
      'VirtualHost' => VirtualHost::class
    ];

    private function getStatusOpts() {
        return [
          self::STATUS_ACTIVE => _t(__CLASS__.'.active', 'Active'),
          self::STATUS_PENDING => _t(__CLASS__.'.pending', 'Pending'),
          self::STATUS_PENDING_DELETION => _t(__CLASS__.'.pendingDeletion', 'Pending deletion'),
          self::STATUS_DELETED => _t(__CLASS__.'.deleted', 'Deleted')
        ];
    }

    private static $summary_fields = [
        'DBUserName' => 'DB User',
        'VirtualHost.Title' => 'Virtual host',
        'NiceStatus' => 'Status'
    ];

    private static $defaults = [
      'Status' => self::STATUS_PENDING
    ];

    public function getNiceStatus() {
        $opts = $this->getStatusOpts();
        return $opts[$this->Status];
    }
}
