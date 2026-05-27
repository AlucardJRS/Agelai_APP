Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Convert-SecureToPlainText {
    param(
        [Parameter(Mandatory = $true)]
        [System.Security.SecureString]$SecureValue
    )

    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
        if ($null -eq $value) {
            # Empty SecureString can come back as null; normalize to empty text.
            return ""
        }
        return $value
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
}

function Escape-EnvValue {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string]$Value
    )

    $escaped = $Value.Replace('\', '\\').Replace('"', '\"')
    return '"' + $escaped + '"'
}

function Read-Default {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Prompt,
        [Parameter(Mandatory = $true)]
        [string]$Default
    )

    $raw = Read-Host "$Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return $Default
    }
    return $raw.Trim()
}

function Read-Required {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Prompt
    )

    while ($true) {
        $raw = Read-Host $Prompt
        if (-not [string]::IsNullOrWhiteSpace($raw)) {
            return $raw.Trim()
        }
        Write-Host "Valor obligatorio. Intenta de nuevo." -ForegroundColor Yellow
    }
}

function Read-SecureRequired {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Prompt
    )

    while ($true) {
        $secure = Read-Host $Prompt -AsSecureString
        $value = Convert-SecureToPlainText -SecureValue $secure
        if (-not [string]::IsNullOrWhiteSpace($value)) {
            return $value.Trim()
        }
        Write-Host "Valor obligatorio. Intenta de nuevo." -ForegroundColor Yellow
    }
}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$envFilePath = Join-Path $projectRoot "backend/api/.env.local"

Write-Host ""
Write-Host "=== Asistente de secretos locales (Club Agelai) ===" -ForegroundColor Cyan
Write-Host "Este asistente guardara credenciales en backend/api/.env.local" -ForegroundColor Cyan
Write-Host "No se subira a GitHub (esta excluido por .gitignore)." -ForegroundColor Cyan
Write-Host ""

# DB (con valores por defecto locales)
$dbHost = Read-Default -Prompt "AGELAI_DB_HOST" -Default "127.0.0.1"
$dbPort = Read-Default -Prompt "AGELAI_DB_PORT" -Default "3306"
$dbName = Read-Default -Prompt "AGELAI_DB_NAME" -Default "agelai_dietas"
$dbUser = Read-Default -Prompt "AGELAI_DB_USER" -Default "root"
$dbPassSecure = Read-Host "AGELAI_DB_PASS (puede ir vacio)" -AsSecureString
$dbPass = Convert-SecureToPlainText -SecureValue $dbPassSecure

# Base URL de la aplicacion (local o produccion)
$appBaseUrl = Read-Default -Prompt "AGELAI_BASE_URL" -Default "http://127.0.0.1:8000"
$appBaseUrl = $appBaseUrl.TrimEnd('/')

# App secret
$appSecretInput = Read-Host "AGELAI_APP_SECRET (enter para generar automaticamente)"
if ([string]::IsNullOrWhiteSpace($appSecretInput)) {
    $bytes = New-Object byte[] 48
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $appSecret = [Convert]::ToBase64String($bytes)
} else {
    $appSecret = $appSecretInput.Trim()
}

Write-Host ""
Write-Host "Configura Google OAuth (obligatorio para login Google real)" -ForegroundColor Green
$googleClientId = Read-Required -Prompt "AGELAI_GOOGLE_CLIENT_ID"
$googleClientSecret = Read-SecureRequired -Prompt "AGELAI_GOOGLE_CLIENT_SECRET"
$googleRedirectDefault = "$appBaseUrl/oauth/google/callback"
$googleRedirectUri = Read-Default -Prompt "AGELAI_GOOGLE_REDIRECT_URI" -Default $googleRedirectDefault

Write-Host ""
$configureSmtp = Read-Default -Prompt "¿Configurar SMTP real ahora? (si/no)" -Default "si"
$smtpEnabled = $configureSmtp.Trim().ToLowerInvariant() -eq "si" -or $configureSmtp.Trim().ToLowerInvariant() -eq "s"

$smtpHost = ""
$smtpPort = "587"
$smtpSecure = "tls"
$smtpUser = ""
$smtpPass = ""
$smtpFromEmail = ""
$smtpFromName = "Club Agelai"

if ($smtpEnabled) {
    $smtpHost = Read-Required -Prompt "AGELAI_SMTP_HOST"
    $smtpPort = Read-Default -Prompt "AGELAI_SMTP_PORT" -Default "587"
    $smtpSecure = Read-Default -Prompt "AGELAI_SMTP_SECURE (tls/ssl/none)" -Default "tls"
    $smtpUser = Read-Default -Prompt "AGELAI_SMTP_USER" -Default ""
    $smtpPassSecure = Read-Host "AGELAI_SMTP_PASS (puede ir vacio)" -AsSecureString
    $smtpPass = Convert-SecureToPlainText -SecureValue $smtpPassSecure
    $smtpFromEmail = Read-Required -Prompt "AGELAI_SMTP_FROM_EMAIL"
    $smtpFromName = Read-Default -Prompt "AGELAI_SMTP_FROM_NAME" -Default "Club Agelai"
}

$lines = @(
    "# Archivo local generado por tools/setup_local_secrets.ps1",
    "# No subir este archivo a repositorios publicos.",
    "",
    "AGELAI_DB_HOST=$(Escape-EnvValue -Value $dbHost)",
    "AGELAI_DB_PORT=$(Escape-EnvValue -Value $dbPort)",
    "AGELAI_DB_NAME=$(Escape-EnvValue -Value $dbName)",
    "AGELAI_DB_USER=$(Escape-EnvValue -Value $dbUser)",
    "AGELAI_DB_PASS=$(Escape-EnvValue -Value $dbPass)",
    "",
    "AGELAI_BASE_URL=$(Escape-EnvValue -Value $appBaseUrl)",
    "",
    "AGELAI_APP_SECRET=$(Escape-EnvValue -Value $appSecret)",
    "",
    "AGELAI_GOOGLE_CLIENT_ID=$(Escape-EnvValue -Value $googleClientId)",
    "AGELAI_GOOGLE_CLIENT_SECRET=$(Escape-EnvValue -Value $googleClientSecret)",
    "AGELAI_GOOGLE_REDIRECT_URI=$(Escape-EnvValue -Value $googleRedirectUri)",
    "",
    "AGELAI_SMTP_HOST=$(Escape-EnvValue -Value $smtpHost)",
    "AGELAI_SMTP_PORT=$(Escape-EnvValue -Value $smtpPort)",
    "AGELAI_SMTP_SECURE=$(Escape-EnvValue -Value $smtpSecure)",
    "AGELAI_SMTP_USER=$(Escape-EnvValue -Value $smtpUser)",
    "AGELAI_SMTP_PASS=$(Escape-EnvValue -Value $smtpPass)",
    "AGELAI_SMTP_FROM_EMAIL=$(Escape-EnvValue -Value $smtpFromEmail)",
    "AGELAI_SMTP_FROM_NAME=$(Escape-EnvValue -Value $smtpFromName)"
)

$content = ($lines -join [Environment]::NewLine) + [Environment]::NewLine
Set-Content -Path $envFilePath -Value $content -Encoding UTF8

Write-Host ""
Write-Host "Listo. Archivo creado/actualizado: $envFilePath" -ForegroundColor Green
Write-Host "Siguiente paso: reinicia el backend PHP para cargar estos secretos." -ForegroundColor Green
