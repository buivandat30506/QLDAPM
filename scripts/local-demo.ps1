param([ValidateSet('start','stop','status')][string]$Action = 'status', [int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$taskRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskLauncher = Join-Path $taskRoot 'scripts\demo.php'
$taskState = Join-Path $taskRoot 'storage\app\private\demo-services.json'
$taskPhp = (Get-Command php).Source

function Get-VerifiedDemoProcess($entry) {
    $taskProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $($entry.pid)" -ErrorAction SilentlyContinue
    if ($null -eq $taskProcess) { return $null }
    if ($taskProcess.ExecutablePath -ne $taskPhp -or -not $taskProcess.CommandLine.Contains($taskLauncher)) { return $null }
    if ($taskProcess.CreationDate.ToUniversalTime().ToString('o') -ne $entry.created_at_utc) { return $null }
    return $taskProcess
}

if ($Action -eq 'stop' -or $Action -eq 'status') {
    if (-not (Test-Path -LiteralPath $taskState)) { Write-Output 'No managed demo processes.'; exit 0 }
    $taskEntries = Get-Content -LiteralPath $taskState -Raw | ConvertFrom-Json
    foreach ($entry in $taskEntries) {
        $taskVerified = Get-VerifiedDemoProcess $entry
        if ($null -eq $taskVerified) { Write-Output "$($entry.name): stopped or identity changed; no action."; continue }
        if ($Action -eq 'stop') {
            # Only the verified launcher and its own descendants, never a port-wide kill.
            & taskkill.exe /PID $entry.pid /T /F | Out-Null
            Write-Output "$($entry.name): stopped."
        } else { Write-Output "$($entry.name): running, PID $($entry.pid)." }
    }
    exit 0
}

if (-not (Test-Path -LiteralPath (Join-Path $taskRoot 'storage\app\private\demo-runtime.json'))) { throw 'Run php scripts/setup-local-demo.php first.' }
if (Test-Path -LiteralPath $taskState) {
    foreach ($entry in (Get-Content -LiteralPath $taskState -Raw | ConvertFrom-Json)) {
        if ($null -ne (Get-VerifiedDemoProcess $entry)) { throw 'Managed demo is already running. Use status/stop.' }
    }
}
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) { throw "Port $Port is already in use; no existing server was stopped." }
$taskEntries = @()
$taskCommands = @(
    @{ name='web'; arguments=@('serve','--host=127.0.0.1',"--port=$Port") },
    @{ name='worker'; arguments=@('queue:work','--sleep=1','--tries=3','--timeout=30') },
    @{ name='scheduler'; arguments=@('schedule:work') }
)
foreach ($entry in $taskCommands) {
    $taskArgs = @("`"$taskLauncher`"") + $entry.arguments
    $taskProcess = Start-Process -FilePath $taskPhp -ArgumentList $taskArgs -WorkingDirectory $taskRoot -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $taskRoot "storage\logs\demo-$($entry.name).stdout.log") `
        -RedirectStandardError (Join-Path $taskRoot "storage\logs\demo-$($entry.name).stderr.log")
    $taskCim = Get-CimInstance Win32_Process -Filter "ProcessId = $($taskProcess.Id)"
    $taskEntries += @{ name=$entry.name; pid=$taskProcess.Id; created_at_utc=$taskCim.CreationDate.ToUniversalTime().ToString('o') }
    $taskEntries | ConvertTo-Json | Set-Content -LiteralPath $taskState -Encoding UTF8
    Write-Output "$($entry.name): started hidden, PID $($taskProcess.Id)."
}
Write-Output "Demo URL: http://127.0.0.1:$Port/login. Logs: storage/logs/demo-*.log"
