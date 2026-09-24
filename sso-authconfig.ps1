<#
  PPDA Kerberos SSO - silent sign-in configuration (Edge + Chrome)
  ================================================================
  - Adds *.ppda.go.ug (helpdesk AND timesheet) to the Integrated Windows
    Authentication allow-lists of BOTH Microsoft Edge and Google Chrome.
  - Maps the host into the "Local intranet" zone so Windows automatically
    supplies the Kerberos ticket (no credential popup).
  - Restarts Edge/Chrome so the new settings take effect immediately.

  RUN AS ADMINISTRATOR:
      Right-click PowerShell > Run as administrator, then:
      powershell -ExecutionPolicy Bypass -File .\sso-authconfig.ps1

  After it finishes: open https://helpdesk.ppda.go.ug/pages/UI.php
  and verify with `klist` that HTTP/helpdesk.ppda.go.ug@PPDA.GO.UG appears.
#>

#Requires -RunAsAdministrator

$ErrorActionPreference = 'Stop'

$sHost = '*.ppda.go.ug'

function Set-ChromiumPolicy {
    param(
        [string]$RootKey,
        [string]$Name
    )
    $sPath = "HKLM:\SOFTWARE\Policies\$RootKey"
    if (-not (Test-Path $sPath)) { New-Item -Path $sPath -Force | Out-Null }
    foreach ($v in @('AuthServerAllowlist', 'AuthNegotiateDelegateAllowlist')) {
        New-ItemProperty -Path $sPath -Name $v -Value $sHost -PropertyType String -Force | Out-Null
        Write-Host "  [OK] $RootKey\$v = $sHost"
    }
}

function Set-IntranetZone {
    # Map https://helpdesk.ppda.go.ug and https://timesheet.ppda.go.ug into the
    # Local intranet zone so Windows automatically supplies the Kerberos ticket.
    foreach ($sHost in @('helpdesk','timesheet')) {
        foreach ($sRoot in @('HKLM','HKCU')) {
            $sPath = "$($sRoot):\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\$sHost"
            if (-not (Test-Path $sPath)) { New-Item -Path $sPath -Force | Out-Null }
            foreach ($v in @('http','https')) {
                New-ItemProperty -Path $sPath -Name $v -Value 1 -PropertyType DWord -Force | Out-Null
            }
            Write-Host "  [OK] $sRoot ...\ZoneMap\Domains\ppda.go.ug\$sHost => Local intranet (zone 1)"
        }
    }
}

function Restart-IfRunning {
    param([string]$ProcessName, [string]$ExeName)
    $p = Get-Process $ProcessName -ErrorAction SilentlyContinue
    if ($p) {
        Stop-Process -Name $ProcessName -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        Write-Host "  [OK] Restarted $ProcessName"
    } else {
        Write-Host "  [..] $ProcessName not running, nothing to restart"
    }
}

Write-Host 'PPDA Kerberos SSO configuration'
Write-Host '--------------------------------'

Write-Host '[1/3] Edge policies'
Set-ChromiumPolicy -RootKey 'Microsoft\Edge' -Name 'Edge'

Write-Host '[2/3] Chrome policies'
Set-ChromiumPolicy -RootKey 'Google\Chrome' -Name 'Chrome'

Write-Host '[3/3] Local intranet zone (Kerberos ticket supply)'
Set-IntranetZone

Write-Host ''
Write-Host 'Restarting browsers so the settings load...'
Restart-IfRunning -ProcessName 'msedge' -ExeName 'msedge.exe'
Restart-IfRunning -ProcessName 'chrome' -ExeName 'chrome.exe'

Write-Host ''
Write-Host 'Done. Re-open https://helpdesk.ppda.go.ug/pages/UI.php in your browser.'
Write-Host 'Then verify the ticket in a Command Prompt with:  klist'
Write-Host 'You should see:  HTTP/helpdesk.ppda.go.ug@PPDA.GO.UG'