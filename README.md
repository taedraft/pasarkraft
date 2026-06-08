# pasarkraft
fyp project

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
