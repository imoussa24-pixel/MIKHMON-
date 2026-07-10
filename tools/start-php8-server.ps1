param(
  [int] $Port = 8081
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root "php8/php.exe"
$ini = Join-Path $root "php8/php.ini"
$docroot = Join-Path $root "mikhmon"

if (-not (Test-Path $php)) {
  throw "PHP 8 introuvable: $php"
}

& $php -c $ini -S "127.0.0.1:$Port" -t $docroot
