@echo off
REM ============================================================
REM  PPDA Kerberos SSO - silent sign-in config (Edge + Chrome)
REM  - Adds https://helpdesk.ppda.go.ug to the Integrated Windows
REM    Authentication allow-lists of BOTH Edge and Chrome.
REM  - Maps the host into the "Local intranet" zone so Windows
REM    supplies the Kerberos ticket automatically (no popup).
REM  - Restarts Edge/Chrome so settings apply immediately.
REM
REM  Double-click this file. If prompted, allow it to run as
REM  Administrator.
REM ============================================================

REM --- Auto-elevate if not running as Administrator ------------
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting administrator privileges...
    powershell -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo.
echo PPDA Kerberos SSO configuration
echo --------------------------------

REM --- [1] Edge policies --------------------------------------
echo [1/3] Edge policies
reg add "HKLM\SOFTWARE\Policies\Microsoft\Edge" /v AuthServerAllowlist /t REG_SZ /d "*.ppda.go.ug" /f >nul
reg add "HKLM\SOFTWARE\Policies\Microsoft\Edge" /v AuthNegotiateDelegateAllowlist /t REG_SZ /d "*.ppda.go.ug" /f >nul
echo   [OK] Microsoft\Edge auth allow-lists = *.ppda.go.ug

REM --- [2] Chrome policies ------------------------------------
echo [2/3] Chrome policies
reg add "HKLM\SOFTWARE\Policies\Google\Chrome" /v AuthServerAllowlist /t REG_SZ /d "*.ppda.go.ug" /f >nul
reg add "HKLM\SOFTWARE\Policies\Google\Chrome" /v AuthNegotiateDelegateAllowlist /t REG_SZ /d "*.ppda.go.ug" /f >nul
echo   [OK] Google\Chrome auth allow-lists = *.ppda.go.ug

REM --- [3] Local intranet zone (ticket supply) ----------------
echo [3/3] Local intranet zone
reg add "HKLM\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v http /t REG_DWORD /d 1 /f >nul
reg add "HKLM\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v https /t REG_DWORD /d 1 /f >nul
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v http /t REG_DWORD /d 1 /f >nul
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v https /t REG_DWORD /d 1 /f >nul
reg add "HKLM\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\timesheet" /v http /t REG_DWORD /d 1 /f >nul
reg add "HKLM\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\timesheet" /v https /t REG_DWORD /d 1 /f >nul
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\timesheet" /v http /t REG_DWORD /d 1 /f >nul
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\timesheet" /v https /t REG_DWORD /d 1 /f >nul
echo   [OK] helpdesk + timesheet mapped to Local intranet (zone 1)
echo.
echo   Verification (each must show "http" and "https" with value 0x1):
reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v https
reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\timesheet" /v https
reg query "HKLM\Software\Microsoft\Windows\CurrentVersion\Internet Settings\ZoneMap\Domains\ppda.go.ug\helpdesk" /v https

echo.
echo Restarting browsers so the settings load...
taskkill /F /IM msedge.exe >nul 2>&1
if %errorlevel% equ 0 ( echo   [OK] Edge closed ) else ( echo   [..] Edge was not running )
taskkill /F /IM chrome.exe >nul 2>&1
if %errorlevel% equ 0 ( echo   [OK] Chrome closed ) else ( echo   [..] Chrome was not running )
timeout /t 2 /nobreak >nul

echo.
echo Done. Re-open https://helpdesk.ppda.go.ug/pages/UI.php in your browser.
echo Then verify the ticket in a Command Prompt with:  klist
echo You should see:  HTTP/helpdesk.ppda.go.ug@PPDA.GO.UG
echo.
pause