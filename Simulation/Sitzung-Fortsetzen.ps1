# Startet genau die Codex-Sitzung zur Planung des Auto-Simulators.
# Aufruf im Terminal: .\Sitzung-Fortsetzen.ps1
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$sessionId = '01a1101c-04b4-7a50-a014-7a0cf78e205d'

if (-not (Get-Command codex -ErrorAction SilentlyContinue)) {
    throw 'Codex wurde nicht im PATH gefunden. Bitte das Terminal mit installiertem Codex verwenden.'
}

Push-Location -LiteralPath $PSScriptRoot
try {
    Write-Host 'Setze die Sitzung zur Planung des Auto-Simulators fort ...'
    & codex resume $sessionId --cd $PSScriptRoot
    $resumeExitCode = $LASTEXITCODE
    if ($resumeExitCode -ne 0) {
        throw "Codex wurde mit Fehlercode $resumeExitCode beendet."
    }
}
finally {
    Pop-Location
}
