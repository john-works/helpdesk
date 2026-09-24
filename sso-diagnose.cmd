@echo off
REM PPDA SSO diagnostic - double-click, then copy/paste the output back.
setlocal
echo ==========================================================
echo  PPDA SSO DIAGNOSTICS
echo ==========================================================
echo.
echo [1] What kind of machine am I?
echo     Windows account domain: %USERDOMAIN%
echo     Computer name: %COMPUTERNAME%
systeminfo | findstr /B /I "Domain OS Name"
echo.
echo [2] Can this PC get a Kerberos ticket for the iTop server?
echo     (the following requests a ticket just like the browser would)
echo.
echo  -- HTTP/helpdesk.ppda.go.ug --
klist get HTTP/helpdesk.ppda.go.ug
echo.
echo  -- HTTP/timesheet.ppda.go.ug --
klist get HTTP/timesheet.ppda.go.ug
echo.
echo [3] Local time vs domain controller (clock skew check)
w32tm /stripchart /computer:primary-dc.ppda.go.ug /samples:1
echo.
echo [4] DNS resolution
nslookup helpdesk.ppda.go.ug
echo.
echo [5] Browser IWA allow-list actually applied?
reg query "HKLM\SOFTWARE\Policies\Microsoft\Edge" /v AuthServerAllowlist 2>nul
reg query "HKLM\SOFTWARE\Policies\Google\Chrome" /v AuthServerAllowlist 2>nul
echo.
echo ==========================================================
echo Done. Select all, copy, and paste it back.
echo ==========================================================
endlocal
pause