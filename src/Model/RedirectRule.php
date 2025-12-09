<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\ORM\DataObject;

class RedirectRule extends DataObject
{
    private static $table_name = 'RedirectRule';
    private static $db = [
        'Path' => 'Varchar(255)',
        'NewLocation' => 'Varchar(255)',
        'Permanent' => 'Boolean',
        'Sort' => 'Int',
    ];
    private static $has_one = [
        'VirtualHost' => VirtualHost::class,
    ];
    private static $summary_fields = [
        'Path',
        'NewLocation',
    ];

    public function getCMSFields() {
        $fields = parent::getCMSFields();

        return $fields;
    }
}