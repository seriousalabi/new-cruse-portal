$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
& docker compose stop
