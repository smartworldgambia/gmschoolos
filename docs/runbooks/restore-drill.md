# Runbook: Backup Restore Drill

## Goal

Prove that database and critical object storage can be restored within stated RTO/RPO. Until this drill succeeds in the target environment, RPO/RTO remain **targets**, not guarantees.

## Automated readiness check

Against the DB the app is currently pointed at (use a restored staging DB for real drills):

```bash
php artisan schoolos:validate-backup --trigger=manual
```

Or from the platform console: **Backups → Run validation**. Results land in `backup_validation_runs` and failed runs raise a critical `platform_alerts` row.

Scheduled: weekly via `routes/console.php` (`schoolos:validate-backup --trigger=scheduled`).

## Procedure (outline)

1. Snapshot or select a known backup  
2. Restore into an isolated staging database  
3. Point a staging app instance at the restored DB (read-only first)  
4. Run `php artisan schoolos:validate-backup` on that staging instance  
5. Verify sample tenants: school profile, student count, recent payment, recent grade  
6. Restore a sample private file from object storage  
7. Record wall-clock time and gaps vs RPO  
8. Tear down staging restore resources  

## Cadence

At least quarterly once production backups exist; after any backup-system change.
