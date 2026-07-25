# Installe la cle SSH de deploiement sur le serveur.
# A lancer dans PowerShell (pas Git Bash):
#   powershell -ExecutionPolicy Bypass -File deploy\installer-cle.ps1 169.58.74.46

param(
  [Parameter(Mandatory = $true)][string] $Serveur,
  [string] $Utilisateur = "root",
  [string] $Cle = "$env:USERPROFILE\.ssh\tikras_deploy"
)

$ErrorActionPreference = "Stop"
$ssh = "$env:SystemRoot\System32\OpenSSH\ssh.exe"

if (-not (Test-Path $ssh)) { throw "OpenSSH introuvable: $ssh" }

if (-not (Test-Path "$Cle.pub")) {
  Write-Host "Aucune cle trouvee, generation en cours..." -ForegroundColor Yellow
  $dossier = Split-Path $Cle
  if (-not (Test-Path $dossier)) { New-Item -ItemType Directory -Path $dossier -Force | Out-Null }
  & "$env:SystemRoot\System32\OpenSSH\ssh-keygen.exe" -t ed25519 -f $Cle -N '""' -C "tikras-deploy" -q
}

$clePublique = (Get-Content "$Cle.pub" -Raw).Trim()

Write-Host ""
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " Installation de la cle de deploiement sur $Serveur" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Le mot de passe root du serveur va vous etre demande." -ForegroundColor Yellow
Write-Host ""
Write-Host "  IMPORTANT : pendant la saisie, RIEN ne s'affiche a l'ecran." -ForegroundColor Yellow
Write-Host "  Ni etoiles, ni points. C'est normal, c'est une securite." -ForegroundColor Yellow
Write-Host "  Tapez le mot de passe puis appuyez sur Entree." -ForegroundColor Yellow
Write-Host ""
Write-Host "  Conseil : evitez le collage avec Ctrl+V, faites un clic droit" -ForegroundColor Yellow
Write-Host "  dans la fenetre pour coller, ou tapez le mot de passe a la main." -ForegroundColor Yellow
Write-Host ""

$commande = "mkdir -p ~/.ssh && chmod 700 ~/.ssh && grep -q '$clePublique' ~/.ssh/authorized_keys 2>/dev/null || echo '$clePublique' >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys && echo CLE_INSTALLEE"

& $ssh -o StrictHostKeyChecking=accept-new "$Utilisateur@$Serveur" $commande

Write-Host ""
Write-Host "Verification de l'acces par cle..." -ForegroundColor Cyan
$test = & $ssh -i $Cle -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 "$Utilisateur@$Serveur" "echo ACCES_OK; hostname; . /etc/os-release 2>/dev/null && echo `$PRETTY_NAME" 2>&1

if ($test -match "ACCES_OK") {
  Write-Host ""
  Write-Host "==================================================================" -ForegroundColor Green
  Write-Host " Cle installee, acces confirme." -ForegroundColor Green
  Write-Host "==================================================================" -ForegroundColor Green
  $test | Where-Object { $_ -notmatch "ACCES_OK" } | ForEach-Object { Write-Host "  $_" }
  Write-Host ""
  Write-Host "Dites-le a Claude, il prend la suite du deploiement." -ForegroundColor Green
} else {
  Write-Host ""
  Write-Host "L'acces par cle ne fonctionne pas encore." -ForegroundColor Red
  Write-Host "Detail: $test" -ForegroundColor DarkGray
  Write-Host ""
  Write-Host "Autre methode: ajoutez cette cle publique depuis l'interface web" -ForegroundColor Yellow
  Write-Host "de votre hebergeur (section SSH Keys), puis reessayez:" -ForegroundColor Yellow
  Write-Host ""
  Write-Host $clePublique -ForegroundColor White
}
