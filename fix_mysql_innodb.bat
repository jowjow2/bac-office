@echo off
echo ========================================
echo XAMPP MySQL InnoDB Corruption Fix
echo ========================================
echo.
echo This script will:
echo 1. Stop XAMPP services
echo 2. Restore MySQL data directory from data_OLD
echo 3. Delete corrupted InnoDB log files (ib_logfile0, ib_logfile1, ibtmp1)
echo 4. Start XAMPP services (MySQL will recreate logs)
echo.

REM Check if running as Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator.
    echo Right-click on this file and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

echo Stopping XAMPP services...
C:\xampp\xampp_stop.exe
timeout /t 5 /nobreak >nul

echo.
echo Checking for existing data directory...
if exist "C:\xampp\mysql\data" (
    echo Backing up existing data directory to data_BACKUP_%date:~-4,4%%date:~-7,2%%date:~-10,2%...
    ren "C:\xampp\mysql\data" "data_BACKUP_%date:~-4,4%%date:~-7,2%%date:~-10,2%"
)

echo.
echo Restoring MySQL data from data_OLD...
if exist "C:\xampp\mysql\data_OLD" (
    ren "C:\xampp\mysql\data_OLD" "data"
    echo Data directory restored.
) else (
    echo ERROR: data_OLD directory not found!
    echo Cannot restore MySQL data.
    pause
    exit /b 1
)

echo.
echo Removing corrupted InnoDB log files...
cd /d "C:\xampp\mysql\data"
if exist "ib_logfile0" (
    del /f /q "ib_logfile0"
    echo Deleted ib_logfile0
)
if exist "ib_logfile1" (
    del /f /q "ib_logfile1"
    echo Deleted ib_logfile1
)
if exist "ibtmp1" (
    del /f /q "ibtmp1"
    echo Deleted ibtmp1
)
echo InnoDB log files removed. Fresh logs will be created on startup.

echo.
echo Starting XAMPP services...
C:\xampp\xampp_start.exe
timeout /t 8 /nobreak >nul

echo.
echo ========================================
echo Fix complete!
echo.
echo Verify MySQL is running in XAMPP control panel.
echo The bac_office database and all tables are preserved.
echo ========================================
pause
