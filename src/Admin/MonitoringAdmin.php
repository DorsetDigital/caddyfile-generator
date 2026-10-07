<?php

namespace DorsetDigital\Caddy\Admin;

use DorsetDigital\Caddy\Client\FarpointClient;
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
use SilverStripe\View\Requirements;

class MonitoringAdmin extends LeftAndMain
{
    private static $url_segment = 'monitoring';
    private static $menu_title = 'Monitoring';
    private static $menu_icon_class = 'font-icon-chart-line';
    private static $required_permission_codes = 'ADMIN';

    private static $allowed_actions = [
        'dashboard',
        'pauseMonitoring',
        'resumeMonitoring',
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $dashboardUrl = $this->Link('dashboard');
        $pauseUrl = $this->Link('pauseMonitoring');
        $resumeUrl = $this->Link('resumeMonitoring');

        $fields = FieldList::create(
            HeaderField::create('FarpointMonitoring', 'Farpoint monitoring'),
            LiteralField::create(
                'FarpointStatus',
                '<div id="farpoint-service-status" class="alert alert-info">' .
                'Loading Farpoint status…</div>'
            ),
            LiteralField::create(
                'FarpointSummary',
                '<div id="farpoint-summary" class="row mb-4"></div>'
            ),
            LiteralField::create(
                'FarpointFilters',
                '<div class="row g-2 align-items-end mb-3">' .
                '<div class="col-md-4">' .
                '<label class="form-label" for="farpoint-search">Search</label>' .
                '<input type="search" id="farpoint-search" class="form-control" placeholder="Site name or URL">' .
                '</div>' .
                '<div class="col-md-3">' .
                '<label class="form-label" for="farpoint-state-filter">Status</label>' .
                '<select id="farpoint-state-filter" class="form-select">' .
                '<option value="">All statuses</option>' .
                '<option value="UP">Up</option>' .
                '<option value="DEGRADED">Degraded</option>' .
                '<option value="DOWN">Down</option>' .
                '<option value="UNKNOWN">Unknown</option>' .
                '</select>' .
                '</div>' .
                '<div class="col-md-3">' .
                '<label class="form-label" for="farpoint-sort">Sort by</label>' .
                '<select id="farpoint-sort" class="form-select">' .
                '<option value="name">Site name</option>' .
                '<option value="state">Status</option>' .
                '<option value="response_time">Response time</option>' .
                '<option value="last_checked">Last checked</option>' .
                '</select>' .
                '</div>' .
                '<div class="col-md-2">' .
                '<label class="form-label" for="farpoint-direction">Direction</label>' .
                '<select id="farpoint-direction" class="form-select">' .
                '<option value="asc">Ascending</option>' .
                '<option value="desc">Descending</option>' .
                '</select>' .
                '</div>' .
                '</div>'
            ),
            LiteralField::create(
                'FarpointTable',
                '<div class="table-responsive">' .
                '<table class="table table-striped table-hover">' .
                '<thead><tr>' .
                '<th>Site</th><th>Status</th><th>Response</th><th>Last check</th>' .
                '<th>24h uptime</th><th>7d uptime</th><th>30d uptime</th>' .
                '</tr></thead>' .
                '<tbody id="farpoint-monitor-rows">' .
                '<tr><td colspan="7">Loading monitors…</td></tr>' .
                '</tbody></table></div>'
            )
        );

        $pauseButton = HTML::createTag('button', [
            'class' => 'btn btn-danger farpoint-control',
            'id' => 'farpoint-pause',
            'data-url' => $pauseUrl,
            'type' => 'button',
        ], 'Pause all monitoring');

        $resumeButton = HTML::createTag('button', [
            'class' => 'btn btn-success farpoint-control',
            'id' => 'farpoint-resume',
            'data-url' => $resumeUrl,
            'type' => 'button',
        ], 'Resume monitoring');

        $actions = FieldList::create(
            LiteralField::create(
                'FarpointControls',
                '<div class="d-flex gap-2 mb-4">' .
                $pauseButton .
                $resumeButton .
                '</div>'
            )
        );

        $form = Form::create($this, 'EditForm', $fields, $actions);
        $form->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setAttribute('data-dashboard-url', $dashboardUrl);

        Requirements::javascript(
            'dorsetdigital/caddyfile-generator:client/javascript/monitoring.js'
        );

        return $form;
    }

    public function dashboard(HTTPRequest $request): HTTPResponse
    {
        if (!$this->canView()) {
            return $this->jsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            $query = [
                'page' => max(1, (int) $request->getVar('page')),
                'per_page' => min(100, max(1, (int) ($request->getVar('per_page') ?: 100))),
                'sort' => $request->getVar('sort') ?: 'name',
                'direction' => $request->getVar('direction') ?: 'asc',
            ];

            $state = trim((string) $request->getVar('state'));
            if ($state !== '') {
                $query['state'] = $state;
            }

            $search = trim((string) $request->getVar('search'));
            if ($search !== '') {
                $query['search'] = $search;
            }

            $data = $this->getClient()->getDashboard($query);

            if (!is_array($data)) {
                return $this->jsonResponse(['error' => 'Farpoint is unavailable'], 502);
            }

            return $this->jsonResponse($data);
        } catch (Exception $e) {
            $this->getLogger()->error('Unable to load Farpoint dashboard', [
                'error' => $e->getMessage(),
            ]);
            return $this->jsonResponse(['error' => $e->getMessage()], 502);
        }
    }

    public function pauseMonitoring(HTTPRequest $request): HTTPResponse
    {
        return $this->controlMonitoring($request, true);
    }

    public function resumeMonitoring(HTTPRequest $request): HTTPResponse
    {
        return $this->controlMonitoring($request, false);
    }

    private function controlMonitoring(
        HTTPRequest $request,
        bool $paused
    ): HTTPResponse {
        if (!$this->canView()) {
            return $this->jsonResponse(['error' => 'Permission denied'], 403);
        }

        if (!$request->isPOST()) {
            return $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }

        try {
            $client = $this->getClient();
            $data = $paused
                ? $client->pauseMonitoring()
                : $client->resumeMonitoring();

            if (!is_array($data)) {
                return $this->jsonResponse(['error' => 'Farpoint is unavailable'], 502);
            }

            return $this->jsonResponse($data);
        } catch (Exception $e) {
            $this->getLogger()->error('Farpoint control action failed', [
                'paused' => $paused,
                'error' => $e->getMessage(),
            ]);
            return $this->jsonResponse(['error' => $e->getMessage()], 502);
        }
    }

    private function getClient(): FarpointClient
    {
        return Injector::inst()->get(FarpointClient::class);
    }

    private function getLogger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        return HTTPResponse::create()
            ->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json')
            ->setBody(json_encode($data));
    }
}
