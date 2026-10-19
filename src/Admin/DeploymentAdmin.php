<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Helper\DeploymentHelper;
use DorsetDigital\Caddy\Helper\GatekeeperSyncHelper;
use DorsetDigital\Caddy\Helper\UptimeMonitorHelper;
use DorsetDigital\Caddy\Model\VirtualHost;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\HTML;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;

/**
 * Class \DorsetDigital\Caddy\Admin\DeploymentAdmin
 *
 */
class DeploymentAdmin extends LeftAndMain
{
    private static $url_segment = 'deployment';
    private static $menu_title = 'Deployment';
    private static $menu_icon_class = 'font-icon-cog';
    private static $required_permission_codes = 'ADMIN';

    private static $allowed_actions = [
        'runProcess',
        'syncENVFiles',
        'syncFarpoint',
        'syncGatekeeper',
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $runUrl = $this->Link('runProcess');
        $syncENVFilesUrl = $this->Link('syncENVFiles');
        $syncFarpointUrl = $this->Link('syncFarpoint');
        $syncGatekeeperUrl = $this->Link('syncGatekeeper');

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
            'data-url' => $runUrl . '?dryrun=1',
            'type' => 'button',
        ], 'Build and push configuration (Dry Run)');

        $envButton = HTML::createTag('button', [
            'class' => 'btn btn-outline-primary process-env-sync',
            'id' => 'sync-env-btn',
            'data-url' => $syncENVFilesUrl,
            'type' => 'button',
        ], 'Write ENV files');

        $syncButton = HTML::createTag('button', [
            'class' => 'btn btn-outline-primary process-farpoint-sync',
            'id' => 'sync-farpoint-btn',
            'data-url' => $syncFarpointUrl,
            'type' => 'button',
        ], 'Sync Farpoint monitors');

        $syncGatekeeperButton = HTML::createTag('button', [
            'class' => 'btn btn-outline-primary process-gatekeeper-sync',
            'id' => 'sync-gatekeeper-btn',
            'data-url' => $syncGatekeeperUrl,
            'type' => 'button',
        ], 'Sync Gatekeeper');

        $actions = FieldList::create(
            LiteralField::create(
                'run-button',
                $btn1 . $btn2 . $envButton . $syncButton . $syncGatekeeperButton
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

    public function syncENVFiles(HTTPRequest $request)
    {
        if (!$this->canView()) {
            $this->jsonError(403, 'Permission denied');
        }

        if (!$request->isPOST()) {
            $this->jsonError(405, 'Method not allowed');
        }

        try {
            $helper = DeploymentHelper::create();
            $helper->processENVFiles();

            return HTTPResponse::create()
                ->addHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'html' => $helper->getMessages(),
                    'status' => 'success',
                ]));
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            $this->jsonError(500, $e->getMessage());
        }
    }

    public function syncFarpoint(HTTPRequest $request)
    {
        if (!$this->canView()) {
            $this->jsonError(403, 'Permission denied');
        }

        if (!$request->isPOST()) {
            $this->jsonError(405, 'Method not allowed');
        }

        try {
            $helper = UptimeMonitorHelper::create();
            $messages = $helper->syncFarpoint();

            return HTTPResponse::create()
                ->addHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'html' => '<pre>' . htmlspecialchars($messages, ENT_QUOTES, 'UTF-8') . '</pre>',
                    'status' => 'success',
                ]));
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            $this->jsonError(500, $e->getMessage());
        }
    }

    public function syncGatekeeper(HTTPRequest $request)
    {
        if (!$this->canView()) {
            $this->jsonError(403, 'Permission denied');
        }

        if (!$request->isPOST()) {
            $this->jsonError(405, 'Method not allowed');
        }

        try {
            $sites = Versioned::get_by_stage(VirtualHost::class, 'Live');
            $messages = GatekeeperSyncHelper::create()->sync($sites, true);

            return HTTPResponse::create()
                ->addHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'html' => '<pre>' . htmlspecialchars($messages, ENT_QUOTES, 'UTF-8') . '</pre>',
                    'status' => 'success',
                ]));
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            $this->jsonError(500, $e->getMessage());
        }
    }

    /**
     * Run the custom process
     * This must return a proper HTTPResponse with JSON content
     */
    public function runProcess(HTTPRequest $request)
    {
        if (!$this->canView()) {
            $this->jsonError(403, 'Permission denied');
        }

        if (!$request->isPOST()) {
            $this->jsonError(405, 'Method not allowed');
        }

        try {

            $dep = DeploymentHelper::create();

            if ($request->requestVar('dryrun') == 1) {
                $dep->processDryRun();
            } else {
                $dep->processConfig();
            }

            $html = $dep->getMessages();

            return HTTPResponse::create()
                ->addHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'html' => $html,
                    'status' => 'success'
                ]));

        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            $this->jsonError(500, $e->getMessage());
        }
    }

}