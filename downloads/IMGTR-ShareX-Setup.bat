@echo off
setlocal EnableExtensions
title IMGTR ShareX Setup
color 07
if /I "%~1"=="--verify" goto verify

echo.
echo  IMGTR ShareX Setup
echo  ==================
echo  This installer prepares Node.js LTS, the IMGTR CLI and the background bridge.
echo.

where node.exe >nul 2>nul
if errorlevel 1 goto install_node
node.exe -e "process.exit(Number(process.versions.node.split('.')[0]) >= 20 ? 0 : 1)" >nul 2>nul
if errorlevel 1 goto install_node
goto node_ready

:install_node
echo [1/4] Node.js LTS is not installed. Installing it now...
where winget.exe >nul 2>nul
if errorlevel 1 goto install_node_fallback
winget install --exact --id OpenJS.NodeJS.LTS --accept-package-agreements --accept-source-agreements
if errorlevel 1 goto install_node_fallback
goto refresh_path

:install_node_fallback
echo       Using the direct download for the current Node.js LTS installer...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; [Net.ServicePointManager]::SecurityProtocol=[Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12; $arch=if([Environment]::Is64BitOperatingSystem){'x64'}else{'x86'}; $artifact='win-'+$arch+'-msi'; $releases=Invoke-RestMethod -UseBasicParsing -Uri 'https://nodejs.org/dist/index.json'; $release=$releases | Where-Object { $_.lts -and $_.files -contains $artifact } | Select-Object -First 1; if(-not $release){throw 'Node.js LTS release could not be resolved.'}; $version=[string]$release.version; if($version -notmatch '^v\d+\.\d+\.\d+$'){throw 'Node.js LTS version is invalid.'}; $name='node-'+$version+'-'+$arch+'.msi'; $base='https://nodejs.org/dist/'+$version+'/'; $target=Join-Path $env:TEMP $name; try { Invoke-WebRequest -UseBasicParsing -Uri ([Uri]($base+$name)) -OutFile $target; $sums=(Invoke-WebRequest -UseBasicParsing -Uri ([Uri]($base+'SHASUMS256.txt'))).Content; $match=[regex]::Match($sums,'(?m)^([a-f0-9]{64})\s+\*?'+[regex]::Escape($name)+'$'); if(-not $match.Success){throw 'Node.js checksum could not be resolved.'}; $actual=(Get-FileHash -Algorithm SHA256 -LiteralPath $target).Hash; if($actual -ne $match.Groups[1].Value){throw 'Node.js installer checksum verification failed.'}; $process=Start-Process msiexec.exe -Verb RunAs -ArgumentList @('/i',('"'+$target+'"'),'/passive','/norestart') -Wait -PassThru; if(@(0,1641,3010) -notcontains $process.ExitCode){throw ('Node.js installer failed with exit code '+$process.ExitCode+'.')} } finally { Remove-Item -LiteralPath $target -Force -ErrorAction SilentlyContinue }"
if errorlevel 1 goto failed_node

:refresh_path
set "PATH=%ProgramFiles%\nodejs;%APPDATA%\npm;%PATH%"

:node_ready
echo [2/4] Checking Node.js and npm...
where node.exe >nul 2>nul
if errorlevel 1 goto restart_required
node.exe -e "process.exit(Number(process.versions.node.split('.')[0]) >= 20 ? 0 : 1)" >nul 2>nul
if errorlevel 1 goto restart_required
where npm.cmd >nul 2>nul
if errorlevel 1 goto restart_required
node.exe --version
if errorlevel 1 goto restart_required
call npm.cmd --version
if errorlevel 1 goto restart_required

echo [3/4] Installing the official IMGTR SDK and CLI...
call npm.cmd install -g --allow-scripts=sharp https://github.com/sedatyildiznet/imgtr/releases/download/downloads/imgtr-sdk-1.2.0.tgz
if errorlevel 1 (
  echo       Retrying with compatibility options...
  call npm.cmd install -g https://github.com/sedatyildiznet/imgtr/releases/download/downloads/imgtr-sdk-1.2.0.tgz
)
if errorlevel 1 goto failed_cli
set "PATH=%APPDATA%\npm;%PATH%"

echo [4/4] Enabling the invisible ShareX bridge and automatic startup...
where imgtr.cmd >nul 2>nul
if errorlevel 1 goto failed_cli
call imgtr.cmd sharex install
if errorlevel 1 goto failed_bridge
call imgtr.cmd sharex status
if errorlevel 1 goto failed_bridge

echo.
echo  Setup completed successfully.
echo  You can close this window. The IMGTR bridge now runs in the background.
echo  Import your personal IMGTR.sxcu file into ShareX to finish the setup.
echo.
pause
exit /b 0

:verify
echo IMGTR ShareX Setup is readable.
exit /b 0

:restart_required
echo.
echo  Node.js was installed, but Windows has not refreshed PATH yet.
echo  Close this window, run this setup file once more, and installation will continue.
goto failed

:failed_node
echo.
echo  Node.js LTS could not be installed. Review the error above and try again.
goto failed

:failed_cli
echo.
echo  The IMGTR CLI could not be installed. Check your internet connection and try again.
goto failed

:failed_bridge
echo.
echo  The IMGTR background bridge could not be started.
echo  Run: imgtr sharex status

:failed
echo.
pause
exit /b 1
