@echo off
setlocal

set PK_API_BASE=http://pasarkraft.xo.je/
set PK_API_KEY=YOUR_API_KEY

python "%~dp0daily_recommender_sync.py" --api-base "%PK_API_BASE%" --api-key "%PK_API_KEY%" --limit 8

endlocal
