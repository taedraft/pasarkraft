# OPTIONAL: FTP upload after prepare-deploy.ps1
# 1. Copy this file to deploy-ftp.ps1 (deploy-ftp.ps1 is gitignored)
# 2. Fill in FTP credentials from InfinityFree control panel -> FTP Details
# 3. Run: powershell -ExecutionPolicy Bypass -File scripts/deploy-ftp.ps1
#
# InfinityFree FTP host is usually: ftpupload.net
# Remote htdocs path is often: /htdocs or /domains/yourdomain/htdocs

param(
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "YOUR_FTP_USER",
    [string]$FtpPass = "YOUR_FTP_PASSWORD",
    [string]$RemoteHtdocs = "/htdocs",
    [string]$RemoteEnvDir = "/"
)

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$ZipPath = Join-Path $RepoRoot "dist\pasarkraft-htdocs.zip"
$EnvPath = Join-Path $RepoRoot "dist\server.env.template"

if (-not (Test-Path $ZipPath)) {
    Write-Error "Run scripts/prepare-deploy.ps1 first."
}

function Upload-FtpFile([string]$LocalFile, [string]$RemotePath) {
    $uri = "ftp://$FtpHost$RemotePath"
    $request = [System.Net.FtpWebRequest]::Create($uri)
    $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $request.UseBinary = $true
    $request.UsePassive = $true
    $bytes = [System.IO.File]::ReadAllBytes($LocalFile)
    $stream = $request.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    $response = $request.GetResponse()
    Write-Host "Uploaded $LocalFile -> $RemotePath ($($response.StatusDescription))"
    $response.Close()
}

Upload-FtpFile $ZipPath "$RemoteHtdocs/pasarkraft-htdocs.zip"

if (Test-Path $EnvPath) {
    Write-Host ""
    Write-Host "Upload .env manually after editing server.env.template:" -ForegroundColor Yellow
    Write-Host "  Rename to .env and upload to account home (parent of htdocs)."
    Write-Host "  Or uncomment below after filling real credentials:"
    Write-Host "  # Upload-FtpFile (Join-Path $RepoRoot '.env') '$RemoteEnvDir.env'"
}

Write-Host ""
Write-Host "Next: In InfinityFree File Manager, unzip pasarkraft-htdocs.zip inside htdocs." -ForegroundColor Cyan
