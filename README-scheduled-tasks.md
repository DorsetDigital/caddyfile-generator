# Scheduled Tasks (CLI generation only)

The **Scheduled Tasks** section in the CMS stores local schedule definitions and
generates scripts for AWS EventBridge Scheduler. The web application never contacts
AWS or stores AWS credentials.

## Workflow

1. Set the AWS region, ECS cluster ARN, capacity provider, execution role ARN, subnet, security groups, schedule group, timezone and process UID/GID in **SiteConfig → Scheduled Tasks**. No defaults are populated by the module.\n2. Configure the PHP backend record with its scheduled ECS task definition and exact container name.\n2. Create a task and select an existing PHP-enabled virtual host.
4. Enter a unique EventBridge schedule name, e.g. `Example-Queue`.
5. Enter the command relative to the site's deployment directory, e.g.
   `vendor/bin/sake dev/tasks/ProcessJobQueueTask`. Do **not** enter `cd` or
   `setpriv`: the generator adds those.
6. Enter a rate or cron expression (e.g. `rate(5 minutes)` or `cron(10 0 * * ? *)`).
7. Save the record. The **Commands** tab shows separate create and update scripts.
8. Run the appropriate script manually in an authenticated AWS CLI environment.
   Review the generated payload and AWS CLI output before considering it applied.
9. Only **after independently verifying AWS**, set **Recorded AWS PHP version**
   to the version actually deployed. This is an operator-maintained value, not a
   live status check.

The Scheduled Tasks grid and virtual-host service badges highlight records whose
host PHP version differs from the manually recorded version, or which have not
yet been confirmed in AWS.

## Working directory

The generator uses the host's configured Filesystem and DocumentRoot, plus
`/current` for zero-downtime deployments. It deliberately excludes
`DocumentRootSuffix` (for example, `public`), because Silverstripe `sake`
runs from the project root. Filesystem roots come from the associated host's
configured Filesystem; no hard-coded mount prefix is assumed.

## AWS configuration

All installation-specific settings are stored in SiteConfig under the
**Scheduled Tasks** tab: region, ECS cluster ARN, capacity provider, execution
role ARN, subnet ID, security group IDs (one per line), schedule group,
timezone, and process UID/GID. No account IDs, ARNs, resource names,
network IDs, process identities or PHP-version mappings are stored in the
module. The generator refuses to produce commands until these settings are
completed and validated.

Each PHPBackend stores its scheduled ECS task definition and container
override name. If either is missing, generation fails with an explanatory error.

## Existing schedules

This feature does **not** import existing AWS schedules. Existing schedules can
be entered as local records, then marked with their confirmed deployed PHP
version. **Create** is for new schedules; **Update** fetches the current AWS
schedule and applies the locally managed fields while preserving other retrieved
configuration. Review the generated script before executing it.

No AWS write operations are invoked from the CMS. Generating a command never
automatically changes the recorded deployed version.
