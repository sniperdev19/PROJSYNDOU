# 📊 Limitation des Messages pour les Invités

**Date de mise en œuvre** : 5 décembre 2025

## 🎯 Objectif

Encourager les utilisateurs invités à créer un compte en limitant le nombre de messages qu'ils peuvent envoyer, tout en offrant une expérience gratuite pour tester le chat.

## ⚙️ Configuration

Les limites sont définies dans `config.php` :

```php
'limits' => [
    'guest_message_limit' => 10,    // Limite totale de messages pour les invités
    'warning_at_message' => 7       // Avertissement à partir de ce nombre
]
```

### Paramètres ajustables :

- **`guest_message_limit`** : Nombre maximum de messages qu'un invité peut envoyer (par défaut : 10)
- **`warning_at_message`** : Nombre de messages à partir duquel l'avertissement apparaît (par défaut : 7)

## 🔄 Fonctionnement

### Phase 1 : Messages libres (1-6 messages)
- L'invité peut envoyer des messages normalement
- Aucun avertissement affiché
- Expérience utilisateur fluide

### Phase 2 : Avertissement (7-9 messages)
- Un bandeau d'avertissement apparaît en haut du chat
- Indique le nombre de messages restants
- Propose un lien direct pour créer un compte
- L'avertissement peut être fermé temporairement

### Phase 3 : Limite atteinte (10+ messages)
- L'interface de saisie est désactivée
- Une modale s'affiche avec :
  - Message explicatif
  - Avantages de la création de compte
  - Bouton "Créer un compte"
  - Bouton "Se déconnecter"
- L'utilisateur ne peut plus envoyer de messages

## 📱 Interface Utilisateur

### Avertissement (Phase 2)

```
⚠️ Attention ! Il vous reste X messages en tant qu'invité.
   Créer un compte pour continuer à discuter.
   [×]
```

**Caractéristiques** :
- Couleur jaune/orange pour attirer l'attention
- Peut être fermé mais réapparaît à chaque rechargement
- Lien cliquable vers l'inscription
- Design non intrusif

### Modale de limite (Phase 3)

```
╔═════════════════════════════════════╗
║  🚫 Limite de messages atteinte     ║
╠═════════════════════════════════════╣
║                                     ║
║  Vous avez atteint la limite de    ║
║  10 messages en tant qu'invité.     ║
║                                     ║
║  Créez un compte gratuit pour      ║
║  continuer à discuter sans limites!║
║                                     ║
║  ✅ Messages illimités              ║
║  ✅ Historique sauvegardé           ║
║  ✅ Connexion permanente            ║
║                                     ║
║  [Créer un compte] [Se déconnecter]║
╚═════════════════════════════════════╝
```

**Caractéristiques** :
- Modale plein écran avec overlay
- Design moderne et attractif
- Liste des avantages
- Actions claires (créer compte ou se déconnecter)
- Non fermable (force le choix)

## 🔧 Implémentation Technique

### Backend (API - `api.php`)

#### 1. Vérification lors de l'envoi de message

```php
// Détection si l'utilisateur est un invité
$isGuest = !isset($_SESSION['user_id']);

// Comptage des messages de l'invité
if ($isGuest) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages 
                          WHERE username = ? 
                          AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$username]);
    $messageCount = $stmt->fetchColumn();
    
    // Blocage si limite atteinte
    if ($messageCount >= $guestLimit) {
        sendResponse(false, ['limit_reached' => true], 
                    "Limite atteinte. Créez un compte !");
        return;
    }
}
```

#### 2. Réponse avec compteurs

Après l'envoi d'un message réussi pour un invité :

```json
{
  "success": true,
  "data": {
    "id": 123,
    "is_guest": true,
    "message_count": 7,
    "remaining": 3,
    "limit": 10,
    "show_warning": true
  }
}
```

#### 3. Vérification de session

L'endpoint `check_session` renvoie également les compteurs pour les invités :

```json
{
  "success": true,
  "data": {
    "username": "Invité123",
    "is_guest": true,
    "message_count": 8,
    "remaining": 2,
    "limit": 10,
    "show_warning": true
  }
}
```

### Frontend (JavaScript - `script.js`)

#### Variables de suivi

```javascript
constructor() {
    // ...
    this.isGuest = false;
    this.messageCount = 0;
    this.messageLimit = 10;
    this.messagesRemaining = 10;
}
```

#### Méthodes principales

1. **`showGuestWarning()`** : Affiche le bandeau d'avertissement
2. **`handleGuestLimitReached()`** : Affiche la modale et bloque l'interface
3. Mise à jour automatique des compteurs après chaque message

### Frontend (CSS - `style.css`)

- **`.guest-warning`** : Bandeau d'avertissement jaune
- **`.guest-limit-modal`** : Modale plein écran
- **`.modal-overlay`** : Fond semi-transparent avec flou
- **`.modal-content`** : Contenu de la modale
- Animations : `slideDown`, `slideUp`, `fadeIn`

## 📊 Comptage des Messages

### Période de comptage
- **24 heures glissantes** : Les messages de plus de 24h ne sont pas comptés
- Permet à un invité de revenir le lendemain avec un compteur réinitialisé

### Identification
- Par `username` (invité)
- Les utilisateurs avec compte (`user_id` en session) ne sont pas limités

## ✨ Avantages pour l'Utilisateur

### Mode Invité (Gratuit)
- ✅ Jusqu'à 10 messages
- ✅ Pas d'inscription requise
- ✅ Test du chat
- ❌ Limite de messages
- ❌ Pas d'historique permanent

### Compte Utilisateur (Gratuit)
- ✅ Messages illimités
- ✅ Historique sauvegardé
- ✅ Connexion permanente
- ✅ Pas de limite de temps
- ✅ Profil personnalisé

## 🎨 Expérience Utilisateur

### Parcours Invité → Compte

1. **Connexion invité** : L'utilisateur rejoint rapidement
2. **Discussion libre** : 6 premiers messages sans interruption
3. **Premier avertissement** : Message 7, avertissement discret
4. **Rappels** : Messages 8-9, avertissement persistent
5. **Blocage doux** : Message 10, modale encourageante
6. **Conversion** : Création de compte en 1 clic
7. **Continuation** : L'utilisateur peut continuer immédiatement

### Points clés UX
- ✅ Non intrusif au début
- ✅ Avertissement progressif
- ✅ Création de compte facilitée
- ✅ Avantages clairement présentés
- ✅ Pas de perte de la conversation en cours

## 🔒 Sécurité et Contraintes

### Limitations
- Comptage par `username` et période de 24h
- Un invité peut changer de nom pour contourner (comportement acceptable)
- Les comptes authentifiés ne sont jamais limités

### Considérations
- Le système encourage la création de compte sans forcer
- Les invités peuvent toujours lire les messages
- Pas de limitation sur la réception de messages

## 📈 Métriques Suggérées

Pour suivre l'efficacité du système :

1. **Taux de conversion** : Invités → Comptes créés
2. **Messages moyens avant conversion**
3. **Taux d'abandon** : Invités qui quittent à la limite
4. **Temps moyen avant limite**

## 🛠️ Maintenance

### Ajuster les limites

Dans `config.php`, modifier :

```php
'guest_message_limit' => 15,  // Plus généreux
'warning_at_message' => 10    // Avertir plus tard
```

### Désactiver temporairement

Mettre une limite très élevée :

```php
'guest_message_limit' => 9999,
```

### Test

Pour tester rapidement :

```php
'guest_message_limit' => 3,   // Limite basse
'warning_at_message' => 2     // Avertir tôt
```

## 🐛 Résolution de Problèmes

### Problème : Les invités ne sont pas limités
- Vérifier que `$_SESSION['user_id']` n'est pas défini pour les invités
- Vérifier que la configuration est bien chargée

### Problème : Compteur incorrect
- Vérifier la requête SQL (période 24h)
- Logger le comptage : `error_log("Messages: $messageCount/$guestLimit")`

### Problème : Avertissement ne s'affiche pas
- Vérifier la console JavaScript
- Vérifier que `show_warning` est true dans la réponse API

## 📝 Logs et Debugging

Le système log automatiquement :

```
🚫 Limite atteinte pour invité 'Invité123': 10/10 messages
```

Activer plus de logs dans `api.php` :

```php
error_log("Invité - Messages: $messageCount/$guestLimit");
```

## 🚀 Évolutions Futures

### Possibilités d'amélioration :

1. **Limite progressive** : 10 messages le premier jour, 5 les jours suivants
2. **Bonus temporaire** : +5 messages pour partage sur réseaux sociaux
3. **Système de parrainage** : +10 messages par ami invité
4. **Achat de messages** : Option premium pour invités récurrents
5. **Captcha** : Vérifier que ce n'est pas un bot avant la limite

## 📞 Support

Pour toute question sur cette fonctionnalité :
- Consulter les logs PHP et JavaScript
- Vérifier `config.php` pour les limites actuelles
- Tester avec différents comptes (invité vs authentifié)

---

**Version** : 1.0  
**Auteur** : Projet PROJSYNDOU2  
**Date** : 5 décembre 2025
