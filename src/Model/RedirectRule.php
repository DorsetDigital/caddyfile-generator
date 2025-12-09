<?php

namespace DorsetDigital\Caddy\Admin;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\HTML;

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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Path', 'NewLocation', 'Permanent', 'Sort', 'VirtualHost']);
        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create('pathnote', HTML::createTag('p', [
                'class' => 'alert alert-info',
            ],
                "Path matching is an exact match by default, not a prefix match. You must append a * for a fast prefix match. Note that /foo* will match /foo and /foo/ as well as /foobar; you might actually want /foo/* instead.")),
            TextField::create('Path', 'Old Path')
                ->setDescription('Root-relative - should begin with a forward-slash.  Can contain wildcard, see note above.'),
            TextField::create('NewLocation', 'New Location')
                ->setDescription('Can be a root-relative location (eg. /about-us-new) or an absolute URL'),
            CheckboxField::create('Permanent', 'Permanent Redirect (301)')
        ]);
        return $fields;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!str_starts_with($this->Path, '/')) {
            $result->addError('Path must begin with a slash.');
        }
        if ((!str_starts_with($this->NewLocation, '/')) && (!str_starts_with($this->NewLocation, 'http'))) {
            $result->addError('New Location must be root-relative or an absolute URL.');
        }
        if (str_starts_with($this->NewLocation, 'http:')) {
            $result->addMessage('NewLocation',
                'You are redirecting to a non-SSL URL, is that correct?',
                ValidationResult::TYPE_INFO, 'httpinfo');
        }
        return $result;
    }
}