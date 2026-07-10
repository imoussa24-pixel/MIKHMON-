$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root "php8/php.exe"
$app = Join-Path $root "mikhmon"

if (-not (Test-Path $php)) {
  throw "PHP 8 introuvable: $php"
}

$files = & rg --files -g "*.php" $app
$errors = 0

foreach ($file in $files) {
  $output = & $php -l $file 2>&1
  if ($LASTEXITCODE -ne 0) {
    $errors++
    Write-Output "--- $file"
    Write-Output $output
  }
}

Write-Output "PHP 8 lint errors: $errors"
if ($errors -gt 0) {
  exit 1
}
