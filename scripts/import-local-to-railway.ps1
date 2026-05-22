# Imports database/local_export.sql into Railway MySQL.
# Usage:
#   $env:RAILWAY_DATABASE_URL = "<MYSQL_PUBLIC_URL from Railway MySQL Connect tab>"
#   .\scripts\import-local-to-railway.ps1
#
# Or:
#   .\scripts\import-local-to-railway.ps1 -DatabaseUrl "mysql://user:pass@host:port/railway"

param(
    [string]$DatabaseUrl = $env:RAILWAY_DATABASE_URL
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$DumpFile = Join-Path $Root "database\local_export.sql"

if (-not $DatabaseUrl) {
    Write-Host @"

RAILWAY_DATABASE_URL is not set.

1. Open Railway -> MySQL service -> Connect (or Variables)
2. Copy MYSQL_PUBLIC_URL (or MYSQL_URL for private network from TOURCONNECT only)
3. Run:

   `$env:RAILWAY_DATABASE_URL = 'mysql://USER:PASS@HOST:PORT/DATABASE'
   .\scripts\import-local-to-railway.ps1

"@
    exit 1
}

if (-not (Test-Path $DumpFile)) {
    Write-Host "Dump not found. Running export first..."
    & (Join-Path $PSScriptRoot "export-local-database.ps1")
}

if ($DatabaseUrl -notmatch '^mysql://([^:]+):([^@]+)@([^:/]+):(\d+)/([^?]+)') {
    throw "Invalid DATABASE_URL format. Expected: mysql://user:pass@host:port/database"
}

$user = [Uri]::UnescapeDataString($Matches[1])
$pass = [Uri]::UnescapeDataString($Matches[2])
$hostName = $Matches[3]
$port = $Matches[4]
$database = [Uri]::UnescapeDataString($Matches[5])

Write-Host "Importing to Railway MySQL: $hostName`:$port / $database"

$dumpMount = Join-Path $Root "database"
$env:MYSQL_PWD = $pass

try {
    docker run --rm `
        -v "${dumpMount}:/dump:ro" `
        -e MYSQL_PWD=$pass `
        mysql:8.0 `
        sh -c "mysql -h $hostName -P $port -u $user -D $database < /dump/local_export.sql" 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw "mysql import failed with exit code $LASTEXITCODE"
    }

    Write-Host "Import finished. Verifying tables..."
    docker run --rm `
        -e MYSQL_PWD=$pass `
        mysql:8.0 `
        mysql -h $hostName -P $port -u $user -D $database -e "SHOW TABLES; SELECT 'service' t, COUNT(*) c FROM service UNION SELECT 'user', COUNT(*) FROM user UNION SELECT 'booking', COUNT(*) FROM booking;" 2>&1
}
finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}

Write-Host @"

Done. Next steps on Railway:
1. TOURCONNECT -> Variables -> DATABASE_URL = `${{MySQL.MYSQL_URL}}?serverVersion=8.0.32&charset=utf8mb4
2. Redeploy TOURCONNECT

"@
