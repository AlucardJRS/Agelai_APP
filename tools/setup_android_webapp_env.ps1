<#
    Club Agelai - Setup Android WebApp Environment
    Este script prepara el entorno local para empaquetar la WEB APP en Android (TWA):
    - Instala cmdline-tools si faltan.
    - Configura variables ANDROID_SDK_ROOT / ANDROID_HOME / JAVA_HOME.
    - Ajusta PATH de usuario sin truncarlo.
    - Verifica Flutter Android toolchain.
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Add-PathEntries {
    param(
        [string[]]$PreferredEntries
    )

    # Leemos PATH de usuario actual y lo normalizamos.
    $currentPath = [Environment]::GetEnvironmentVariable("Path", "User")
    if ([string]::IsNullOrWhiteSpace($currentPath)) {
        $currentPath = ""
    }

    $seen = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::OrdinalIgnoreCase)
    $ordered = New-Object 'System.Collections.Generic.List[string]'

    # Primero añadimos rutas prioritarias (Node + Android tools).
    foreach ($entry in $PreferredEntries) {
        if (-not [string]::IsNullOrWhiteSpace($entry) -and (Test-Path $entry) -and $seen.Add($entry)) {
            [void] $ordered.Add($entry)
        }
    }

    # Después preservamos el resto de rutas existentes.
    foreach ($entry in $currentPath.Split(';')) {
        $trimmed = $entry.Trim()
        if ($trimmed -ne "" -and $seen.Add($trimmed)) {
            [void] $ordered.Add($trimmed)
        }
    }

    $newPath = ($ordered.ToArray() -join ';')

    # Escribimos directamente en registro para evitar truncado de setx.
    $envKey = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey("Environment", $true)
    if ($null -eq $envKey) {
        throw "No se pudo abrir HKCU\\Environment para escribir PATH."
    }
    $envKey.SetValue("Path", $newPath, [Microsoft.Win32.RegistryValueKind]::ExpandString)
    $envKey.Close()

    return $newPath
}

Write-Step "Detectando Android SDK"
$sdkRoot = Join-Path $env:LOCALAPPDATA "Android\Sdk"
if (-not (Test-Path $sdkRoot)) {
    $sdkRoot = Join-Path $env:LOCALAPPDATA "Android\sdk"
}
if (-not (Test-Path $sdkRoot)) {
    throw "No se encontro Android SDK en $env:LOCALAPPDATA\\Android\\Sdk. Abre Android Studio y descarga el SDK primero."
}
Write-Host "SDK: $sdkRoot" -ForegroundColor Green

Write-Step "Instalando cmdline-tools si faltan"
$cmdToolsLatest = Join-Path $sdkRoot "cmdline-tools\latest\bin\sdkmanager.bat"
if (-not (Test-Path $cmdToolsLatest)) {
    $cmdToolsRoot = Join-Path $sdkRoot "cmdline-tools"
    New-Item -ItemType Directory -Force -Path $cmdToolsRoot | Out-Null

    $zipPath = Join-Path $env:TEMP "agelai_cmdline_tools.zip"
    $extractRoot = Join-Path $env:TEMP "agelai_cmdline_extract"
    $urls = @(
        "https://dl.google.com/android/repository/commandlinetools-win-14742923_latest.zip",
        "https://dl.google.com/android/repository/commandlinetools-win-13114758_latest.zip",
        "https://dl.google.com/android/repository/commandlinetools-win-11076708_latest.zip"
    )

    $downloadOk = $false
    foreach ($url in $urls) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $zipPath -UseBasicParsing
            $downloadOk = $true
            Write-Host "Descarga correcta: $url" -ForegroundColor Green
            break
        } catch {
            Write-Host "No disponible: $url" -ForegroundColor Yellow
        }
    }

    if (-not $downloadOk) {
        throw "No se pudo descargar cmdline-tools automaticamente."
    }

    if (Test-Path $extractRoot) {
        Remove-Item -Recurse -Force $extractRoot
    }
    Expand-Archive -Path $zipPath -DestinationPath $extractRoot -Force

    $latestDir = Join-Path $sdkRoot "cmdline-tools\latest"
    if (Test-Path $latestDir) {
        Remove-Item -Recurse -Force $latestDir
    }
    New-Item -ItemType Directory -Force -Path $latestDir | Out-Null

    $source = Join-Path $extractRoot "cmdline-tools"
    if (-not (Test-Path $source)) {
        $source = Join-Path $extractRoot "tools"
    }
    Copy-Item -Path (Join-Path $source "*") -Destination $latestDir -Recurse -Force
}

Write-Step "Configurando variables de entorno"
[Environment]::SetEnvironmentVariable("ANDROID_SDK_ROOT", $sdkRoot, "User")
[Environment]::SetEnvironmentVariable("ANDROID_HOME", $sdkRoot, "User")
[Environment]::SetEnvironmentVariable("JAVA_HOME", "C:\Program Files\Android\Android Studio\jbr", "User")

$preferred = @(
    "C:\Program Files\nodejs",
    (Join-Path $sdkRoot "platform-tools"),
    (Join-Path $sdkRoot "cmdline-tools\latest\bin"),
    (Join-Path $sdkRoot "build-tools\37.0.0")
)
$newPath = Add-PathEntries -PreferredEntries $preferred
Write-Host "PATH (user) actualizado. Longitud: $($newPath.Length)" -ForegroundColor Green

# Variables para el proceso actual (evita reiniciar esta sesión de shell).
$env:ANDROID_SDK_ROOT = $sdkRoot
$env:ANDROID_HOME = $sdkRoot
$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
$env:PATH = ("C:\Program Files\nodejs;" + (Join-Path $sdkRoot "platform-tools") + ";" + (Join-Path $sdkRoot "cmdline-tools\latest\bin") + ";" + (Join-Path $sdkRoot "build-tools\37.0.0") + ";" + $env:PATH)

Write-Step "Aceptando licencias Android"
& flutter doctor --android-licenses | Out-Null

Write-Step "Verificacion final"
& flutter doctor -v

Write-Host ""
Write-Host "Entorno Android Web App listo." -ForegroundColor Green
Write-Host "Si abres una consola nueva, node/npm/adb/sdmanager se cargaran desde PATH de usuario." -ForegroundColor Green
