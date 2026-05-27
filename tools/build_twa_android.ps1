<#
    Club Agelai - Build Android Web App (TWA)
    Uso:
      .\tools\build_twa_android.ps1 -ManifestUrl "https://tu-dominio.com/manifest.webmanifest"

    Este script:
    - Inicializa/actualiza un proyecto TWA con Bubblewrap.
    - Preconfigura rutas de JDK y Android SDK.
    - Te deja listo para generar APK/AAB en Play Store.
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

param(
    [Parameter(Mandatory = $true)]
    [string]$ManifestUrl,
    [string]$TargetDirectory = "mobile_webapp\twa_android"
)

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

if (-not $ManifestUrl.StartsWith("https://")) {
    throw "ManifestUrl debe usar HTTPS para Play Store/TWA."
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$outputDir = Join-Path $projectRoot $TargetDirectory
$bubblewrapCmd = Join-Path $env:APPDATA "npm\bubblewrap.cmd"
$sdkRoot = if (Test-Path "$env:LOCALAPPDATA\Android\Sdk") { "$env:LOCALAPPDATA\Android\Sdk" } else { "$env:LOCALAPPDATA\Android\sdk" }
$jdkPath = "C:\Program Files\Android\Android Studio\jbr"

if (-not (Test-Path $bubblewrapCmd)) {
    throw "No se encontro bubblewrap.cmd. Ejecuta primero .\\tools\\setup_android_webapp_env.ps1"
}
if (-not (Test-Path $sdkRoot)) {
    throw "No se encontro Android SDK local."
}
if (-not (Test-Path $jdkPath)) {
    throw "No se encontro JDK de Android Studio en $jdkPath"
}

# Fuerza Node real y certificados del sistema para evitar bloqueos SSL.
$env:PATH = "C:\Program Files\nodejs;$env:PATH"
$env:NODE_OPTIONS = "--use-system-ca"

Write-Step "Configurando Bubblewrap (JDK + Android SDK)"
try {
    & $bubblewrapCmd updateConfig --jdkPath "$jdkPath" --androidSdkPath "$sdkRoot" | Out-Null
} catch {
    # Si la version no soporta updateConfig, continuamos igualmente.
}

Write-Step "Preparando carpeta TWA"
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$twaManifest = Join-Path $outputDir "twa-manifest.json"
if (-not (Test-Path $twaManifest)) {
    Write-Step "Inicializando proyecto TWA"
    & $bubblewrapCmd init --manifest "$ManifestUrl" --directory "$outputDir"
} else {
    Write-Step "Actualizando proyecto TWA existente"
    Push-Location $outputDir
    try {
        & $bubblewrapCmd update --manifest "$twaManifest"
    } finally {
        Pop-Location
    }
}

Write-Host ""
Write-Host "Proyecto TWA preparado en: $outputDir" -ForegroundColor Green
Write-Host "Siguiente paso: abrir la carpeta en Android Studio y generar AAB firmado para Play Console." -ForegroundColor Green
