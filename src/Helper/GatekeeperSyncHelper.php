<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Client\GatekeeperClient;
use DorsetDigital\Caddy\Model\VirtualHost;
use Exception;
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
        $remoteHostOwners = [];

        foreach ($remote as $site) {
            $siteID = $site['id'] ?? '';
            if (!is_string($siteID) || $siteID === '') {
                continue;
            }

            if (str_starts_with($siteID, self::SITE_ID_PREFIX)) {
                $remoteManagedIDs[$siteID] = true;
            }

            foreach ($site['hosts'] ?? [] as $host) {
                if (is_string($host) && $host !== '') {
                    $remoteHostOwners[strtolower($host)] = $siteID;
                }
            }
        }

        $removed = 0;
        foreach (array_keys($remoteManagedIDs) as $siteID) {
            if (!isset($desired[$siteID])) {
                $client->deleteSite($siteID);
                $removed++;

                foreach ($remoteHostOwners as $host => $ownerID) {
                    if ($ownerID === $siteID) {
                        unset($remoteHostOwners[$host]);
                    }
                }
            }
        }

        foreach ($desired as $siteID => $payload) {
            foreach ($payload['hosts'] as $host) {
                $ownerID = $remoteHostOwners[strtolower($host)] ?? null;

                if ($ownerID !== null && $ownerID !== $siteID) {
                    throw new Exception(sprintf(
                        'Gatekeeper host %s is already assigned to unmanaged site %s. Remove or rename that Gatekeeper site before deploying.',
                        $host,
                        $ownerID
                    ));
                }
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
