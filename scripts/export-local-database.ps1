# Exports local Docker MySQL (start_go_db) to database/local_export.sql
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$OutDir = Join-Path $Root "database"
$OutFile = Join-Path $OutDir "local_export.sql"

if (-not (Test-Path $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir | Out-Null
}

$container = docker ps --format "{{.Names}}" | Where-Object { $_ -match "mysql" } | Select-Object -First 1
if (-not $container) {
    throw "Local MySQL container is not running. Start it with: docker compose up -d mysql"
}

Write-Host "Exporting from container: $container"
docker exec $container mysqldump `
    -u start_go_user `
    -pstart_go_password `
    start_go_db `
    --single-transaction `
    --routines `
    --triggers `
    --set-gtid-purged=OFF `
    2>$null | Set-Content -Path $OutFile -Encoding utf8

$sizeKb = [math]::Round((Get-Item $OutFile).Length / 1kb, 1)
Write-Host "Saved: $OutFile ($sizeKb KB)"

docker exec $container mysql -u start_go_user -pstart_go_password start_go_db -e @"
SELECT 'activity_log' AS tbl, COUNT(*) AS rows FROM activity_log
UNION SELECT 'booking', COUNT(*) FROM booking
UNION SELECT 'budget_tracker', COUNT(*) FROM budget_tracker
UNION SELECT 'inventory', COUNT(*) FROM inventory
UNION SELECT 'payment', COUNT(*) FROM payment
UNION SELECT 'service', COUNT(*) FROM service
UNION SELECT 'user', COUNT(*) FROM user;
"@ 2>$null
