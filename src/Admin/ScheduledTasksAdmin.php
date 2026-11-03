<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Model\ScheduledTask;
use SilverStripe\Admin\ModelAdmin;

/**
 * Manage local schedule definitions and generate CLI scripts.
 * No AWS API operations or credentials are used.
 */
class ScheduledTasksAdmin extends ModelAdmin
{
    private static $managed_models = [
        ScheduledTask::class,
    ];

    private static $menu_title = 'Scheduled Tasks';
    private static $url_segment = 'scheduled-tasks';
    private static $menu_icon_class = 'font-icon-clock';
    private static $menu_priority = 99;
    private static $required_permission_codes = 'ADMIN';
}
