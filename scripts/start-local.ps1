param([string]$Php = 'C:/laragon/bin/php/php-8.4.18-Win32-vs17-x64/php.exe')
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path -LiteralPath $Php)) { throw 'Supply your PHP executable with -Php.' }
$taskLogs = Join-Path $taskRoot '.tools'
New-Item -ItemType Directory -Force -Path $taskLogs | Out-Null
function Test-LocalPort([int]$Port) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
if ((Test-Path -LiteralPath (Join-Path $taskLogs 'mysql-data')) -and -not (Test-LocalPort 3307)) {
    Start-Process -FilePath 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqld.exe' -ArgumentList '--no-defaults','--basedir=C:/laragon/bin/mysql/mysql-8.0.30-winx64',"--datadir=$taskRoot/.tools/mysql-data",'--port=3307','--bind-address=127.0.0.1','--mysqlx=OFF',"--log-error=$taskRoot/.tools/mysql.log" -WindowStyle Hidden
}
$taskPidFile = Join-Path $taskLogs 'processes.json'
$taskProcesses = @{}
if (Test-Path -LiteralPath $taskPidFile) {
    $taskSavedProcesses = Get-Content -LiteralPath $taskPidFile -Raw | ConvertFrom-Json
    foreach ($taskProperty in $taskSavedProcesses.PSObject.Properties) { $taskProcesses[$taskProperty.Name] = $taskProperty.Value }
}
foreach ($taskService in @(
    @{Name='web'; Arguments=@('artisan','serve','--host=127.0.0.1','--port=8000')},
    @{Name='queue'; Arguments=@('artisan','queue:work','--tries=1','--timeout=60','--sleep=3')},
    @{Name='scheduler'; Arguments=@('artisan','schedule:work')}
)) {
    $taskExisting = $taskProcesses[$taskService.Name]
    if ($taskExisting -and (Get-Process -Id $taskExisting -ErrorAction SilentlyContinue)) { continue }
    if ($taskService.Name -eq 'web' -and (Test-LocalPort 8000)) { continue }
    $taskProcess = Start-Process -FilePath $Php -ArgumentList $taskService.Arguments -WorkingDirectory $taskRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $taskLogs ($taskService.Name+'.stdout.log')) -RedirectStandardError (Join-Path $taskLogs ($taskService.Name+'.stderr.log')) -PassThru
    $taskProcesses[$taskService.Name] = $taskProcess.Id
}
$taskProcesses | ConvertTo-Json | Set-Content -LiteralPath $taskPidFile
Write-Output 'Local preview: http://127.0.0.1:8000 | Admin: http://127.0.0.1:8000/admin'
