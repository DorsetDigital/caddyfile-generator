<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\ScheduledTask;
use DorsetDigital\Caddy\Model\VirtualHost;
use RuntimeException;
use SilverStripe\Core\Config\Config;

/**
 * Produces copy/paste AWS CLI scripts. Deliberately makes NO AWS requests.
 */
class SchedulerCommandGenerator
{
    private static $aws = [];

    public function phpVersion(VirtualHost $host): string
    {
        if (!$host->EnablePHP || !$host->PHPBackendID || !$host->PHPBackend()->exists()) {
            throw new RuntimeException('The selected host has no PHP backend.');
        }

        $backend = $host->PHPBackend();
        foreach ([(string) $backend->Title, (string) $backend->URI] as $candidate) {
            if (preg_match('/(?:^|[^0-9])8[.\-_]?([0-9])(?:[^0-9]|$)/', $candidate, $matches)) {
                return '8.' . $matches[1];
            }
        }
        throw new RuntimeException('Cannot identify the PHP version from this host backend.');
    }

    public function siteRoot(VirtualHost $host): string
    {
        if ((int) $host->HostType !== VirtualHost::HOST_TYPE_HOST || !$host->DocumentRoot) {
            throw new RuntimeException('Select a standard host with a document root.');
        }
        $root = FilesystemHelper::create()->getFullHostPath($host);
        if (!str_starts_with($root, '/mnt/nfs/')) {
            throw new RuntimeException('This host is not on the scheduled workers\' NFS mount.');
        }
        return rtrim($root, '/') . ($host->EnableZeroDowntime ? '/current' : '');
    }

    public function payload(ScheduledTask $task): array
    {
        $host = $task->VirtualHost();
        if (!$host || !$host->exists()) {
            throw new RuntimeException('Select a virtual host.');
        }
        $version = $this->phpVersion($host);
        $settings = Config::inst()->get(self::class, 'aws') ?: [];
        $php = $settings['php'][$version] ?? null;
        if (!$php || empty($php['task_definition']) || empty($php['container'])) {
            throw new RuntimeException("No verified ECS task definition/container mapping for PHP {$version}. Configure it before generating CLI.");
        }
        foreach (['region', 'cluster', 'capacity_provider', 'role', 'subnet', 'security_groups'] as $key) {
            if (empty($settings[$key])) {
                throw new RuntimeException("Missing Scheduler configuration: {$key}");
            }
        }

        $name = trim((string) $task->ScheduleName);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $name)) {
            throw new RuntimeException('Schedule name must contain only letters, numbers, dashes and underscores (maximum 64).');
        }
        $expression = trim((string) $task->ScheduleExpression);
        if (!preg_match('/^(rate|cron)\([a-zA-Z0-9,*?\/\- ]+\)$/', $expression)) {
            throw new RuntimeException('Enter a valid EventBridge rate(...) or cron(...) expression.');
        }
        $command = trim((string) $task->Command);
        if ($command === '' || str_contains($command, "\n") || str_contains($command, "\r")) {
            throw new RuntimeException('Enter a single-line task command.');
        }

        $shell = 'cd ' . escapeshellarg($this->siteRoot($host))
            . ' && exec setpriv --reuid=1000 --regid=1000 --clear-groups ' . $command;

        return [
            'Name' => $name,
            'GroupName' => 'default',
            'Description' => (string) ($task->Description ?: "Runs {$name}"),
            'State' => $task->Enabled ? 'ENABLED' : 'DISABLED',
            'ActionAfterCompletion' => 'NONE',
            'ScheduleExpression' => $expression,
            'ScheduleExpressionTimezone' => 'Europe/London',
            'FlexibleTimeWindow' => ['Mode' => 'OFF'],
            'Target' => [
                'Arn' => $settings['cluster'],
                'RoleArn' => $settings['role'],
                'Input' => json_encode([
                    'containerOverrides' => [[
                        'name' => $php['container'],
                        'command' => ['/bin/sh', '-c', $shell],
                    ]],
                ], JSON_THROW_ON_ERROR),
                'RetryPolicy' => [
                    'MaximumEventAgeInSeconds' => 86400,
                    'MaximumRetryAttempts' => 0,
                ],
                'EcsParameters' => [
                    'CapacityProviderStrategy' => [[
                        'capacityProvider' => $settings['capacity_provider'],
                        'base' => 0,
                        'weight' => 1,
                    ]],
                    'EnableECSManagedTags' => true,
                    'EnableExecuteCommand' => false,
                    'Group' => $name,
                    'NetworkConfiguration' => [
                        'awsvpcConfiguration' => [
                            'AssignPublicIp' => 'DISABLED',
                            'SecurityGroups' => $settings['security_groups'],
                            'Subnets' => [$settings['subnet']],
                        ],
                    ],
                    'TaskCount' => 1,
                    'TaskDefinitionArn' => $php['task_definition'],
                ],
            ],
        ];
    }

    public function script(ScheduledTask $task, bool $update = false): string
    {
        $payload = $this->payload($task);
        $region = Config::inst()->get(self::class, 'aws')['region'];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $header = "#!/usr/bin/env bash\nset -euo pipefail\n";
        $header .= "PAYLOAD=\$(mktemp)\ntrap 'rm -f \"\$PAYLOAD\"' EXIT\n";
        $header .= "cat > \"\$PAYLOAD\" <<'SCHEDULE_PAYLOAD_JSON'\n{$json}\nSCHEDULE_PAYLOAD_JSON\n";

        if (!$update) {
            return $header . 'aws scheduler create-schedule --cli-input-json "file://$PAYLOAD" --region '
                . escapeshellarg($region) . "\n";
        }

        // Fetch the current AWS configuration and preserve fields not managed by this generator.
        $header .= "CURRENT=\$(mktemp)\nUPDATED=\$(mktemp)\n";
        $header .= "trap 'rm -f \"\$PAYLOAD\" \"\$CURRENT\" \"\$UPDATED\"' EXIT\n";
        $header .= 'aws scheduler get-schedule --name ' . escapeshellarg($payload['Name'])
            . ' --group-name default --region ' . escapeshellarg($region)
            . ' > "$CURRENT"' . "\n";
        $header .= <<<'SHELL'
jq --slurpfile desired "$PAYLOAD" '
  {
    Name, GroupName, Description, State, ActionAfterCompletion,
    FlexibleTimeWindow, ScheduleExpression, ScheduleExpressionTimezone,
    StartDate, EndDate, KmsKeyArn, Target
  } | with_entries(select(.value != null)) |
  .Description = $desired[0].Description |
  .State = $desired[0].State |
  .ScheduleExpression = $desired[0].ScheduleExpression |
  .ScheduleExpressionTimezone = $desired[0].ScheduleExpressionTimezone |
  .FlexibleTimeWindow = $desired[0].FlexibleTimeWindow |
  .Target.Arn = $desired[0].Target.Arn |
  .Target.RoleArn = $desired[0].Target.RoleArn |
  .Target.Input = $desired[0].Target.Input |
  .Target.EcsParameters.TaskDefinitionArn = $desired[0].Target.EcsParameters.TaskDefinitionArn |
  .Target.EcsParameters.Group = $desired[0].Target.EcsParameters.Group |
  .Target.EcsParameters.CapacityProviderStrategy = $desired[0].Target.EcsParameters.CapacityProviderStrategy
' "$CURRENT" > "$UPDATED"
SHELL;
        return $header . "\n" . 'aws scheduler update-schedule --cli-input-json "file://$UPDATED" --region '
            . escapeshellarg($region) . "\n";
    }
}
