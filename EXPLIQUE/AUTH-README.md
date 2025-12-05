# Module de Création de Compte - Chat en Temps Réel

## 🎉 Nouvelles Fonctionnalités

Le chat dispose maintenant d'un système complet de gestion des comptes utilisateurs avec trois modes de connexion :

1. **Mode Invité** : Connexion rapide sans compte (comme avant)
2. **Mode Inscription** : Création d'un nouveau compte avec email et mot de passe
3. **Mode Connexion** : Connexion avec un compte existant

## 📋 Installation

### Étape 1 : Créer la table des utilisateurs

1. Assurez-vous que votre serveur WAMP/MySQL est démarré
2. Ouvrez votre navigateur et accédez à : `http://localhost/PROJET ECOLE/PROJSYNDOU2/setup-users.php`
3. Suivez les instructions à l'écran pour créer la table `users`

**Ou** exécutez manuellement le script SQL :

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Étape 2 : Utiliser le chat

Accédez à : `http://localhost/PROJET ECOLE/PROJSYNDOU2/index.html`

## 🔐 Fonctionnalités du Système d'Authentification

### Mode Invité (Sans compte)
- Connexion rapide avec un simple nom d'utilisateur
- Pas d'email ni de mot de passe requis
- Session temporaire (disparaît après déconnexion)

### Inscription (Créer un compte)
- **Champs requis** :
  - Nom d'utilisateur (1-20 caractères)
  - Adresse email valide
  - Mot de passe (minimum 6 caractères)
  - Confirmation du mot de passe
- **Validation** :
  - Nom d'utilisateur unique
  - Email unique
  - Format d'email valide
  - Correspondance des mots de passe

### Connexion avec compte
- Se connecter avec :
  - Nom d'utilisateur OU adresse email
  - Mot de passe
- La dernière connexion est enregistrée
- Session persistante

## 🛠️ API Endpoints

### 1. Inscription (`register`)
```javascript
POST api.php?action=register
FormData:
  - username: string (1-20 caractères)
  - email: string (email valide)
  - password: string (min 6 caractères)
  - confirm_password: string
```

**Réponse succès** :
```json
{
  "success": true,
  "data": {
    "username": "nom_utilisateur"
  },
  "message": "Compte créé avec succès! Vous pouvez maintenant vous connecter.",
  "timestamp": "2025-12-05 10:30:00"
}
```

### 2. Connexion avec compte (`login_account`)
```javascript
POST api.php?action=login_account
FormData:
  - client_id: string (ID unique du client)
  - identifier: string (username ou email)
  - password: string
```

**Réponse succès** :
```json
{
  "success": true,
  "data": {
    "username": "nom_utilisateur",
    "session_id": "session_id_unique",
    "session_start": 1733395800
  },
  "message": "Connexion réussie!",
  "timestamp": "2025-12-05 10:30:00"
}
```

### 3. Connexion invité (`login`)
Fonctionne comme avant - pas de modification

## 🔒 Sécurité

- **Mots de passe hashés** : Utilisation de `password_hash()` et `password_verify()` de PHP
- **Protection injection SQL** : Requêtes préparées avec PDO
- **Validation des entrées** : Sanitization avec `htmlspecialchars()`
- **Emails uniques** : Contrainte de base de données
- **Usernames uniques** : Contrainte de base de données
- **Sessions sécurisées** : Gestion des sessions PHP natives

## 📂 Fichiers Modifiés/Créés

### Nouveaux fichiers :
- `setup-users.php` : Script d'installation de la table users
- `create_users_table.sql` : Script SQL pour création manuelle
- `AUTH-README.md` : Ce fichier de documentation

### Fichiers modifiés :
- `api.php` : Ajout des endpoints `register` et `login_account`
- `index.html` : Ajout des formulaires d'inscription et connexion
- `script.js` : Ajout des méthodes `register()`, `loginWithAccount()`, `showAuthMode()`
- `style.css` : Ajout des styles pour les nouveaux formulaires

## 💡 Utilisation

### Pour l'utilisateur :

1. **Créer un compte** :
   - Cliquer sur "S'inscrire"
   - Remplir le formulaire
   - Soumettre

2. **Se connecter** :
   - Cliquer sur "Se connecter"
   - Entrer username/email et mot de passe
   - Soumettre

3. **Mode invité** :
   - Entrer juste un nom d'utilisateur
   - Cliquer sur "Rejoindre en tant qu'invité"

### Navigation entre les modes :
- Liens pour basculer entre inscription/connexion/invité
- Interface intuitive avec titres dynamiques
- Formulaires séparés pour chaque mode

## 🎨 Interface Utilisateur

- **Design cohérent** : Même style que le reste de l'application
- **Responsive** : Fonctionne sur mobile et desktop
- **Feedback visuel** : Notifications de succès/erreur
- **Navigation fluide** : Transition entre les modes sans rechargement

## 🐛 Gestion des Erreurs

Le système gère automatiquement :
- Nom d'utilisateur déjà pris
- Email déjà utilisé
- Mot de passe trop court
- Mots de passe non correspondants
- Email invalide
- Identifiants incorrects
- Compte désactivé

## 📊 Base de Données

### Table `users`
| Champ | Type | Description |
|-------|------|-------------|
| id | INT | Clé primaire auto-incrémentée |
| username | VARCHAR(50) | Nom d'utilisateur unique |
| email | VARCHAR(100) | Email unique |
| password_hash | VARCHAR(255) | Hash du mot de passe |
| created_at | TIMESTAMP | Date de création du compte |
| last_login | TIMESTAMP | Dernière connexion |
| is_active | TINYINT(1) | Statut du compte (actif/inactif) |

### Table `active_users` (existante)
Gère les sessions actives pour tous les utilisateurs (invités et comptes)

## ✅ Tests Recommandés

1. ✓ Créer un nouveau compte
2. ✓ Se connecter avec le compte créé
3. ✓ Tenter de créer un compte avec un username existant
4. ✓ Tenter de créer un compte avec un email existant
5. ✓ Se connecter avec un mauvais mot de passe
6. ✓ Se connecter en mode invité
7. ✓ Basculer entre les différents modes
8. ✓ Se déconnecter et vérifier la persistance de session

## 🚀 Améliorations Futures Possibles

- Réinitialisation de mot de passe par email
- Modification du profil utilisateur
- Avatar personnalisé
- Historique des messages par utilisateur
- Messages privés entre utilisateurs
- Rôles et permissions (admin, modérateur, etc.)
- Authentification OAuth (Google, Facebook, etc.)

## 📞 Support

Pour toute question ou problème, vérifiez :
1. Que WAMP est démarré
2. Que la base de données existe
3. Que la table `users` a été créée
4. Les logs d'erreur PHP dans `php_error.log`
5. La console du navigateur pour les erreurs JavaScript

---

**Développé pour le projet PROJSYNDOU2**
*Système de chat en temps réel avec gestion complète des utilisateurs*
