$ErrorActionPreference = 'Stop'
Push-Location (Join-Path $PSScriptRoot '..')
try {
    python scripts/demo.py @args
    if ($LASTEXITCODE -ne 0) { throw 'Demo setup failed. See the error above; existing data was preserved.' }
} finally {
    Pop-Location
}
