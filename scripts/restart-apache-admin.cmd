@echo off
REM Restart Apache to apply php.ini / httpd.conf changes (run as Administrator).
echo Restarting Apache2.4...
net stop Apache2.4
net start Apache2.4
echo.
echo Test: http://pay.tesnet.xyz/  (add 127.0.0.1 pay.tesnet.xyz to hosts if needed)
echo Or:   http://localhost/hotspot-pay/public/
pause
