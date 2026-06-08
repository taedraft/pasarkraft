# Future Render Implementation Plan

## Goal
Move the recommender relay off InfinityFree (AES blocks non-browser API calls) and run the Python pipeline against a small HTTP API hosted on Render.

## Why
InfinityFree free tier blocks automated HTTP calls to PHP endpoints, so the current Option 1 (Python calling /api) cannot run automatically.

## Target Architecture
- Render hosts a small Python API (Flask or FastAPI).
- InfinityFree PHP pushes product/interactions data to the Render API.
- Python recommender reads from Render API and writes results back to Render API.
- InfinityFree PHP pulls recommendations from Render API on demand (or on a scheduled trigger).

## Render Service (Python)
- Runtime: Python 3.11
- Dependencies: numpy, pandas, scikit-learn, flask (or fastapi), uvicorn if fastapi
- Endpoints:
  - POST /data (store products + interactions)
  - POST /recs (store recommendations)
  - GET /data (fetch latest data)
  - GET /recs?user_id= (fetch recommendations for a user)
- Auth: API key in header (X-API-KEY)

## Files to Add (Render Repo)
- app.py (API server)
- requirements.txt
- render.yaml (optional, for deploy settings)

## InfinityFree Changes
- Add a PHP script to POST data to Render API (products + interactions)
- Add a PHP script to GET recs from Render API and cache into MySQL
- Cron-like trigger: manual admin button or a scheduled ping (if available)

## Python Recommender Changes
- Point recommender.py to Render API base URL
- Use API key header for both data fetch and result write

## Deployment Steps (Render)
1. Create a new GitHub repo for the API.
2. Add app.py and requirements.txt.
3. Create a new Render Web Service:
   - Build: pip install -r requirements.txt
   - Start: python app.py (Flask) or uvicorn app:app --host 0.0.0.0 --port $PORT (FastAPI)
4. Set environment variables:
   - API_KEY
5. Deploy and copy the public URL.

## Testing Plan
- POST /data with sample payload from InfinityFree
- Run recommender.py locally with --api-base pointing to Render
- POST /recs from recommender.py
- GET /recs from InfinityFree PHP and verify homepage recommendations

## Open Questions
- Which framework to use (Flask vs FastAPI)
- Where to store data on Render (in-memory vs simple SQLite vs file)
- How often to refresh recs
