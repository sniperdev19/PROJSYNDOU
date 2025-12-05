# 🎥 CORRECTION - Upload de Vidéos

## 🔴 Problème
**"Erreur lors de l'upload du fichier"** lors de l'envoi de vidéos

## ✅ Solution Rapide (3 méthodes)

---

### 🚀 Méthode 1 : Page de Diagnostic (LE PLUS SIMPLE)

1. **Ouvrez dans votre navigateur :**
   ```
   http://localhost/PROJET ECOLE/PROJSYNDOU2/diagnostic-video-upload.php
   ```

2. **Cette page va :**
   - Analyser votre configuration PHP
   - Identifier les problèmes
   - Vous guider étape par étape
   - Permettre de tester l'upload

3. **Suivez les instructions affichées**

---

### ⚡ Méthode 2 : Script PowerShell Automatique

1. **Ouvrez PowerShell en tant qu'Administrateur**
   - Cliquez droit sur le menu Démarrer
   - "Windows PowerShell (Admin)"

2. **Naviguez vers le dossier du projet :**
   ```powershell
   cd "C:\wamp64\www\PROJET ECOLE\PROJSYNDOU2"
   ```

3. **Exécutez le script :**
   ```powershell
   .\configure-php-for-videos.ps1
   ```

4. **Le script va :**
   - Trouver automatiquement php.ini
   - Créer une sauvegarde
   - Modifier les paramètres nécessaires
   - Vous proposer de redémarrer WAMP

---

### 🔧 Méthode 3 : Manuel (WAMP)

#### Étape 1 : Ouvrir php.ini
1. Icône WAMP → **PHP** → **php.ini**

#### Étape 2 : Modifier (Ctrl+F pour chercher)
```ini
upload_max_filesize = 25M
post_max_size = 30M
max_execution_time = 120
max_input_time = 120
memory_limit = 256M
```

#### Étape 3 : Redémarrer WAMP
1. Icône WAMP → **Redémarrer tous les services**
2. Attendre que l'icône soit verte

---

## 📁 Fichiers Créés

| Fichier | Description |
|---------|-------------|
| `diagnostic-video-upload.php` | Page de diagnostic interactive |
| `configure-php-for-videos.ps1` | Script PowerShell automatique |
| `GUIDE-RESOLUTION-VIDEO.md` | Guide détaillé complet |
| `.htaccess` | Mis à jour avec limites PHP |
| `api.php` | Messages d'erreur améliorés |

---

## 🧪 Test

Après avoir appliqué UNE des méthodes :

1. **Rechargez** votre page de chat
2. **Cliquez** sur le bouton 📷
3. **Sélectionnez** une vidéo (MP4, WebM, max 20MB)
4. **Envoyez** la vidéo
5. **Elle devrait s'afficher** avec un lecteur vidéo

---

## 📊 Limites Actuelles

| Type | Taille Max | Format |
|------|-----------|--------|
| **Images** | 5 MB | JPG, PNG, GIF, WebP |
| **Vidéos** | 20 MB | MP4, WebM, MOV, AVI |
| **Audio** | 10 MB | WebM, MP3, OGG |

---

## ❓ Problèmes Fréquents

### La vidéo est trop grosse
- **Compressez-la** avec HandBrake ou VLC
- Ou augmentez les limites à 50MB dans php.ini

### Les changements ne s'appliquent pas
- Vérifiez que vous avez **redémarré WAMP**
- Ouvrez la page de diagnostic pour vérifier

### Erreur de permissions
- Vérifiez les permissions des dossiers `uploads/`
- Windows : Propriétés → Sécurité → Contrôle total

---

## 🎯 Ordre Recommandé

1. ✅ **Ouvrir** `diagnostic-video-upload.php`
2. ✅ **Identifier** les problèmes
3. ✅ **Choisir** une méthode de correction
4. ✅ **Appliquer** la correction
5. ✅ **Redémarrer** WAMP
6. ✅ **Vérifier** sur la page de diagnostic
7. ✅ **Tester** dans le chat

---

## 📞 Support

Si rien ne fonctionne :

1. Vérifiez les **logs PHP** : WAMP → Logs → PHP error
2. Console du navigateur **(F12)** → onglet Console
3. Consultez `GUIDE-RESOLUTION-VIDEO.md` pour plus de détails

---

**Correction appliquée le** : 5 décembre 2025  
**Versions testées** : WAMP 3.x, PHP 7.x/8.x
