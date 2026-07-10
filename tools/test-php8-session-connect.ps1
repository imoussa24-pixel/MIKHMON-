param(
  [string] $BaseUrl = "http://127.0.0.1:8081",
  [Parameter(Mandatory = $true)] [string] $Session,
  [string] $User = "",
  [string] $Password = ""
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

Invoke-WebRequest -UseBasicParsing -WebSession $web -Uri $loginUrl -Method Post -Body @{
  user = $User
  pass = $Password
  login = "Login"
} -TimeoutSec 30 | Out-Null

$connectUrl = $BaseUrl.TrimEnd("/") + "/admin.php?id=connect&session=$Session"
$sw = [System.Diagnostics.Stopwatch]::StartNew()
$response = Invoke-WebRequest -UseBasicParsing -WebSession $web -Uri $connectUrl -TimeoutSec 30
$sw.Stop()

$body = [string] $response.Content
$hasRuntimeError = $body -match "Fatal error|Parse error|Warning:|Notice:"

[pscustomobject]@{
  Session = $Session
  Status = $response.StatusCode
  FinalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
  Seconds = [math]::Round($sw.Elapsed.TotalSeconds, 2)
  RuntimeError = $hasRuntimeError
  Bytes = $body.Length
}

if ($response.StatusCode -ne 200 -or $hasRuntimeError) {
  exit 1
}
