@echo off
echo ========================================
echo XAMPP MySQL Data Directory Fix
echo ========================================
echo.

REM Check if running as Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator.
    echo Right-click on this file and select "Run as administrator"
    pause
    exit /b 1
)

echo Stopping XAMPP services...
C:\xampp\xampp_stop.exe
timeout /t 3 /nobreak >nul

echo.
echo Removing empty/missing data directory (if exists)...
if exist "C:\xampp\mysql\data" (
    echo Found existing data directory, backing up to data_BACKUP...
    ren "C:\xampp\mysql\data" "data_BACKUP"
)

echo.
echo Restoring MySQL data from data_OLD...
if exist "C:\xampp\mysql\data_OLD" (
    ren "C:\xampp\mysql\data_OLD" "data"
    echo MySQL data directory restored successfully.
) else (
    echo ERROR: data_OLD directory not found!
    echo Cannot restore MySQL data.
    pause
    exit /b 1
)

echo.
echo Starting XAMPP services...
C:\xampp\xampp_start.exe
timeout /t 5 /nobreak >nul

echo.
echo ========================================
echo Fix complete! MySQL should now be running.
echo Check the XAMPP control panel for status.
echo ========================================
pause
