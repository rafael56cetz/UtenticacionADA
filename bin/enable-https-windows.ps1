#Requires -RunAsAdministrator
param([string]$LanIp = '192.168.1.79', [int]$PrefixLength = 24, [switch]$EnableSiteProxy)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$privateDir = Join-Path $taskRoot 'storage/private/tls'
if (-not (Test-Path -LiteralPath (Join-Path $privateDir 'site.pem'))) { throw 'Primero prepara el certificado y el VirtualHost HTTPS siguiendo el manual.' }
Start-Transcript -Path (Join-Path $privateDir 'windows-https-setup.log') -Append | Out-Null
$domain = 'acceso.ada.test'
$ip = [Net.IPAddress]::Parse($LanIp)
if ($ip.AddressFamily -ne [Net.Sockets.AddressFamily]::InterNetwork -or $PrefixLength -lt 16 -or $PrefixLength -gt 30) { throw 'Se requiere una IPv4 local y un prefijo entre 16 y 30.' }
$assigned = Get-NetIPAddress -AddressFamily IPv4 -IPAddress $LanIp -ErrorAction Stop
if ($assigned.PrefixLength -ne $PrefixLength) { throw 'La direccion o el prefijo ya cambiaron. Revisar la red antes de continuar.' }
$bytes = $ip.GetAddressBytes()
if (-not ($bytes[0] -eq 10 -or ($bytes[0] -eq 172 -and $bytes[1] -ge 16 -and $bytes[1] -le 31) -or ($bytes[0] -eq 192 -and $bytes[1] -eq 168))) { throw 'Utiliza solamente una red privada.' }
$networkBytes = for ($i=0; $i -lt 4; $i++) { $bits=[Math]::Max(0,[Math]::Min(8,$PrefixLength-8*$i)); $mask=if($bits -eq 0){0}else{256-[Math]::Pow(2,8-$bits)}; [byte]($bytes[$i] -band [int]$mask) }
$subnet = ($networkBytes -join '.') + '/' + $PrefixLength
$hostsPath = Join-Path $env:WINDIR 'System32/drivers/etc/hosts'
$hostsText = [IO.File]::ReadAllText($hostsPath)
$existing = $hostsText -split '\r?\n' | Where-Object { ($_ -split '#',2)[0] -match '(^|\s)acceso\.ada\.test(\s|$)' }
if ($existing -and ($existing.Count -ne 1 -or ($existing -split '\s+')[0] -ne $LanIp)) { throw 'Ya hay una entrada hosts distinta para acceso.ada.test; revisarla sin sobrescribirla.' }
if (-not $existing) {
    $backupPath = Join-Path $privateDir ('hosts.before-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.txt')
    [IO.File]::WriteAllText($backupPath,$hostsText,[Text.UTF8Encoding]::new($false))
    [IO.File]::AppendAllText($hostsPath,"`r`n$LanIp acceso.ada.test # Acceso ADA prueba local`r`n",[Text.UTF8Encoding]::new($false))
}
$rules = @(@{Name='AccesoADA-HTTPS';Port=8443;Protocol='TCP'},@{Name='AccesoADA-DNS';Port=53;Protocol='UDP'})
if ($EnableSiteProxy) { $rules += @{Name='AccesoADA-SiteProxy';Port=8899;Protocol='TCP'} }
foreach ($rule in $rules) {
    $previous = Get-NetFirewallRule -Name $rule.Name -ErrorAction SilentlyContinue
    if ($previous) {
        if ($previous.Group -ne 'Acceso ADA prueba local') { throw 'Hay una regla ajena con el mismo nombre; no se modifica.' }
        $previous | Set-NetFirewallRule -Enabled True -Action Allow -Direction Inbound -Profile Any -LocalAddress $LanIp -RemoteAddress $subnet -Protocol $rule.Protocol -LocalPort $rule.Port | Out-Null
    } else {
        New-NetFirewallRule -Name $rule.Name -DisplayName $rule.Name -Group 'Acceso ADA prueba local' -Direction Inbound -Action Allow -Protocol $rule.Protocol -LocalPort $rule.Port -LocalAddress $LanIp -RemoteAddress $subnet -Profile Any | Out-Null
    }
}
Clear-DnsClientCache
@{ip=$LanIp;subnet=$subnet;domain=$domain;completedAt=(Get-Date).ToString('o')} | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $privateDir 'windows-https-ready.json')
if ($EnableSiteProxy) { Write-Host 'Conexion local del sitio autorizada en el puerto 8899.' -ForegroundColor Green }
else { Write-Host 'Hosts y firewall listos. Reinicia Apache desde el icono de WAMP.' -ForegroundColor Green }
Stop-Transcript | Out-Null
