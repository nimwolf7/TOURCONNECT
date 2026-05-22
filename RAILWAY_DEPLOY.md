# Deploy TOURCONNECT on Railway

## Required variables (TOURCONNECT service)

| Variable | Value |
|----------|--------|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | long random string (not `$ecretf0rt3st`) |
| `DATABASE_URL` | `${{MySQL.MYSQL_URL}}?serverVersion=8.0.32&charset=utf8mb4` |
| `JWT_PASSPHRASE` | same passphrase used when generating JWT keys locally |

## After pushing deploy files

1. Commit and push `Dockerfile`, `railway.json`, and `scripts/railway-*.sh`.
2. In Railway → **TOURCONNECT** → **Settings** → **Build**, set **Builder** to **Dockerfile** (not Nixpacks/Railpack).
3. Redeploy **TOURCONNECT** and use **Clear build cache** if the logs still show `nix-env` / Nixpacks.
3. Test:
   - `https://YOUR-APP.up.railway.app/` → home page
   - `https://YOUR-APP.up.railway.app/api` → JSON API index

## Mobile app

Point API base URL to your Railway domain, e.g. `https://tourconnect-production.up.railway.app`.
