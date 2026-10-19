param([string]$Username, [switch]$CopyPassword)

$ErrorActionPreference = 'Stop'
$taskRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskProfile = Join-Path $taskRoot 'storage\app\private\demo-runtime.json'
if (-not (Test-Path -LiteralPath $taskProfile -PathType Leaf)) {
    throw 'Local demo profile not found. Run scripts/setup-local-demo.php first.'
}
try {
    $taskRuntime = Get-Content -LiteralPath $taskProfile -Raw -Encoding UTF8 | ConvertFrom-Json
} catch {
    throw 'Cannot read the local demo profile. Its contents were not printed.'
}
if ($taskRuntime.mode -ne 'local-demo' -or $taskRuntime.database -notmatch '^airline_demo_[a-zA-Z0-9_]+$') {
    throw 'This helper only accepts an isolated local demo profile.'
}

if (-not $CopyPassword) {
    foreach ($taskAccount in $taskRuntime.accounts) {
        [PSCustomObject]@{ Username = $taskAccount.username; Roles = ($taskAccount.roles -join ', ') }
    }
    Write-Output 'Copy a decoded password: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/demo-credentials.ps1 -Username demo_admin -CopyPassword'
    return
}
if ([string]::IsNullOrWhiteSpace($Username)) {
    throw 'Specify -Username with -CopyPassword.'
}
$taskMatches = @($taskRuntime.accounts | Where-Object { $_.username -ceq $Username })
if ($taskMatches.Count -ne 1 -or $taskMatches[0].password -isnot [string] -or [string]::IsNullOrEmpty($taskMatches[0].password)) {
    throw 'Demo account not found or password unavailable. No clipboard change was requested.'
}

# JSON decoding preserves quotes/backslashes exactly; never print or re-serialize the password.
try {
    Set-Clipboard -Value $taskMatches[0].password
} catch {
    throw 'Clipboard unavailable. Run this command in a local Windows PowerShell session.'
}
Write-Output "Password copied for $Username. Paste into the password field at http://127.0.0.1:8000/login."
