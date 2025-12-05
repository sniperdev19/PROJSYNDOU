# Fonctionnalité d'Envoi de Photos et Vidéos

## 📸 Description

Cette nouvelle fonctionnalité permet aux utilisateurs du chat d'envoyer des photos et des vidéos en plus des messages texte et vocaux.

## ✨ Fonctionnalités

- **Envoi d'images** (formats: JPG, PNG, GIF, etc.) - Maximum 5MB
- **Envoi de vidéos** (formats: MP4, WebM, etc.) - Maximum 20MB
- **Prévisualisation** avant l'envoi
- **Légende optionnelle** pour accompagner les médias
- **Affichage optimisé** dans l'interface de chat
- **Visionneuse plein écran** pour les images (clic pour agrandir)
- **Lecteur vidéo intégré** avec contrôles

## 🔧 Installation

### 1. Mettre à jour la base de données

Exécutez le script SQL suivant dans phpMyAdmin ou via la ligne de commande :

```sql
USE chat_db;

-- Ajouter la colonne media_type
ALTER TABLE messages 
ADD COLUMN media_type VARCHAR(10) NULL AFTER audio_duration,
ADD INDEX idx_media_type (media_type);

-- Ajouter la colonne media_path
ALTER TABLE messages 
ADD COLUMN media_path VARCHAR(255) NULL AFTER media_type,
ADD INDEX idx_media_path (media_path);
```

**Alternativement**, vous pouvez exécuter le fichier `add_media_columns.sql` :

```bash
mysql -u root -p chat_db < add_media_columns.sql
```

### 2. Vérifier les permissions des dossiers

Les dossiers suivants ont été créés automatiquement :
- `uploads/images/` - Pour les photos
- `uploads/videos/` - Pour les vidéos

Assurez-vous que le serveur web a les droits d'écriture :

```bash
chmod 755 uploads/images uploads/videos
```

### 3. Configuration PHP (optionnel)

Si vous souhaitez modifier les limites d'upload, éditez `php.ini` :

```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 60
```

Puis redémarrez Apache/WAMP.

## 🎯 Utilisation

### Pour les utilisateurs

1. **Cliquer sur l'icône de photo** 📷 à côté du champ de message
2. **Sélectionner une image ou vidéo** depuis votre appareil
3. **Prévisualiser** le média sélectionné
4. **Ajouter une légende** (optionnel) dans le champ de message
5. **Cliquer sur "Envoyer"** pour partager

### Limitations

- **Invités** : Soumis à la même limite de 10 messages/24h (incluant médias)
- **Utilisateurs enregistrés** : Aucune limite
- **Taille maximale** :
  - Images : 5 MB
  - Vidéos : 20 MB

## 📁 Structure des fichiers

```
PROJSYNDOU2/
├── index.html              # Interface avec bouton d'envoi de médias
├── script.js              # Gestion de l'affichage des médias
├── media-manager.js       # Nouveau gestionnaire d'upload de médias
├── style.css              # Styles pour l'interface médias
├── api.php                # API avec endpoints send_media et get_media
├── add_media_columns.sql  # Script de mise à jour de la base de données
└── uploads/
    ├── audio/             # Messages vocaux existants
    ├── images/            # Nouvelles photos (créé automatiquement)
    └── videos/            # Nouvelles vidéos (créé automatiquement)
```

## 🔒 Sécurité

- **Validation du type MIME** côté serveur
- **Vérification de la taille** des fichiers
- **Noms de fichiers uniques** (timestamp + uniqid)
- **Validation des noms** pour éviter les injections
- **Limitation d'accès** aux fichiers uploadés

## 🐛 Dépannage

### Les médias ne s'uploadent pas

1. Vérifiez les permissions des dossiers `uploads/`
2. Vérifiez les limites PHP dans `php.ini`
3. Consultez les logs d'erreur Apache/PHP
4. Vérifiez que les colonnes ont bien été ajoutées à la base de données

### Les images ne s'affichent pas

1. Vérifiez que l'API endpoint `get_media` fonctionne
2. Ouvrez la console du navigateur pour voir les erreurs
3. Vérifiez que les fichiers existent dans `uploads/images/` ou `uploads/videos/`

### Erreur "File too large"

- Augmentez `upload_max_filesize` et `post_max_size` dans `php.ini`
- Redémarrez le serveur web

## 📝 Notes de développement

- Les médias sont stockés dans le système de fichiers (pas dans la BD)
- Seuls les chemins/noms de fichiers sont stockés en base
- La colonne `media_type` contient : `'image'`, `'video'`, ou `NULL`
- Les anciens messages (texte/audio) restent compatibles

## 🎨 Personnalisation

### Modifier les limites de taille

Dans `media-manager.js` :

```javascript
this.maxImageSize = 5 * 1024 * 1024; // 5MB
this.maxVideoSize = 20 * 1024 * 1024; // 20MB
```

Dans `api.php` :

```php
$maxImageSize = 5 * 1024 * 1024; // 5MB
$maxVideoSize = 20 * 1024 * 1024; // 20MB
```

### Ajouter d'autres formats

Dans `media-manager.js`, modifiez l'attribut `accept` :

```html
<input type="file" id="media-input" accept="image/*,video/*,.pdf">
```

## 🚀 Améliorations futures possibles

- Compression automatique des images
- Génération de miniatures
- Support des GIFs animés
- Galerie de médias
- Téléchargement de fichiers
- Partage de documents PDF

## ✅ Tests recommandés

- [ ] Upload d'image JPG/PNG
- [ ] Upload de vidéo MP4
- [ ] Affichage correct dans le chat
- [ ] Ouverture de la modale pour images
- [ ] Lecture de vidéo
- [ ] Envoi avec légende
- [ ] Vérification des limites de taille
- [ ] Test avec compte invité (limite)
- [ ] Test avec compte enregistré (illimité)

---

**Version** : 1.0  
**Date** : 5 décembre 2025  
**Auteur** : Votre équipe de développement
