# Builds InfinityFree upload packages:
#   dist/pasarkraft-htdocs.zip  -> upload & unzip inside htdocs/
#   dist/server.env.template    -> copy to account home as .env (one level above htdocs)
#
# Usage:  powershell -ExecutionPolicy Bypass -File scripts/prepare-deploy.ps1

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$Source = $RepoRoot
$Dist = Join-Path $RepoRoot "dist"
$Staging = Join-Path $Dist "htdocs-staging"
$ZipPath = Join-Path $Dist "pasarkraft-htdocs.zip"
$EnvTemplate = Join-Path $Dist "server.env.template"

$ExcludeNames = @(
    "setup_db.php",
    "setup_db.sql",
    "recommender.py",
    "daily_recommender_sync.py",
    "generate_data.py",
    "generate_test_data.py",
    "run_daily_recommender.bat",
    "COPILOT.md",
    "README.md",
    "TASK1.md",
    "TASK2.md",
    "FUTURE_RENDER_SETUP.md",
    ".gitignore"
)

$ExcludePatterns = @(
    "*.pyc",
    "*.pyo",
    "__pycache__",
    ".env",
    ".env.*"
)

if (Test-Path $Staging) { Remove-Item $Staging -Recurse -Force }
if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }
New-Item -ItemType Directory -Path $Staging -Force | Out-Null
New-Item -ItemType Directory -Path $Dist -Force | Out-Null

function Should-Exclude([string]$Name, [string]$RelativePath) {
    if ($ExcludeNames -contains $Name) { return $true }
    foreach ($pattern in $ExcludePatterns) {
        if ($Name -like $pattern) { return $true }
    }
    if ($RelativePath -match '(\\|/)__pycache__(\\|/)') { return $true }
    if ($RelativePath -match '^(dist|scripts|\.git|pasarkraft)(\\|/|$)') { return $true }
    return $false
}

Get-ChildItem -Path $Source -Recurse -Force | ForEach-Object {
    $rel = $_.FullName.Substring($Source.Length).TrimStart('\')
    if ($rel -eq "") { return }

    if (Should-Exclude $_.Name $rel) { return }

    $dest = Join-Path $Staging $rel
    if ($_.PSIsContainer) {
        New-Item -ItemType Directory -Path $dest -Force | Out-Null
    } else {
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Copy-Item $_.FullName $dest -Force
    }
}

$fileCount = (Get-ChildItem $Staging -Recurse -File).Count
$folderCount = (Get-ChildItem $Staging -Directory).Count
$rootFileCount = (Get-ChildItem $Staging -File).Count
$rootNames = (Get-ChildItem $Staging | Select-Object -ExpandProperty Name) -join ", "

Compress-Archive -Path (Join-Path $Staging "*") -DestinationPath $ZipPath -Force
Remove-Item $Staging -Recurse -Force

$exampleEnv = Join-Path $RepoRoot ".env.example"
if (Test-Path $exampleEnv) {
    Copy-Item $exampleEnv $EnvTemplate -Force
} else {
    @"
PK_DB_HOST=sql204.infinityfree.com
PK_DB_USER=YOUR_IF0_USER
PK_DB_PASSWORD=YOUR_DB_PASSWORD
PK_DB_NAME=YOUR_IF0_DB_NAME
PK_API_BASE=https://pasarkraft.xo.je
PK_API_KEY=YOUR_API_KEY
GEMINI_API_KEY=YOUR_GEMINI_API_KEY
PK_SMTP_HOST=smtp.gmail.com
PK_SMTP_PORT=465
PK_SMTP_USER=your.email@gmail.com
PK_SMTP_PASS=your_gmail_app_password
PK_SMTP_FROM=your.email@gmail.com
PK_SMTP_FROM_NAME=PasarKraft
"@ | Set-Content $EnvTemplate -Encoding UTF8
}

Write-Host ""
Write-Host "Deploy package ready:" -ForegroundColor Green
Write-Host "  $ZipPath"
Write-Host "  $EnvTemplate"
Write-Host ""
Write-Host "Package contents:" -ForegroundColor Yellow
Write-Host "  $fileCount files total"
Write-Host "  $folderCount folders + $rootFileCount files at htdocs root (expect ~21 top-level items)"
Write-Host "  Root: $rootNames"
Write-Host ""
Write-Host "InfinityFree steps:" -ForegroundColor Cyan
Write-Host "  1. File Manager -> htdocs -> Upload & Unzip -> pasarkraft-htdocs.zip"
Write-Host "  2. Edit server.env.template with real credentials"
Write-Host "  3. Upload server.env.template as htdocs/pk_config.env (recommended on InfinityFree)"
Write-Host "  4. On your PC: powershell -File scripts/run-recommender-sync.ps1"
Write-Host ""
