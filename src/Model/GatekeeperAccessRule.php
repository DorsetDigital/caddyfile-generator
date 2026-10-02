<?php

namespace DorsetDigital\Caddy\Model;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

class GatekeeperAccessRule extends DataObject
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_DOMAIN = 'domain';

    private static $table_name = 'GatekeeperAccessRule';

    private static $db = [
        'Type' => 'Varchar(20)',
        'Value' => 'Varchar(255)',
    ];

    private static $has_one = [
        'VirtualHost' => VirtualHost::class,
    ];

    private static $defaults = [
        'Type' => self::TYPE_EMAIL,
    ];

    private static $summary_fields = [
        'Type' => 'Type',
        'Value' => 'Value',
    ];

    private static $default_sort = 'Type, Value';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Type', 'Value', 'VirtualHostID']);

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('Type', 'Type', [
                self::TYPE_EMAIL => 'Email address',
                self::TYPE_DOMAIN => 'Email domain',
            ]),
            TextField::create('Value', 'Value')
                ->setDescription('Exact email address or domain allowed to request a Gatekeeper access code.'),
        ]);

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Type = strtolower(trim((string) $this->Type));
        $this->Value = strtolower(trim((string) $this->Value));
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $type = strtolower(trim((string) $this->Type));
        $value = strtolower(trim((string) $this->Value));

        if (!in_array($type, [self::TYPE_EMAIL, self::TYPE_DOMAIN], true)) {
            $result->addError('Gatekeeper access rule type must be email or domain.');
            return $result;
        }

        if ($value === '') {
            $result->addError('Gatekeeper access rule value is required.');
            return $result;
        }

        if ($type === self::TYPE_EMAIL) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $result->addError('Please enter a valid email address.');
            }
            return $result;
        }

        if (
            !str_contains($value, '.')
            || str_contains($value, '@')
            || preg_match('~[\s/\\\\:]~', $value)
        ) {
            $result->addError('Please enter a valid email domain.');
        }

        return $result;
    }
}
