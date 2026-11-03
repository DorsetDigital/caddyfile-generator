<?php

namespace DorsetDigital\Caddy\Model;

use DorsetDigital\Caddy\Helper\SchedulerCommandGenerator;
use RuntimeException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * A locally recorded schedule. Nothing in this model calls AWS.
 *
 * RecordedAWSVersion is an operator-confirmed snapshot, NOT a live AWS status.
 */
class ScheduledTask extends DataObject
{
    private static $table_name = 'CaddyScheduledTask';

    private static $db = [
        'ScheduleName' => 'Varchar(64)',
        'Description' => 'Varchar(255)',
        'Command' => 'Text',
        'ScheduleExpression' => 'Varchar(255)',
        'Enabled' => 'Boolean',
        'RecordedAWSVersion' => 'Varchar(10)',
    ];

    private static $has_one = [
        'VirtualHost' => VirtualHost::class,
    ];

    private static $defaults = [
        'ScheduleExpression' => 'rate(5 minutes)',
        'Enabled' => true,
    ];

    private static $summary_fields = [
        'ScheduleName' => 'Schedule',
        'VirtualHost.Title' => 'Host',
        'ScheduleExpression' => 'Frequency',
        'HostPHPVersion' => 'Host PHP',
        'RecordedAWSVersion' => 'Recorded AWS PHP',
        'PHPStatus' => 'Review',
    ];

    private static $searchable_fields = [
        'ScheduleName',
        'VirtualHostID',
    ];

    private static $default_sort = 'ScheduleName ASC';

    public function getTitle(): string
    {
        return (string) ($this->ScheduleName ?: 'New scheduled task');
    }

    public function getHostPHPVersion(): string
    {
        try {
            return SchedulerCommandGenerator::create()->phpVersion($this->VirtualHost());
        } catch (RuntimeException $e) {
            return 'Unknown';
        }
    }

    public function getPHPStatus(): string
    {
        $current = $this->getHostPHPVersion();
        $recorded = trim((string) $this->RecordedAWSVersion);

        if ($recorded === '') {
            return 'Not confirmed in AWS';
        }
        if ($current === 'Unknown') {
            return 'Check host configuration';
        }
        return $recorded === $current ? 'Matches recorded AWS version' : 'PHP VERSION MISMATCH';
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('RecordedAWSVersion');

        $fields->addFieldToTab('Root.Main',
            DropdownField::create(
                'VirtualHostID',
                'Virtual host',
                VirtualHost::get()->filter([
                    'HostType' => VirtualHost::HOST_TYPE_HOST,
                    'EnablePHP' => 1,
                ])->map('ID', 'Title')
            )->setEmptyString('Select a PHP-enabled host')
        );
        $fields->addFieldToTab('Root.Main',
            TextField::create('ScheduleName', 'EventBridge schedule name')
        );
        $fields->addFieldToTab('Root.Main',
            TextareaField::create('Command', 'Task command')
                ->setRows(3)
                ->setDescription('Command relative to the site release root, e.g. vendor/bin/sake dev/tasks/ProcessJobQueueTask. The generator adds cd, UID/GID 1000 and the container shell.')
        );
        $fields->addFieldToTab('Root.Main',
            TextField::create('ScheduleExpression', 'EventBridge expression')
                ->setDescription('For example: rate(5 minutes), rate(1 days), cron(10 0 * * ? *). Time zone: Europe/London.')
        );
        $fields->addFieldToTab('Root.Main',
            CheckboxField::create('Enabled', 'Schedule enabled')
        );
        $fields->addFieldToTab('Root.Main',
            TextField::create('RecordedAWSVersion', 'Recorded AWS PHP version')
                ->setDescription('Manually record the PHP version AFTER confirming the CLI change was applied in AWS. Blank means unverified; never automatically updated when a host changes.')
        );

        if ($this->isInDB()) {
            $generator = SchedulerCommandGenerator::create();
            $fields->addFieldToTab('Root.Commands',
                HeaderField::create('TaskStatusHeading', 'Local configuration only')
            );
            try {
                $fields->addFieldToTab('Root.Commands',
                    TextField::create('PHPStatus', 'PHP version comparison', $this->getPHPStatus())->setReadonly(true)
                );
                $fields->addFieldToTab('Root.Commands',
                    TextField::create('SiteRoot', 'CLI working directory', $generator->siteRoot($this->VirtualHost()))->setReadonly(true)
                );
                $fields->addFieldToTab('Root.Commands',
                    TextareaField::create('CreateCLI', 'Create schedule command', $generator->script($this))->setRows(20)->setReadonly(true)
                );
                $fields->addFieldToTab('Root.Commands',
                    TextareaField::create('UpdateCLI', 'Update existing schedule command', $generator->script($this, true))->setRows(24)->setReadonly(true)
                );
            } catch (RuntimeException $e) {
                $fields->addFieldToTab('Root.Commands',
                    TextareaField::create('GeneratorError', 'Unable to generate commands', $e->getMessage())->setReadonly(true)
                );
            }
        }

        return $fields;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $this->ScheduleName)) {
            $result->addError('Schedule name must contain 1–64 letters, numbers, dashes or underscores.');
        }
        if (!$this->VirtualHostID) {
            $result->addError('Choose a virtual host.');
        }
        if (trim((string) $this->Command) === '') {
            $result->addError('Enter a task command.');
        }
        if (!preg_match('/^(rate|cron)\([a-zA-Z0-9,*?\/\- ]+\)$/', trim((string) $this->ScheduleExpression))) {
            $result->addError('Enter a valid rate(...) or cron(...) expression.');
        }
        return $result;
    }
}
