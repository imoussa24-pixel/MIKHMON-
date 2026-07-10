param(
  [string] $Output = ""
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$src = Join-Path $PSScriptRoot "launcher-src/Lanceur.cs"
$buildDir = Join-Path $env:TEMP "mikhmon-lanceur-build"

if ([string]::IsNullOrWhiteSpace($Output)) {
  $Output = Join-Path $root "lanceur.exe"
}

$csc64 = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
$csc32 = "C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe"

if (Test-Path $csc64) {
  $csc = $csc64
} elseif (Test-Path $csc32) {
  $csc = $csc32
} else {
  throw "Compilateur C# introuvable."
}

if (-not (Test-Path $src)) {
  throw "Source lanceur introuvable: $src"
}

if (Test-Path $buildDir) {
  Remove-Item -LiteralPath $buildDir -Recurse -Force
}
New-Item -ItemType Directory -Path $buildDir | Out-Null

$tmpSrc = Join-Path $buildDir "Lanceur.cs"
$tmpOutput = Join-Path $buildDir "lanceur.exe"
Copy-Item -LiteralPath $src -Destination $tmpSrc -Force

$args = @(
  "/nologo",
  "/target:winexe",
  "/platform:anycpu",
  "/optimize+",
  "/out:$tmpOutput",
  "/reference:System.dll",
  "/reference:System.Core.dll",
  "/reference:System.Drawing.dll",
  "/reference:System.Windows.Forms.dll"
)
$args += $tmpSrc

Push-Location $buildDir
try {
  & $csc @args
} finally {
  Pop-Location
}
if ($LASTEXITCODE -ne 0) {
  throw "Compilation du lanceur echouee."
}
if (-not (Test-Path $tmpOutput)) {
  throw "Compilation terminee mais lanceur.exe introuvable dans $buildDir"
}

[System.IO.File]::Copy($tmpOutput, $Output, $true)

Get-Item $Output | Select-Object FullName, Length, LastWriteTime
