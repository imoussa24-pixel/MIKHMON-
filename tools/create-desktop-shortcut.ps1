param(
  [string] $ShortcutName = "MIKHMON PRO ADMIN.lnk"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$launcher = Join-Path $root "lanceur.exe"
$oldServer = Join-Path $root "MikhmonServer.exe"

if (-not (Test-Path $launcher)) {
  throw "lanceur.exe introuvable: $launcher"
}

$desktop = [Environment]::GetFolderPath("Desktop")
$shortcutPath = Join-Path $desktop $ShortcutName

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $launcher
$shortcut.WorkingDirectory = $root
$shortcut.Description = "Lancer MIKHMON PRO ADMIN"
if (Test-Path $oldServer) {
  $shortcut.IconLocation = "$oldServer,0"
} else {
  $shortcut.IconLocation = "$launcher,0"
}
$shortcut.Save()

Get-Item $shortcutPath | Select-Object FullName, LastWriteTime
