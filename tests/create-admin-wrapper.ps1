# Synthetic input only. PHP is replaced with a test sink; no database changes.
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$temporaryRoot = Join-Path $taskRoot '.tmp'
[IO.Directory]::CreateDirectory($temporaryRoot) | Out-Null
$sinkPath = Join-Path $temporaryRoot ('admin-input-test-' + [Guid]::NewGuid().ToString('N') + '.ps1')
$global:adminTestAnswers = @('test.account', 'Synthetic user', 'Ficticia12345a', 'FICTICIA12345A', 'short', 'short', '  Ficticia12345  ', '  Ficticia12345  ')
$global:adminTestAnswerIndex = 0
$global:adminTestCalls = 0
function Read-Host {
    param([string]$Prompt, [switch]$AsSecureString)
    if ($global:adminTestAnswerIndex -ge $global:adminTestAnswers.Count) { throw 'Unexpected extra prompt.' }
    $value = $global:adminTestAnswers[$global:adminTestAnswerIndex++]
    if ($AsSecureString) { return ConvertTo-SecureString -String $value -AsPlainText -Force }
    return $value
}
$sink = @'
$values = @($input)
if ($values.Count -ne 4) { throw 'Wrong field count.' }
if ($values[0] -cne 'test.account' -or $values[1] -cne 'Synthetic user') { throw 'Account input lost.' }
if ($values[2] -cne '  Ficticia12345  ' -or $values[3] -cne $values[2]) { throw 'Password input changed.' }
if ($args -notcontains '--quiet-prompts') { throw 'Duplicate prompts not suppressed.' }
$global:adminTestCalls++
$global:LASTEXITCODE = 0
'@
try {
    [IO.File]::WriteAllText($sinkPath, $sink, [Text.UTF8Encoding]::new($true))
    & (Join-Path $taskRoot 'bin/create-admin.ps1') -Php $sinkPath
    if ($global:adminTestAnswerIndex -ne 8 -or $global:adminTestCalls -ne 1) { throw 'Invalid attempts reached PHP or valid attempt was lost.' }
    $bytes = [IO.File]::ReadAllBytes((Join-Path $taskRoot 'bin/create-admin.ps1'))
    if ($bytes[0] -ne 239 -or $bytes[1] -ne 187 -or $bytes[2] -ne 191) { throw 'UTF-8 BOM missing for Windows PowerShell.' }
    Write-Output 'PASS: case-sensitive confirmation, retry, length check, exact input, single submission, UTF-8 BOM.'
} finally {
    Remove-Item -LiteralPath $sinkPath -ErrorAction SilentlyContinue
    Remove-Item Function:\Read-Host
    Remove-Variable adminTestCalls -Scope Global -ErrorAction SilentlyContinue
    Remove-Variable adminTestAnswers,adminTestAnswerIndex -Scope Global -ErrorAction SilentlyContinue
}
