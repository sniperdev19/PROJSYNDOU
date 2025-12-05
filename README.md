# Chat Application - Version Multi-Utilisateurs avec Gestion de Comptes

Une application de discussion en temps réel développée avec HTML, CSS, JavaScript et PHP, **maintenant avec système d'authentification complet et optimisée pour les connexions multiples simultanées**.

## 🔐 NOUVEAU : Système de Création de Compte

Le chat dispose maintenant d'un **système complet de gestion des utilisateurs** avec trois modes de connexion :

### 🎯 Trois modes d'accès :
1. **Mode Invité** : Connexion rapide sans inscription (⚠️ limité à 10 messages)
2. **Inscription** : Créez un compte avec email et mot de passe sécurisé
3. **Connexion** : Connectez-vous avec votre compte existant

### 🚀 Installation rapide du module :
1. Démarrez WAMP/MySQL
2. Accédez à : `http://localhost/PROJET ECOLE/PROJSYNDOU2/setup-users.php`
3. Cliquez sur le bouton pour créer la table
4. C'est prêt ! 🎉

📖 **Documentation complète** : Consultez [AUTH-README.md](AUTH-README.md) pour tous les détails

## 📊 NOUVEAU : Limitation des Messages Invités

Pour encourager la création de comptes, les **invités sont limités à 10 messages** :

### 🎯 Fonctionnement :
- **Messages 1-6** : Utilisation libre sans avertissement
- **Messages 7-9** : Avertissement avec compteur de messages restants
- **Message 10+** : Blocage avec invitation à créer un compte

### ✨ Avantages de créer un compte :
- ✅ **Messages illimités** - Discutez sans restriction
- ✅ **Historique sauvegardé** - Retrouvez vos conversations
- ✅ **Connexion permanente** - Restez connecté entre les sessions

📖 **Documentation détaillée** : [GUEST-LIMITS.md](GUEST-LIMITS.md)

## 🆕 Nouvelles Fonctionnalités - Connexions Multiples

### ✅ Améliorations majeures apportées :

🔥 **Connexions simultanées autorisées** : Plusieurs utilisateurs peuvent maintenant se connecter en même temps, même avec des noms similaires.

🤖 **Génération automatique de noms uniques** : Si un nom d'utilisateur est déjà pris, le système génère automatiquement une variante unique (ex: "Jean" → "Jean_1", "Jean_2", etc.).

🔄 **Sessions indépendantes** : Chaque connexion a sa propre session, permettant à un même utilisateur d'ouvrir plusieurs fenêtres/onglets.

📊 **Affichage optimisé** : La liste des utilisateurs actifs groupe les connexions similaires et affiche le nombre total de connexions.

🧹 **Gestion intelligente des déconnexions** : Le système nettoie automatiquement les sessions inactives et gère proprement les déconnexions multiples.

## Fonctionnalités principales

✅ **Interface utilisateur moderne et responsive**
- Design moderne avec dégradés et animations
- Interface adaptée aux mobiles et tablettes
- Notifications visuelles pour les nouveaux messages
- **Affichage détaillé des connexions multiples**

✅ **Système de messagerie avancé**
- Envoi et réception de messages en temps réel
- **Messages vocaux** avec enregistrement et lecture
- Affichage des timestamps relatifs (il y a X minutes)
- Messages limités à 500 caractères
- Échappement HTML pour la sécurité

✅ **Gestion des utilisateurs multi-sessions**
- **Support de connexions simultanées multiples**
- **Génération automatique de noms uniques**
- Sessions PHP indépendantes avec ID client unique
- Validation des noms d'utilisateur (1-20 caractères)
- Déconnexion propre par session

✅ **Base de données MySQL optimisée**
- Stockage persistant des messages et sessions
- **Gestion des sessions multiples avec clé composite**
- Création automatique des tables
- **Nettoyage automatique des sessions inactives**
- Gestion robuste des erreurs de base de données

✅ **Fonctionnalités avancées**
- Polling automatique toutes les 2 secondes
- Scroll automatique vers les nouveaux messages
- Gestion de la visibilité de la page (pause/reprise du polling)
- Raccourcis clavier (Ctrl+Entrée pour envoyer, / pour focus)
- **Page de test multi-utilisateurs intégrée**

## Installation

### Prérequis
- Serveur web avec support PHP (Apache/Nginx)
- PHP 7.4 ou supérieur
- Extension SQLite activée

### Configuration WAMP/XAMPP
1. Copiez tous les fichiers dans le dossier `www` de votre serveur local
2. Accédez à l'application via `http://localhost/PROJSYNDOU/`

### Fichiers inclus
- `index.html` - Interface utilisateur principale
- `style.css` - Styles et animations
- `script.js` - Logique côté client et AJAX
- `api.php` - API backend PHP
- `chat.db` - Base de données SQLite (créée automatiquement)

## Utilisation

1. **Connexion**
   - Entrez votre nom d'utilisateur (1-20 caractères)
   - Cliquez sur "Rejoindre"

2. **Envoyer des messages**
   - Tapez votre message dans le champ de saisie
   - Appuyez sur Entrée ou cliquez sur "Envoyer"
   - Ou utilisez Ctrl+Entrée comme raccourci

3. **Navigation**
   - Les messages s'affichent automatiquement
   - Scroll automatique vers les nouveaux messages
   - Appuyez sur "/" pour focus sur l'input

4. **Déconnexion**
   - Cliquez sur "Déconnexion" dans l'en-tête

## Architecture

### Frontend (HTML/CSS/JS)
- **HTML** : Structure sémantique avec formulaires et zones de message
- **CSS** : Design moderne avec animations et responsivité
- **JavaScript** : Gestion des événements, polling AJAX, manipulation DOM

### Backend (PHP)
- **Sessions** : Gestion de l'authentification utilisateur
- **SQLite** : Base de données légère pour le stockage
- **API REST** : Endpoints pour toutes les actions (login, messages, etc.)

### Base de données
```sql
-- Table des messages
CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    message TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des utilisateurs actifs
CREATE TABLE active_users (
    username TEXT PRIMARY KEY,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## API Endpoints

- `GET api.php?action=check_session` - Vérifier la session
- `POST api.php?action=login` - Se connecter
- `POST api.php?action=logout` - Se déconnecter
- `POST api.php?action=send_message` - Envoyer un message
- `GET api.php?action=get_messages&last_id=X` - Récupérer les messages
- `GET api.php?action=get_active_users` - Liste des utilisateurs connectés
- `POST api.php?action=clear_messages` - Nettoyer les anciens messages

## Sécurité

✅ **Validation des données**
- Échappement HTML des messages et noms d'utilisateur
- Validation côté serveur des longueurs
- Requêtes préparées pour éviter l'injection SQL

✅ **Session management**
- Sessions PHP sécurisées
- Vérification d'authentification pour les actions sensibles

✅ **Sanitisation**
- Tous les inputs utilisateur sont nettoyés
- Protection contre XSS

## Personnalisation

### Modifier les couleurs
Éditez les variables CSS dans `style.css` :
```css
/* Gradient principal */
background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);

/* Couleurs des messages */
.message.own { background: linear-gradient(...); }
.message.other { background: white; }
```

### Modifier la fréquence de polling
Dans `script.js`, changez la valeur (en millisecondes) :
```javascript
this.pollInterval = setInterval(() => {
    this.loadMessages();
}, 2000); // 2 secondes par défaut
```

### Limites de caractères
Modifiez les constantes dans `api.php` :
```php
function isValidUsername($username) {
    return !empty($username) && strlen($username) <= 20; // Changer ici
}

function isValidMessage($message) {
    return !empty($message) && strlen($message) <= 500; // Et ici
}
```

## Améliorations possibles

🔄 **Fonctionnalités futures**
- WebSockets pour un temps réel véritable
- Système de salles/canaux multiples
- Upload d'images/fichiers
- Système de mentions (@utilisateur)
- Messages privés
- Historique de messages plus avancé
- Système de modération
- Émojis et réactions

🔧 **Optimisations techniques**
- Cache des messages côté client
- Compression des données
- Indexation de la base de données
- Rate limiting pour éviter le spam
- Logging des erreurs plus avancé

## Dépannage

### L'application ne se charge pas
- Vérifiez que PHP fonctionne sur votre serveur
- Assurez-vous que l'extension SQLite est activée
- Vérifiez les permissions de fichiers

### Les messages ne s'affichent pas
- Ouvrez la console du navigateur (F12)
- Vérifiez les erreurs JavaScript
- Testez l'API directement : `http://localhost/PROJSYNDOU/api.php?action=check_session`

### Problèmes de base de données
- Supprimez le fichier `chat.db` pour réinitialiser
- Vérifiez les logs d'erreur PHP
- Assurez-vous que le dossier est en écriture

## Licence

Projet éducatif - Libre d'utilisation pour l'apprentissage

## Auteur

Application développée pour le projet école PROJSYNDOU