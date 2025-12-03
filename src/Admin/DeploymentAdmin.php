<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Helper\DeploymentHelper;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements;

class DeploymentAdmin extends LeftAndMain
{
    private static $url_segment = 'deployment';
    private static $menu_title = 'Deployment';
    private static $menu_icon_class = 'font-icon-cog';
    private static $required_permission_codes = 'ADMIN';

    private static $allowed_actions = [
        'runProcess'
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $runUrl = $this->Link('runProcess');

        $fields = FieldList::create(
            HeaderField::create('Build the Caddy configuration'),
            LiteralField::create('results', '<div id="process-results" style="margin-top: 20px;"></div>')
        );

        $btn1 = HTML::createTag('button', [
            'class' => 'btn btn-primary process-deploy',
            'id' => 'run-process-btn',
            'data-url' => $runUrl,
            'type' => 'button',
        ], 'Build and push configuration');

        $btn2 = HTML::createTag('button', [
            'class' => 'btn btn-outline-secondary process-deploy',
            'id' => 'run-dr-process-btn',
            'data-url' => $runUrl.'?dryrun=1',
            'type' => 'button',
        ], 'Build and push configuration (Dry Run)');

        $actions = FieldList::create(
            LiteralField::create(
                'run-button',
                $btn1.$btn2
            )
        );

        $form = Form::create(
            $this,
            'EditForm',
            $fields,
            $actions
        );

        $form->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

        Requirements::javascript('dorsetdigital/caddyfile-generator:client/javascript/deployment.js');

        return $form;
    }

    /**
     * Run the custom process
     * This must return a proper HTTPResponse with JSON content
     */
    public function runProcess(HTTPRequest $request)
    {
        if (!$this->canView()) {
            return $this->jsonError('Permission denied', 403);
        }

        if (!$request->isPOST()) {
            return $this->jsonError('Method not allowed', 405);
        }

        try {

            $dep = DeploymentHelper::create();

            if ($request->requestVar('dryrun') == 1) {
                $dep->processDryRun();
            }
            else {
                $dep->processConfig();
            }

            $html = $dep->getMessages();

            return HTTPResponse::create()
                ->addHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'html' => $html,
                    'status' => 'success'
                ]));

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

}