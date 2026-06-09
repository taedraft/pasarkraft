# pasarkraft
fyp project

## Deploy to InfinityFree (automated prep)

I cannot log into your hosting account, but you can prepare everything locally in one command:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/prepare-deploy.ps1
```

This creates:

| Output | Where to put it on InfinityFree |
|--------|----------------------------------|
| `dist/pasarkraft-htdocs.zip` | File Manager → **htdocs** → **Upload & Unzip** |
| `dist/server.env.template` | Edit credentials, upload as **`htdocs/pk_config.env`** (recommended). Dotfile `.env` may not work on InfinityFree. Protected by `.htaccess`. |

After upload, sync recommendations from your PC:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-recommender-sync.ps1
```

### Optional: FTP automation

1. Copy `scripts/deploy-ftp.example.ps1` → `scripts/deploy-ftp.ps1`
2. Add FTP credentials from InfinityFree control panel
3. Run `deploy-ftp.ps1` to upload the zip (still unzip manually in File Manager)

### What the deploy script excludes

`setup_db.php`, all `.py` files, `__pycache__`, local `.env`, and dev notes — safe for production `htdocs`.

## Recommender Workaround for InfinityFree

InfinityFree blocks direct remote DB access from external Python jobs. Use API-based daily sync instead:

1. Export product and interaction data through `api/recommender_data.php`.
2. Run recommender offline using `daily_recommender_sync.py`.
3. Push results back in one batch to `api/recommender_bulk_write.php`.
4. Homepage reads cached rows from `recommendations` (`hybrid_py_daily`).

### Daily Run Command

```bash
python pasarkraft/daily_recommender_sync.py --api-base http://pasarkraft.xo.je/ --api-key YOUR_API_KEY --limit 8
```

### Windows Task Scheduler (run once per day)

```powershell
schtasks /Create /SC DAILY /TN "PasarKraft Daily Recommender" /TR "python c:\DEV\HANNAN\pasarkraft\pasarkraft\daily_recommender_sync.py --api-base http://pasarkraft.xo.je/ --api-key YOUR_API_KEY --limit 8" /ST 02:00
```

Or schedule the provided batch file:

```powershell
schtasks /Create /SC DAILY /TN "PasarKraft Daily Recommender" /TR "c:\DEV\HANNAN\pasarkraft\pasarkraft\run_daily_recommender.bat" /ST 02:00
```
