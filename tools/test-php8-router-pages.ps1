param(
  [string] $BaseUrl = "http://127.0.0.1:8081",
  [string] $User = "",
  [string] $Password = "",
  [Parameter(Mandatory = $true)] [string] $Session
)

$ErrorActionPreference = "Stop"

function ConvertFrom-MikhmonEncrypted {
  param(
    [string] $Value,
    [string] $Key = "128"
  )

  if ([string]::IsNullOrEmpty($Value)) {
    return ""
  }

  $bytes = [Convert]::FromBase64String($Value)
  $chars = New-Object System.Collections.Generic.List[char]
  for ($i = 0; $i -lt $bytes.Length; $i++) {
    $keyIndex = ($i % $Key.Length) - 1
    if ($keyIndex -lt 0) {
      $keyIndex = $Key.Length - 1
    }
    $chars.Add([char]($bytes[$i] - [byte][char]$Key[$keyIndex]))
  }
  return -join $chars
}

function Get-MikhmonCredentialFromConfig {
  $root = Split-Path -Parent $PSScriptRoot
  $configPath = Join-Path $root "mikhmon/include/config.php"
  if (-not (Test-Path $configPath)) {
    throw "Config Mikhmon introuvable: $configPath"
  }

  $configText = Get-Content $configPath -Raw
  $userMatch = [regex]::Match($configText, '\$data\[''mikhmon''\].*?''1''=>''[^<]*<\|<([^'']*)''')
  $passMatch = [regex]::Match($configText, '\$data\[''mikhmon''\].*?''mikhmon>\|>([^'']*)''')

  if (-not $userMatch.Success -or -not $passMatch.Success) {
    throw "Impossible de lire les identifiants admin Mikhmon depuis config.php"
  }

  [pscustomobject]@{
    User = $userMatch.Groups[1].Value
    Password = ConvertFrom-MikhmonEncrypted -Value $passMatch.Groups[1].Value
  }
}

function Test-TikrasPage {
  param(
    [string] $Path,
    [Microsoft.PowerShell.Commands.WebRequestSession] $WebSession
  )

  $url = $BaseUrl.TrimEnd("/") + "/" + $Path.TrimStart("/")
  $response = Invoke-WebRequest -UseBasicParsing -WebSession $WebSession -Uri $url -TimeoutSec 30
  $body = [string] $response.Content
  $hasRuntimeError = $body -match "Fatal error|Parse error|Warning:|Notice:"
  [pscustomobject]@{
    Url = $url
    Status = $response.StatusCode
    Bytes = $body.Length
    RuntimeError = $hasRuntimeError
  }
}

if ([string]::IsNullOrEmpty($User) -or [string]::IsNullOrEmpty($Password)) {
  $configCredential = Get-MikhmonCredentialFromConfig
  if ([string]::IsNullOrEmpty($User)) {
    $User = $configCredential.User
  }
  if ([string]::IsNullOrEmpty($Password)) {
    $Password = $configCredential.Password
  }
}

$loginUrl = $BaseUrl.TrimEnd("/") + "/admin.php?id=login"
$login = Invoke-WebRequest -UseBasicParsing -SessionVariable web -Uri $loginUrl -TimeoutSec 30
if ($login.StatusCode -ne 200) {
  throw "Login page unavailable: $($login.StatusCode)"
}

$loginResponse = Invoke-WebRequest -UseBasicParsing -WebSession $web -Uri $loginUrl -Method Post -Body @{
  user = $User
  pass = $Password
  login = "Login"
} -TimeoutSec 30

$pages = @(
  "admin.php?id=sessions",
  "admin.php?id=storage",
  "admin.php?id=tickets",
  "admin.php?id=backup",
  "admin.php?id=audit",
  "?session=$Session",
  "?hotspot=dashboard&session=$Session",
  "?hotspot=users&profile=all&session=$Session",
  "?hotspot-user=generate&session=$Session",
  "?hotspot-user=generate-roaming&session=$Session",
  "?hotspot=active&session=$Session",
  "?hotspot=user-profiles&session=$Session",
  "?hotspot=hosts&session=$Session",
  "?hotspot=cookies&session=$Session",
  "?hotspot=ipbinding&session=$Session",
  "?ppp=secrets&profile=all&session=$Session",
  "?ppp=active&session=$Session",
  "?ppp=profiles&session=$Session",
  "?system=scheduler&session=$Session",
  "?system=script-generator&session=$Session",
  "?report=userlog&session=$Session",
  "?report=selling&session=$Session",
  "?report=routeros-log&session=$Session"
)

$results = foreach ($page in $pages) {
  Test-TikrasPage -Path $page -WebSession $web
}

$results | Format-Table -AutoSize

if ($results | Where-Object { $_.Status -ne 200 -or $_.RuntimeError }) {
  exit 1
}

$backupUrl = $BaseUrl.TrimEnd("/") + "/process/backup-export.php"
$backupResponse = Invoke-WebRequest -UseBasicParsing -WebSession $web -Uri $backupUrl -TimeoutSec 30
$backupDisposition = [string] $backupResponse.Headers["Content-Disposition"]
if ($backupResponse.StatusCode -ne 200 -or $backupDisposition -notmatch "mikhmon-backup") {
  throw "Export sauvegarde invalide: status=$($backupResponse.StatusCode), disposition=$backupDisposition"
}

Write-Host "Sauvegarde exportee OK: $backupDisposition"
