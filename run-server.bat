@echo off
REM AMIMS Development Server Launcher
REM This script starts the PHP dev server with SQLite support enabled

setlocal enabledelayedexpansion

set "PHP_EXE=C:\Users\Amara\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
set "EXT_DIR=C:\Users\Amara\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\ext"

echo Starting AMIMS Development Server...
echo URL: http://127.0.0.1:8888
echo.
echo Press Ctrl+C to stop the server
echo.

cd /d "%~dp0"

"%PHP_EXE%" -d extension_dir="%EXT_DIR%" -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8888

pause
