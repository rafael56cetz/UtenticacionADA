param([string]$Php = 'C:\wamp64\bin\php\php8.3.28\php.exe')
$ErrorActionPreference = 'Stop'
$OutputEncoding = [Text.UTF8Encoding]::new($false)
if (-not (Test-Path -LiteralPath $Php -PathType Leaf)) { throw 'Indica la ruta de PHP 8.3 con -Php.' }
$identifier = Read-Host 'Identificador del administrador (letras/numeros, sin espacios)'
$displayName = Read-Host 'Nombre visible'
while ($true) {
    $securePassword = $secureConfirmation = $null
    $passwordPointer = $confirmationPointer = [IntPtr]::Zero
    $password = $confirmation = $null
    try {
        $securePassword = Read-Host 'Contraseña de recuperación (12 a 128 caracteres)' -AsSecureString
        $secureConfirmation = Read-Host 'Repite exactamente la misma contraseña' -AsSecureString
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureConfirmation)
        $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
        $confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)
        if (-not [string]::Equals($password, $confirmation, [StringComparison]::Ordinal)) {
            Write-Host 'Las contraseñas no coinciden. Inténtalo de nuevo; no se creó ninguna cuenta.' -ForegroundColor Yellow
            continue
        }
        $length = [Text.Encoding]::UTF8.GetByteCount($password)
        if ($length -lt 12 -or $length -gt 128) {
            Write-Host 'Usa de 12 a 128 caracteres (máximo 128 bytes UTF-8). Vuelve a introducir la contraseña.' -ForegroundColor Yellow
            continue
        }
        @($identifier, $displayName, $password, $confirmation) | & $Php (Join-Path $PSScriptRoot 'console.php') admin:create --quiet-prompts
        if ($LASTEXITCODE -ne 0) {
            Write-Host 'No se creó la cuenta. Revisa el mensaje anterior y vuelve a ejecutar el comando.' -ForegroundColor Yellow
            return
        }
        Write-Host 'Abre http://localhost:8088/recovery e inicia sesión con tu identificador y contraseña.' -ForegroundColor Green
        break
    } finally {
        if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
        if ($confirmationPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer) }
        $password = $confirmation = $null
        if ($null -ne $securePassword) { $securePassword.Dispose() }
        if ($null -ne $secureConfirmation) { $secureConfirmation.Dispose() }
    }
}