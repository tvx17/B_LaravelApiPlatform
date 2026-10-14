#requires -Version 5.1
<#
.SYNOPSIS
  Nimmt die Codex-Sitzung dieses Projekts wieder auf.
.DESCRIPTION
  Voraussetzung: Codex CLI im PATH, Anmeldung und gespeicherter Sitzungsverlauf
  im verwendeten Codex-Benutzerprofil. Das Skript ist keine Verlaufssicherung.
.EXAMPLE
  .\Resume-Session.ps1
#>
[CmdletBinding()]
param(
  [switch]$CheckOnly
)

$ErrorActionPreference = 'Stop'
$sessionId = '01a119bb-976a-7db0-8d50-ead7324d5819'
$projectDirectory = $PSScriptRoot

try {
  $codexCommand = Get-Command codex -CommandType Application, ExternalScript -ErrorAction Stop | Select-Object -First 1
  if ($CheckOnly) {
    Write-Output "Codex: $($codexCommand.Source)"
    Write-Output "Projekt: $projectDirectory"
    Write-Output "Sitzung: $sessionId"
    exit 0
  }

  Push-Location -LiteralPath $projectDirectory
  try {
    & $codexCommand.Source resume $sessionId --cd $projectDirectory
    $resumeExitCode = $LASTEXITCODE
  }
  finally {
    Pop-Location
  }

  if ($resumeExitCode -ne 0) {
    Write-Warning 'Wiederaufnahme fehlgeschlagen. Prüfe Anmeldung und ob der Sitzungsverlauf im verwendeten Codex-Profil noch vorhanden ist.'
    exit $resumeExitCode
  }
}
catch {
  Write-Error "Codex konnte nicht gestartet werden: $($_.Exception.Message)" -ErrorAction Continue
  exit 1
}
