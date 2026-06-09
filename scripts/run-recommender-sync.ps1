# Runs daily_recommender_sync.py using values from repo-root .env
# Usage: powershell -ExecutionPolicy Bypass -File scripts/run-recommender-sync.ps1

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $RepoRoot ".env"
$Script = Join-Path $RepoRoot "pasarkraft\daily_recommender_sync.py"

if (-not (Test-Path $Script)) {
    Write-Error "Missing $Script"
}

function Read-EnvFile([string]$Path) {
    $vars = @{}
    if (-not (Test-Path $Path)) { return $vars }
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq "" -or $line.StartsWith("#") -or $line -notmatch "=") { return }
        $parts = $line -split "=", 2
        $vars[$parts[0].Trim()] = $parts[1].Trim()
    }
    return $vars
}

$envVars = Read-EnvFile $EnvFile
$apiBase = $envVars["PK_API_BASE"]
$apiKey = $envVars["PK_API_KEY"]

if ([string]::IsNullOrWhiteSpace($apiBase)) {
    $apiBase = "https://pasarkraft.xo.je"
    Write-Host "PK_API_BASE not set in .env, using $apiBase" -ForegroundColor Yellow
}

if ([string]::IsNullOrWhiteSpace($apiKey)) {
    Write-Error "Set PK_API_KEY in $EnvFile before running sync."
}

$python = $envVars["PK_PYTHON_BIN"]
if ([string]::IsNullOrWhiteSpace($python)) { $python = "python" }

Write-Host "Syncing recommendations to $apiBase ..." -ForegroundColor Cyan
& $python $Script --api-base $apiBase --api-key $apiKey --limit 8
if ($LASTEXITCODE -ne 0) {
    Write-Error "Recommender sync failed (exit $LASTEXITCODE)."
}
Write-Host "Done." -ForegroundColor Green
