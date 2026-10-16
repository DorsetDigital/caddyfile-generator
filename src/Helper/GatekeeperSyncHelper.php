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

    public function sync(DataList $allSites, bool $force = false): string
    {
        $client = GatekeeperClient::create();
        $desired = $this->getDesiredSites($allSites);
        $remote = $client->listSites();

        $remoteByID = [];
        $remoteHostOwners = [];

        foreach ($remote as $site) {
            $siteID = $site['id'] ?? '';
            if (!is_string($siteID) || $siteID === '') {
                continue;
            }

            $remoteByID[$siteID] = $site;

            foreach ($site['hosts'] ?? [] as $host) {
                if (is_string($host) && $host !== '') {
                    $remoteHostOwners[strtolower($host)] = $siteID;
                }
            }
        }

        $removed = 0;

        foreach ($remoteByID as $siteID => $site) {
            $isManaged = str_starts_with($siteID, self::SITE_ID_PREFIX);
            $isDesired = isset($desired[$siteID]);

            if (($force && !$isDesired) || (!$force && $isManaged && !$isDesired)) {
                $client->deleteSite($siteID);
                $removed++;

                foreach ($site['hosts'] ?? [] as $host) {
                    if (is_string($host)) {
                        unset($remoteHostOwners[strtolower($host)]);
                    }
                }

                unset($remoteByID[$siteID]);
            }
        }

        foreach ($desired as $siteID => $payload) {
            foreach ($payload['hosts'] as $host) {
                $normalisedHost = strtolower($host);
                $ownerID = $remoteHostOwners[$normalisedHost] ?? null;

                if ($ownerID === null || $ownerID === $siteID) {
                    continue;
                }

                if (!$force) {
                    throw new Exception(sprintf(
                        'Gatekeeper host %s is already assigned to site %s. Run a full Gatekeeper sync if the control panel should take ownership of this host.',
                        $host,
                        $ownerID
                    ));
                }

                if (isset($remoteByID[$ownerID])) {
                    $client->deleteSite($ownerID);
                    $removed++;

                    foreach ($remoteByID[$ownerID]['hosts'] ?? [] as $ownedHost) {
                        if (is_string($ownedHost)) {
                            unset($remoteHostOwners[strtolower($ownedHost)]);
                        }
                    }

                    unset($remoteByID[$ownerID]);
                }
            }
        }

        $updated = 0;
        foreach ($desired as $siteID => $payload) {
            $client->putSite($siteID, $payload);
            $updated++;
        }

        return sprintf(
            'Gatekeeper synchronised%s: %d site%s updated, %d site%s removed.',
            $force ? ' (full)' : '',
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
