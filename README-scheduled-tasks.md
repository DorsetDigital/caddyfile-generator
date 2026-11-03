# Scheduled Tasks (CLI generation only)

The **Scheduled Tasks** section in the CMS stores local schedule definitions and
generates scripts for AWS EventBridge Scheduler. The web application never contacts
AWS or stores AWS credentials.

## Workflow

1. Configure the PHP backend record with its scheduled ECS task definition and exact container name.\n2. Create a task and select an existing PHP-enabled virtual host.
3. Enter a unique EventBridge schedule name, e.g. `Example-Queue`.
4. Enter the command relative to the site's deployment directory, e.g.
   `vendor/bin/sake dev/tasks/ProcessJobQueueTask`. Do **not** enter `cd` or
   `setpriv`: the generator adds those.
5. Enter a rate or cron expression (e.g. `rate(5 minutes)` or `cron(10 0 * * ? *)`).
6. Save the record. The **Commands** tab shows separate create and update scripts.
7. Run the appropriate script manually in an authenticated AWS CLI environment.
   Review the generated payload and AWS CLI output before considering it applied.
8. Only **after independently verifying AWS**, set **Recorded AWS PHP version**
   to the version actually deployed. This is an operator-maintained value, not a
   live status check.

The Scheduled Tasks grid and virtual-host service badges highlight records whose
host PHP version differs from the manually recorded version, or which have not
yet been confirmed in AWS.

## Working directory

The generator uses the host's configured Filesystem and DocumentRoot, plus
`/current` for zero-downtime deployments. It deliberately excludes
`DocumentRootSuffix` (for example, `public`), because Silverstripe `sake`
runs from the project root. Only NFS-backed standard hosts are supported.

## AWS configuration

`_config/scheduler.yml` holds the known-good dedicated ECS cluster, capacity
provider, networking and shared execution role. The ECS task definition and\ncontainer override name come directly from each host's PHPBackend record.

There is no version-specific mapping in the module. Configure these fields on\neach PHPBackend. If either is missing, the generator refuses to produce commands.

## Existing schedules

This feature does **not** import existing AWS schedules. Existing schedules can
be entered as local records, then marked with their confirmed deployed PHP
version. **Create** is for new schedules; **Update** fetches the current AWS
schedule and applies the locally managed fields while preserving other retrieved
configuration. Review the generated script before executing it.

No AWS write operations are invoked from the CMS. Generating a command never
automatically changes the recorded deployed version.
