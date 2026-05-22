# Database export / Railway import

## Local export (already done)

`local_export.sql` contains your localhost Docker MySQL dump:

| Table | Rows (approx.) |
|-------|----------------|
| activity_log | 173 |
| booking | 16 |
| budget_tracker | 4 |
| inventory | 20 |
| payment | 8 |
| service | 20 |
| user | 8 |

Re-export anytime:

```powershell
cd C:\Users\USER\go
.\scripts\export-local-database.ps1
```

## Import to Railway

1. Railway → **MySQL** → **Connect** → copy **MYSQL_PUBLIC_URL** (for import from your PC).
2. In PowerShell:

```powershell
cd C:\Users\USER\go
$env:RAILWAY_DATABASE_URL = 'PASTE_MYSQL_PUBLIC_URL_HERE'
.\scripts\import-local-to-railway.ps1
```

3. Railway → **TOURCONNECT** → **Variables**:

```env
DATABASE_URL=${{MySQL.MYSQL_URL}}?serverVersion=8.0.32&charset=utf8mb4
```

4. Redeploy **TOURCONNECT**.

## Security

`local_export.sql` includes hashed passwords and real user data. Do not commit it to git.
