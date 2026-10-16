<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Client\GatekeeperClient;
use DorsetDigital\Caddy\Model\VirtualHost;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

class GatekeeperSyncHelper
{
    use Injectable;

    private const SITE_ID_PREFIX = 'virtualhost-';

    public function sync(DataList $allSites): string
    {
        $client = GatekeeperClient::create();
        $desired = $this->getDesiredSites($allSites);
        $remote = $client->listSites();

        $remoteManagedIDs = [];
        foreach ($remote as $site) {
            $siteID = $site['id'] ?? '';
            if (is_string($siteID) && str_starts_with($siteID, self::SITE_ID_PREFIX)) {
                $remoteManagedIDs[$siteID] = true;
            }
        }

        $removed = 0;
        foreach (array_keys($remoteManagedIDs) as $siteID) {
            if (!isset($desired[$siteID])) {
                $client->deleteSite($siteID);
                $removed++;
            }
        }

        $updated = 0;
        foreach ($desired as $siteID => $payload) {
            $client->putSite($siteID, $payload);
            $updated++;
        }

        return sprintf(
            'Gatekeeper synchronised: %d site%s updated, %d stale site%s removed.',
            $updated,
            $updated === 1 ? '' : 's',
            $removed,
            $removed === 1 ? '' : 's'
        );
    }

    private function getDesiredSites(DataList $allSites): array
    {
        $desired = [];

        /** @var VirtualHost $site */
        foreach ($allSites as $site) {
            if (!$site->getGatekeeperEnabled()) {
                continue;
            }

            $siteID = self::SITE_ID_PREFIX . (int) $site->ID;
            $rules = [];

            foreach ($site->GatekeeperAccessRules() as $rule) {
                $rules[] = [
                    'type' => $rule->Type,
                    'value' => $rule->Value,
                ];
            }

            $desired[$siteID] = [
                'id' => $siteID,
                'hosts' => [
                    $site->getCurrentHostName(),
                ],
                'access_rules' => $rules,
            ];
        }

        return $desired;
    }
}
