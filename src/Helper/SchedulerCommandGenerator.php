<?php

namespace DorsetDigital\Caddy\Helper;

use DorsetDigital\Caddy\Model\ScheduledTask;
use DorsetDigital\Caddy\Model\VirtualHost;
use RuntimeException;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injectable;

/**
 * Produces copy/paste AWS CLI scripts. Deliberately makes NO AWS requests.
 */
class SchedulerCommandGenerator
{
    use Injectable;

    private function settings(): array
    {
        $config = SiteConfig::current_site_config();
        $groups = preg_split('/[\\s,]+/', trim((string) $config->SchedulerSecurityGroupIDs), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $settings = [
            'region' => trim((string) $config->SchedulerRegion),
            'cluster' => trim((string) $config->SchedulerClusterArn),
            'capacity_provider' => trim((string) $config->SchedulerCapacityProvider),
            'role' => trim((string) $config->SchedulerExecutionRoleArn),
            'subnet' => trim((string) $config->SchedulerSubnetID),
            'security_groups' => $groups,
            'timezone' => trim((string) $config->SchedulerTimezone),
            'schedule_group' => trim((string) $config->SchedulerGroupName),
            'run_uid' => (int) $config->SchedulerRunUID,
            'run_gid' => (int) $config->SchedulerRunGID,
        ];
        foreach ($settings as $key => $value) {
            if ($value === '' || $value === null || $value === []) {
                throw new RuntimeException("Missing SiteConfig Scheduler setting: {$key}");
            }
        }
        if (!preg_match('/^[a-z]{2}-[a-z]+-[0-9]+$/', $settings['region'])
            || !preg_match('/^arn:aws[a-z-]*:ecs:[^:]+:[0-9]{12}:cluster\\/[A-Za-z0-9_-]+$/', $settings['cluster'])
            || !preg_match('/^arn:aws[a-z-]*:iam::[0-9]{12}:role\\/.+$/', $settings['role'])
            || !preg_match('/^subnet-[a-f0-9]+$/', $settings['subnet'])) {
            throw new RuntimeException('Invalid Scheduler region, cluster ARN, role ARN or subnet in SiteConfig.');
        }
        if ($settings['run_uid'] < 1 || $settings['run_gid'] < 1
            || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $settings['schedule_group'])
            || !in_array($settings['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException('Invalid scheduler group, timezone, UID or GID in SiteConfig.');
        }
        foreach ($groups as $group) {
            if (!preg_match('/^sg-[a-f0-9]+$/', $group)) {
                throw new RuntimeException('Invalid Scheduler security group ID in SiteConfig.');
            }
        }
        return $settings;
    }

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
        if (!str_starts_with($root, '/') || str_contains($root, "\n") || str_contains($root, "\r")) {
            throw new RuntimeException('Host filesystem root must be an absolute path.');
        }
        return rtrim($root, '/') . ($host->EnableZeroDowntime ? '/current' : '');
    }

    public function payload(ScheduledTask $task): array
    {
        $host = $task->VirtualHost();
        if (!$host || !$host->exists()) {
            throw new RuntimeException('Select a virtual host.');
        }
        $settings = $this->settings();
        if (!$host->EnablePHP || !$host->PHPBackendID || !$host->PHPBackend()->exists()) {
            throw new RuntimeException('The selected host has no PHP backend.');
        }
        $backend = $host->PHPBackend();
        $taskDefinition = trim((string) $backend->ScheduledTaskDefinition);
        $containerName = trim((string) $backend->ScheduledContainerName);
        if ($taskDefinition === '' || $containerName === '') {
            throw new RuntimeException('Configure the scheduled ECS task definition and container name on the host PHP backend.');
        }
        if (!preg_match('/^[A-Za-z0-9:_\/.\-]+$/', $taskDefinition)
            || !preg_match('/^[A-Za-z0-9_-]+$/', $containerName)) {
            throw new RuntimeException('Enter a full ECS task definition ARN and a valid container name on the PHP backend.');
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
            . ' && exec setpriv --reuid=' . $settings['run_uid']
            . ' --regid=' . $settings['run_gid'] . ' --clear-groups ' . $command;

        return [
            'Name' => $name,
            'GroupName' => $settings['schedule_group'],
            'Description' => (string) ($task->Description ?: "Runs {$name}"),
            'State' => $task->Enabled ? 'ENABLED' : 'DISABLED',
            'ActionAfterCompletion' => 'NONE',
            'ScheduleExpression' => $expression,
            'ScheduleExpressionTimezone' => $settings['timezone'],
            'FlexibleTimeWindow' => ['Mode' => 'OFF'],
            'Target' => [
                'Arn' => $settings['cluster'],
                'RoleArn' => $settings['role'],
                'Input' => json_encode([
                    'containerOverrides' => [[
                        'name' => $containerName,
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
                    'TaskDefinitionArn' => $taskDefinition,
                ],
            ],
        ];
    }

    public function script(ScheduledTask $task, bool $update = false): string
    {
        $payload = $this->payload($task);
        $region = $this->settings()['region'];
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
            . ' --group-name ' . escapeshellarg($payload['GroupName']) . ' --region ' . escapeshellarg($region)
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
