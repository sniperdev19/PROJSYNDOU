# Script PowerShell pour configurer automatiquement php.ini pour l'upload de vidéos
# Exécutez ce script en tant qu'administrateur

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuration PHP pour Upload de Vidéos" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Trouver le fichier php.ini
$possiblePaths = @(
    "C:\wamp64\bin\php\php*\php.ini",
    "C:\wamp\bin\php\php*\php.ini",
    "C:\xampp\php\php.ini"
)

$phpIniPath = $null
foreach ($path in $possiblePaths) {
    $found = Get-ChildItem -Path $path -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) {
        $phpIniPath = $found.FullName
        break
    }
}

if (-not $phpIniPath) {
    Write-Host "❌ Fichier php.ini non trouvé automatiquement" -ForegroundColor Red
    Write-Host "Entrez le chemin complet vers php.ini :" -ForegroundColor Yellow
    $phpIniPath = Read-Host
}

if (-not (Test-Path $phpIniPath)) {
    Write-Host "❌ Fichier introuvable : $phpIniPath" -ForegroundColor Red
    exit
}

Write-Host "✅ php.ini trouvé : $phpIniPath" -ForegroundColor Green
Write-Host ""

# Créer une sauvegarde
$backupPath = $phpIniPath + ".backup." + (Get-Date -Format "yyyyMMdd_HHmmss")
Copy-Item $phpIniPath $backupPath
Write-Host "💾 Sauvegarde créée : $backupPath" -ForegroundColor Green
Write-Host ""

# Lire le contenu
$content = Get-Content $phpIniPath -Raw

# Paramètres à modifier
$modifications = @{
    "upload_max_filesize" = "25M"
    "post_max_size" = "30M"
    "max_execution_time" = "120"
    "max_input_time" = "120"
    "memory_limit" = "256M"
}

Write-Host "🔧 Modifications à apporter :" -ForegroundColor Cyan
Write-Host ""

$modified = $false
foreach ($param in $modifications.Keys) {
    $newValue = $modifications[$param]
    
    # Pattern pour trouver le paramètre
    $pattern = "(?m)^;?\s*$param\s*=\s*.*$"
    
    if ($content -match $pattern) {
        $oldLine = $matches[0]
        $newLine = "$param = $newValue"
        
        # Afficher le changement
        Write-Host "  $param" -ForegroundColor Yellow
        Write-Host "    Avant : $oldLine" -ForegroundColor Gray
        Write-Host "    Après : $newLine" -ForegroundColor Green
        Write-Host ""
        
        # Remplacer
        $content = $content -replace $pattern, $newLine
        $modified = $true
    } else {
        Write-Host "  ⚠️  $param non trouvé, ajout à la fin" -ForegroundColor Yellow
        $content += "`n$param = $newValue"
        $modified = $true
    }
}

if ($modified) {
    # Sauvegarder les modifications
    $content | Set-Content $phpIniPath -Encoding UTF8
    Write-Host "✅ Fichier php.ini modifié avec succès !" -ForegroundColor Green
    Write-Host ""
    
    Write-Host "⚠️  IMPORTANT : Redémarrez WAMP pour appliquer les changements !" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Étapes :" -ForegroundColor Cyan
    Write-Host "  1. Cliquez sur l'icône WAMP (barre des tâches)" -ForegroundColor White
    Write-Host "  2. Sélectionnez 'Redémarrer tous les services'" -ForegroundColor White
    Write-Host "  3. Attendez que l'icône redevienne verte" -ForegroundColor White
    Write-Host "  4. Testez l'upload de vidéo" -ForegroundColor White
    Write-Host ""
    
    # Proposer de redémarrer les services WAMP
    Write-Host "Voulez-vous que je tente de redémarrer WAMP automatiquement ? (O/N)" -ForegroundColor Yellow
    $restart = Read-Host
    
    if ($restart -eq "O" -or $restart -eq "o") {
        Write-Host "🔄 Tentative de redémarrage de WAMP..." -ForegroundColor Cyan
        
        # Arrêter les services
        Stop-Service wampapache64 -ErrorAction SilentlyContinue
        Stop-Service wampmysqld64 -ErrorAction SilentlyContinue
        
        Start-Sleep -Seconds 2
        
        # Redémarrer les services
        Start-Service wampapache64 -ErrorAction SilentlyContinue
        Start-Service wampmysqld64 -ErrorAction SilentlyContinue
        
        Write-Host "✅ Services redémarrés" -ForegroundColor Green
    }
    
} else {
    Write-Host "ℹ️  Aucune modification nécessaire" -ForegroundColor Blue
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuration terminée !" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Testez maintenant : http://localhost/PROJET%20ECOLE/PROJSYNDOU2/diagnostic-video-upload.php" -ForegroundColor Cyan
Write-Host ""
Write-Host "Appuyez sur une touche pour fermer..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
