$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
python dev/package.py
if ($LASTEXITCODE) { throw 'Packaging failed' }
