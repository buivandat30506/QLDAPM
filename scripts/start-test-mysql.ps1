param([string]$MySqlBase = 'C:\Program Files\MySQL\MySQL Server 8.0', [int]$Port = 3318)
$ErrorActionPreference = 'Stop'
$taskRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskLogs = Join-Path $taskRoot 'storage\app\mysql-testing'
$taskData = Join-Path $taskLogs 'data'
New-Item -ItemType Directory -Force -Path $taskData | Out-Null
$taskData = (Resolve-Path -LiteralPath $taskData).Path
if (-not $taskData.StartsWith($taskRoot + [System.IO.Path]::DirectorySeparatorChar)) { throw 'Test data must remain inside workspace.' }
$taskServer = Join-Path $MySqlBase 'bin\mysqld.exe'
$taskExisting = Get-CimInstance Win32_Process -Filter "Name = 'mysqld.exe'" | Where-Object {
    $_.ExecutablePath -eq $taskServer -and $_.CommandLine.IndexOf($taskData, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
}
if ($taskExisting) {
    if ($taskExisting.CommandLine -match "--port=$Port(?:\s|$)") {
        Write-Output "Isolated MySQL already running, PID $($taskExisting.ProcessId), localhost:$Port."
        exit 0
    }
    throw 'The isolated data directory is already served on another port. Existing process preserved.'
}
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) { throw "Port $Port belongs to another process; nothing was stopped." }
if (-not (Test-Path -LiteralPath (Join-Path $taskData 'mysql'))) {
    $taskInitArgs = @('--no-defaults', '--initialize-insecure', "--basedir=`"$MySqlBase`"", "--datadir=`"$taskData`"", '--console')
    $taskInit = Start-Process -FilePath $taskServer -ArgumentList $taskInitArgs -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput (Join-Path $taskLogs 'initialize.stdout.log') -RedirectStandardError (Join-Path $taskLogs 'initialize.stderr.log')
    if ($taskInit.ExitCode -ne 0) { throw 'MySQL initialization failed; inspect storage/app/mysql-testing/initialize.stderr.log.' }
}
$taskArgs = @('--no-defaults', "--basedir=`"$MySqlBase`"", "--datadir=`"$taskData`"", "--port=$Port", '--bind-address=127.0.0.1', '--mysqlx=OFF', '--innodb-buffer-pool-size=64M', '--console')
$taskProcess = Start-Process -FilePath $taskServer -ArgumentList $taskArgs -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskLogs 'stdout.log') -RedirectStandardError (Join-Path $taskLogs 'stderr.log')
$taskProcess.Id | Set-Content -LiteralPath (Join-Path $taskLogs 'server.pid')
$taskReady = $false
for ($attempt = 0; $attempt -lt 80; $attempt++) {
    if ($taskProcess.HasExited) { throw 'Isolated MySQL exited; inspect storage/app/mysql-testing/stderr.log.' }
    $taskClient = New-Object System.Net.Sockets.TcpClient
    try { $taskClient.Connect('127.0.0.1', $Port); $taskReady = $true; break } catch { Start-Sleep -Milliseconds 250 } finally { $taskClient.Dispose() }
}
if (-not $taskReady) { throw 'Isolated MySQL did not become reachable within 20 seconds; process retained for diagnosis.' }
Write-Output "Started isolated MySQL PID $($taskProcess.Id), localhost:$Port. Data: $taskData"
# Disposable synthetic test data only. Shutdown using mysqladmin --host=127.0.0.1 --port=3318 --user=root shutdown
