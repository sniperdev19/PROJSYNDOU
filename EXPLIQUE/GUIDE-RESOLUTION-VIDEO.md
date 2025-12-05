# 🎥 Résolution du Problème d'Upload de Vidéos

## ❌ Symptôme
"Erreur lors de l'upload du fichier" lors de l'envoi de vidéos dans le chat.

## 🔍 Cause Principale
Les limites PHP par défaut sont trop basses pour les vidéos (souvent 2MB alors que nous avons besoin de 20MB).

## ✅ Solutions (dans l'ordre de priorité)

### Solution 1 : Page de Diagnostic Automatique (RECOMMANDÉ)

1. **Ouvrez dans votre navigateur :**
   ```
   http://localhost/PROJET ECOLE/PROJSYNDOU2/diagnostic-video-upload.php
   ```

2. **La page va :**
   - ✅ Afficher tous les paramètres PHP actuels
   - ✅ Indiquer lesquels doivent être modifiés
   - ✅ Fournir les instructions exactes
   - ✅ Permettre de tester l'upload directement

3. **Testez une vidéo directement sur cette page**

---

### Solution 2 : Modification Manuelle de php.ini (WAMP)

#### Étape 1 : Ouvrir php.ini
1. Cliquez sur l'**icône WAMP** (barre des tâches)
2. **PHP** → **php.ini**
3. Le fichier s'ouvre dans le Bloc-notes

#### Étape 2 : Modifier les Paramètres
Cherchez et modifiez ces lignes (Ctrl+F pour rechercher) :

```ini
; Avant (valeurs par défaut)
upload_max_filesize = 2M
post_max_size = 8M
max_execution_time = 30

; Après (nouvelles valeurs)
upload_max_filesize = 25M
post_max_size = 30M
max_execution_time = 120
max_input_time = 120
memory_limit = 256M
```

**Note :** `post_max_size` doit être **plus grand** que `upload_max_filesize`

#### Étape 3 : Redémarrer WAMP
1. Cliquez sur l'**icône WAMP**
2. **Redémarrer tous les services**
3. Attendez que l'icône redevienne **verte**

#### Étape 4 : Vérifier
- Rechargez la page de diagnostic
- Les valeurs doivent être à jour
- Testez l'upload d'une vidéo

---

### Solution 3 : Via .htaccess (Alternative)

Le fichier `.htaccess` a été mis à jour automatiquement avec :

```apache
php_value upload_max_filesize 25M
php_value post_max_size 30M
php_value max_execution_time 120
php_value max_input_time 120
php_value memory_limit 256M
php_flag file_uploads On
```

**Note :** Cela fonctionne si `AllowOverride All` est activé dans Apache.

---

## 🧪 Test Rapide

### Dans le Chat
1. Cliquez sur le bouton 📷
2. Sélectionnez une **vidéo de moins de 20MB**
3. Ajoutez une légende (optionnel)
4. Cliquez sur **Envoyer**
5. La vidéo devrait s'afficher avec un lecteur

### Dans la Page de Diagnostic
1. Ouvrez `diagnostic-video-upload.php`
2. Section "Test d'Upload"
3. Sélectionnez une vidéo
4. Cliquez sur "Tester l'Upload"
5. Vérifiez le résultat

---

## 📊 Codes d'Erreur et Solutions

| Code | Erreur | Solution |
|------|--------|----------|
| **1** | `UPLOAD_ERR_INI_SIZE` | Augmenter `upload_max_filesize` dans php.ini |
| **2** | `UPLOAD_ERR_FORM_SIZE` | Vérifier MAX_FILE_SIZE dans le formulaire |
| **3** | `UPLOAD_ERR_PARTIAL` | Réessayer, vérifier la connexion Internet |
| **4** | `UPLOAD_ERR_NO_FILE` | Sélectionner un fichier avant d'envoyer |
| **6** | `UPLOAD_ERR_NO_TMP_DIR` | Vérifier le dossier temporaire PHP |
| **7** | `UPLOAD_ERR_CANT_WRITE` | Vérifier les permissions du dossier uploads |

---

## 🔧 Vérifications Supplémentaires

### 1. Dossiers d'Upload
Vérifiez que ces dossiers existent et sont accessibles en écriture :
- `uploads/`
- `uploads/images/`
- `uploads/videos/`
- `uploads/audio/`

### 2. Permissions (Windows)
- Les dossiers doivent permettre la lecture/écriture
- Clic droit → Propriétés → Sécurité
- L'utilisateur doit avoir "Contrôle total"

### 3. Taille de la Vidéo
- Maximum : **20 MB**
- Formats supportés : MP4, WebM, MOV, AVI
- Si votre vidéo est plus grosse, compressez-la d'abord

---

## 📱 Recommandations

### Pour les Vidéos Volumineuses
Si vous devez envoyer des vidéos de plus de 20MB :

1. **Compressez la vidéo** avec un outil comme :
   - HandBrake (gratuit)
   - VLC (gratuit)
   - Convertisseurs en ligne

2. **Ou augmentez encore les limites** :
   ```ini
   upload_max_filesize = 50M
   post_max_size = 55M
   ```

### Formats Recommandés
- **MP4** (H.264) : meilleure compatibilité
- **WebM** : bonne compression
- Résolution : 720p ou moins pour réduire la taille

---

## ✅ Checklist de Résolution

- [ ] Ouvrir `diagnostic-video-upload.php`
- [ ] Vérifier les paramètres PHP actuels
- [ ] Si nécessaire, modifier php.ini
- [ ] Redémarrer WAMP
- [ ] Vérifier que les modifications sont appliquées
- [ ] Tester l'upload sur la page de diagnostic
- [ ] Tester l'upload dans le chat
- [ ] Vérifier l'affichage de la vidéo

---

## 🆘 Support

Si le problème persiste après avoir suivi ces étapes :

1. **Consultez les logs d'erreur PHP**
   - WAMP → Logs → PHP error log

2. **Ouvrez la console du navigateur** (F12)
   - Onglet Console
   - Onglet Réseau (Network)

3. **Vérifiez le message d'erreur exact**
   - Il indiquera le code d'erreur PHP
   - Cherchez ce code dans le tableau ci-dessus

---

**Dernière mise à jour** : 5 décembre 2025
