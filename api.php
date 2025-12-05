<?php
// Activation de l'affichage des erreurs pour débug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Garder à 0 pour éviter de polluer le JSON

// Configuration robuste des sessions PHP
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.gc_maxlifetime', 86400); // 24 heures
ini_set('session.save_handler', 'files');

// Gérer les sessions avec IDs valides PHP (seulement A-Z, a-z, 0-9, tirets)
$sessionStarted = false;

try {
    if (isset($_POST['client_id']) && !empty($_POST['client_id'])) {
        $providedId = $_POST['client_id'];
        // Nettoyer l'ID pour qu'il soit valide (remplacer underscores par tirets, garder que alphanum)
        $cleanId = preg_replace('/[^a-zA-Z0-9-]/', '', str_replace('_', '-', $providedId));
        // Limiter à 48 caractères (limite PHP)
        $cleanId = substr($cleanId, 0, 48);
        
        if (strlen($cleanId) >= 10) {
            session_id($cleanId);
            error_log("🔑 Utilisation client_id fourni (nettoyé): " . $cleanId);
        }
    }

    session_start();
    $sessionStarted = true;
    error_log("✅ Session démarrée avec ID: " . session_id());
    
    // Log de l'état de la session pour debug
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        error_log("🔐 Session contient user_id: {$_SESSION['user_id']} pour username: " . ($_SESSION['username'] ?? 'non défini'));
    } else {
        error_log("👤 Session sans user_id (invité ou pas encore connecté)");
    }
    
} catch (Exception $e) {
    error_log("❌ Erreur démarrage session: " . $e->getMessage());
    // Fallback avec session par défaut
    if (!$sessionStarted) {
        session_start();
        $sessionStarted = true;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

try {
    // Configuration de la base de données MySQL
    $config = include __DIR__ . '/config.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de configuration: ' . $e->getMessage()
    ]);
    exit;
}
$db_config = $config['database'];

// Initialiser la base de données MySQL
function initDatabase() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de base de données MySQL : " . $e->getMessage());
        return null;
    }
}

// Nettoyer et valider l'entrée utilisateur
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Valider le nom d'utilisateur
function isValidUsername($username) {
    $pattern = '/^[a-zA-Z0-9_\-\sÀ-ÿ]+$/u';
    $max_length = 20;
    return !empty($username) && strlen($username) <= $max_length && preg_match($pattern, $username);
}

// Valider le message
function isValidMessage($message) {
    $max_length = 500;
    return !empty($message) && strlen($message) <= $max_length;
}

// Envoyer une réponse JSON
function sendResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Initialiser la base de données
try {
    $pdo = initDatabase();
    if (!$pdo) {
        sendResponse(false, null, 'Erreur de connexion à la base de données');
    }
} catch (Exception $e) {
    sendResponse(false, null, 'Erreur d\'initialisation de la base: ' . $e->getMessage());
}

// Récupérer l'action demandée (GET ou POST)
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Log pour debug
if ($action === 'send_message' && isset($_POST['is_audio'])) {
    error_log("Action audio reçue: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
}

// Traitement des différentes actions
switch ($action) {
    case 'check_session':
        $username = $_SESSION['username'] ?? '';
        $sessionStart = $_SESSION['session_start'] ?? 0;
        $currentSessionId = session_id();
        
        // Debug détaillé de l'état de la session
        error_log("🔍 check_session DEBUG:");
        error_log("  - username: '$username'");
        error_log("  - session_id: '$currentSessionId'");
        error_log("  - session_start: $sessionStart");
        error_log("  - session_status: " . session_status());
        error_log("  - toutes les vars session: " . print_r($_SESSION, true));
        
        // Vérifier si la session est toujours valide
        if ($username) {
            try {
                // D'abord vérifier si l'entrée existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM active_users WHERE username = ? AND session_id = ?");
                $stmt->execute([$username, $currentSessionId]);
                $exists = $stmt->fetchColumn();
                
                if ($exists > 0) {
                    // Mettre à jour l'activité
                    $stmt = $pdo->prepare("UPDATE active_users SET last_activity = NOW() WHERE username = ? AND session_id = ?");
                    $stmt->execute([$username, $currentSessionId]);
                    error_log("✅ Session validée et mise à jour pour: $username");
                } else {
                    // Tenter de recréer l'entrée plutôt que de détruire la session
                    try {
                        $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
                        $stmt->execute([$username, $currentSessionId]);
                        error_log("🔄 Session recréée pour: $username");
                    } catch (PDOException $e2) {
                        error_log("❌ Impossible de recréer la session: " . $e2->getMessage());
                        session_destroy();
                        $username = '';
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la vérification de session : " . $e->getMessage());
                // Ne pas détruire la session sur une erreur de BD
            }
        }
        
        // Préparer les données de réponse
        $responseData = [
            'username' => $username, 
            'session_id' => $currentSessionId, 
            'session_start' => $sessionStart
        ];
        
        // Vérifier en DB si l'utilisateur est un abonné (existe dans la table users)
        $isGuest = true; // Par défaut invité
        $userId = null;
        
        if (!empty($username)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Utilisateur trouvé dans la table users = ABONNÉ
                    $isGuest = false;
                    $userId = $user['id'];
                    $_SESSION['user_id'] = $userId; // Synchroniser la session
                    error_log("📊 check_session - ABONNÉ en DB: $username (user_id: $userId)");
                } else {
                    // Pas dans la table users = INVITÉ
                    error_log("📊 check_session - INVITÉ (pas dans users): $username");
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification utilisateur en DB: " . $e->getMessage());
            }
        }
        
        if ($username && $isGuest) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE username = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt->execute([$username]);
                $messageCount = $stmt->fetchColumn();
                $guestLimit = $config['limits']['guest_message_limit'];
                $warningAt = $config['limits']['warning_at_message'];
                
                $responseData['is_guest'] = true;
                $responseData['message_count'] = $messageCount;
                $responseData['remaining'] = max(0, $guestLimit - $messageCount);
                $responseData['limit'] = $guestLimit;
                $responseData['show_warning'] = $messageCount >= $warningAt;
            } catch (PDOException $e) {
                error_log("Erreur comptage messages invité: " . $e->getMessage());
            }
        } else {
            $responseData['is_guest'] = false;
            if (isset($_SESSION['user_id'])) {
                $responseData['user_id'] = $_SESSION['user_id'];
            }
        }
        
        sendResponse(true, $responseData);
        break;
        
    case 'login':
        // Accepter les données de formulaire ET JSON
        if (isset($_POST['username'])) {
            $username = sanitizeInput($_POST['username']);
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = sanitizeInput($input['username'] ?? '');
        }
        
        if (!isValidUsername($username)) {
            sendResponse(false, null, 'Nom d\'utilisateur invalide (1-20 caractères, lettres, chiffres, _ - et espaces autorisés)');
        }
        
        // Nettoyer les sessions vraiment inactives (30 secondes pour réactivité instantanée)
        try {
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            $stmt->execute();
            $cleanedCount = $stmt->rowCount();
            if ($cleanedCount > 0) {
                error_log("🧹 Sessions inactives nettoyées: $cleanedCount");
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du nettoyage : " . $e->getMessage());
        }
        
        // Générer un nom d'utilisateur unique si nécessaire
        $originalUsername = $username;
        $counter = 1;
        
        try {
            // Vérifier si ce nom d'utilisateur exact est déjà utilisé (session vraiment active)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM active_users WHERE username = ? AND session_id != ? AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $stmt->execute([$username, session_id()]);
            $otherActiveUser = $stmt->fetchColumn();
            
            error_log("🔍 Vérification nom '$username': $otherActiveUser autre(s) session(s) active(s)");
            
            // Si le nom est déjà pris, générer une variante unique
            while ($otherActiveUser > 0 && $counter <= 100) {
                $username = $originalUsername . '_' . $counter;
                $stmt->execute([$username, session_id()]);
                $otherActiveUser = $stmt->fetchColumn();
                error_log("🔄 Test nom '$username': $otherActiveUser conflit(s)");
                $counter++;
            }
            
            if ($counter > 100) {
                sendResponse(false, null, 'Trop d\'utilisateurs avec ce nom. Essayez un nom différent.');
            }
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du nom d'utilisateur : " . $e->getMessage());
        }
        
        // Supprimer seulement l'ancienne entrée de CETTE session spécifique
        $oldUsername = $_SESSION['username'] ?? '';
        if ($oldUsername && $oldUsername !== $username) {
            try {
                // Supprimer seulement cette session, pas toutes les occurrences du nom
                $stmt = $pdo->prepare("DELETE FROM active_users WHERE username = ? AND session_id = ?");
                $stmt->execute([$oldUsername, session_id()]);
                error_log("🗑️ Ancien utilisateur supprimé: $oldUsername (session: " . session_id() . ")");
            } catch (PDOException $e) {
                error_log("Erreur lors de la suppression de l'ancien utilisateur : " . $e->getMessage());
            }
        }
        
        $_SESSION['username'] = $username;
        $_SESSION['session_start'] = time();
        
        // FORCER la sauvegarde des données de session
        session_write_close();
        session_start(); // Rouvrir pour continuer à utiliser la session
        
        // Vérifier que les données ont été sauvegardées
        if ($_SESSION['username'] !== $username) {
            error_log("⚠️ PROBLÈME: Session non sauvegardée! Retry...");
            $_SESSION['username'] = $username;
            $_SESSION['session_start'] = time();
            session_write_close();
            session_start();
        }
        
        error_log("💾 Session sauvegardée: username='" . ($_SESSION['username'] ?? 'VIDE') . "', session_id='" . session_id() . "'");
        
        // Utiliser INSERT ... ON DUPLICATE KEY UPDATE pour éviter les suppressions accidentelles
        try {
            // Supprimer SEULEMENT les anciennes entrées de cette session spécifique
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE session_id = ?");
            $deletedRows = $stmt->execute([session_id()]);
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0) {
                error_log("🗑️ Anciennes entrées supprimées pour cette session: $deletedCount");
            }
            
            // Insérer la nouvelle entrée avec vérification
            $currentSessionId = session_id();
            if (empty($currentSessionId)) {
                error_log("⚠️ ATTENTION: session_id est vide pour $username");
                $currentSessionId = 'fallback_' . uniqid() . '_' . time();
            }
            
            $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
            $stmt->execute([$username, $currentSessionId]);
            
            // Vérifier que l'insertion s'est bien passée
            $stmt = $pdo->prepare("SELECT session_id FROM active_users WHERE username = ? AND session_id = ?");
            $stmt->execute([$username, $currentSessionId]);
            $verifyResult = $stmt->fetch();
            
            if ($verifyResult) {
                error_log("✅ Utilisateur connecté: $username avec session: " . $currentSessionId);
                error_log("🔍 Vérification BD réussie pour session: " . $verifyResult['session_id']);
            } else {
                error_log("❌ ERREUR: Session non trouvée en BD après insertion!");
            }
        } catch (PDOException $e) {
            error_log("❌ Erreur lors de l'insertion de l'utilisateur actif : " . $e->getMessage());
            
            // Créer la table si elle n'existe pas
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS active_users (
                    username VARCHAR(50) NOT NULL,
                    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    session_id VARCHAR(255) NOT NULL,
                    PRIMARY KEY (username, session_id),
                    INDEX idx_session (session_id),
                    INDEX idx_activity (last_activity)
                )");
                error_log("📋 Table active_users créée/vérifiée avec session_id");
                
                $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?)");
                $stmt->execute([$username, session_id()]);
                error_log("✅ Table créée et utilisateur inséré: $username");
            } catch (PDOException $e2) {
                error_log("❌ Erreur critique : " . $e2->getMessage());
            }
        }
        
        // Informer l'utilisateur si son nom a été modifié
        $message = 'Connexion réussie';
        if ($username !== $originalUsername) {
            $message .= " (nom modifié en '$username' car '$originalUsername' était déjà utilisé)";
        }
        
        sendResponse(true, ['username' => $username, 'session_id' => session_id()], $message);
        break;
        
    case 'logout':
        $username = $_SESSION['username'] ?? '';
        $sessionId = session_id();
        
        if ($username) {
            // Supprimer seulement cette session spécifique, pas tous les utilisateurs avec ce nom
            try {
                $stmt = $pdo->prepare("DELETE FROM active_users WHERE username = ? AND session_id = ?");
                $result = $stmt->execute([$username, $sessionId]);
                error_log("Déconnexion: $username (session: $sessionId) - Lignes supprimées: " . $stmt->rowCount());
                
                // Ne pas utiliser de fallback qui pourrait supprimer d'autres sessions
                if ($stmt->rowCount() === 0) {
                    error_log("⚠️ Aucune session trouvée pour: $username (session: $sessionId)");
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la suppression de l'utilisateur actif : " . $e->getMessage());
            }
        }
        
        session_destroy();
        sendResponse(true, null, 'Déconnexion réussie');
        break;
        
    case 'send_message':
        // Nettoyage proactif des sessions inactives
        try {
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur nettoyage proactif: " . $e->getMessage());
        }
        
        // Vérification d'authentification améliorée avec debug
        $currentSessionId = session_id();
        $username = $_SESSION['username'] ?? '';
        $postedUsername = $_POST['username'] ?? '';
        
        // Vérifier en DB si l'utilisateur est un abonné (existe dans la table users)
        $isGuest = true; // Par défaut invité
        $userId = null;
        
        if (!empty($username)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Utilisateur trouvé dans la table users = ABONNÉ
                    $isGuest = false;
                    $userId = $user['id'];
                    $_SESSION['user_id'] = $userId; // Synchroniser la session
                    error_log("👤 ABONNÉ détecté en DB: $username (user_id: $userId)");
                } else {
                    // Pas dans la table users = INVITÉ
                    error_log("👥 INVITÉ détecté (pas dans users): $username");
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification utilisateur en DB: " . $e->getMessage());
            }
        }
        
        error_log("🔍 send_message - Session ID: '$currentSessionId', Session Username: '$username', Posted Username: '$postedUsername'");
        
        // Utiliser le username de la session ou du POST si disponible
        if (empty($username) && !empty($postedUsername)) {
            // Vérifier si l'utilisateur posté correspond à une session active
            $stmt = $pdo->prepare("SELECT username FROM active_users WHERE username = ? AND session_id = ?");
            $stmt->execute([$postedUsername, $currentSessionId]);
            $activeUser = $stmt->fetchColumn();
            
            if ($activeUser) {
                $username = $activeUser;
                $_SESSION['username'] = $username;
                error_log("🔄 Username restauré depuis active_users: $username");
            } else {
                // Tentative de création d'une nouvelle entrée active_users si l'utilisateur semble valide
                if (strlen($postedUsername) > 0 && strlen($postedUsername) <= 20) {
                    $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_activity = NOW(), session_id = ?");
                    $stmt->execute([$postedUsername, $currentSessionId, $currentSessionId]);
                    $username = $postedUsername;
                    $_SESSION['username'] = $username;
                    error_log("🆕 Nouvelle entrée active_users créée pour: $username avec session: $currentSessionId");
                }
            }
        }
        
        if (empty($username)) {
            error_log("❌ Échec d'authentification pour send_message - Username vide après vérifications");
            sendResponse(false, null, 'Session expirée. Veuillez vous reconnecter.');
            return;
        }
        
        // Vérifier la limite de messages pour les invités
        if ($isGuest) {
            $guestLimit = $config['limits']['guest_message_limit'];
            
            try {
                // Compter les messages de cet invité dans cette session
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE username = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt->execute([$username]);
                $messageCount = $stmt->fetchColumn();
                
                if ($messageCount >= $guestLimit) {
                    error_log("🚫 Limite atteinte pour invité '$username': $messageCount/$guestLimit messages");
                    sendResponse(false, ['limit_reached' => true, 'message_count' => $messageCount, 'limit' => $guestLimit], 
                        "Limite de $guestLimit messages atteinte. Créez un compte pour continuer à discuter !");
                    return;
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification limite invité: " . $e->getMessage());
            }
        }
        
        // Vérifier que l'utilisateur existe encore dans la base
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM active_users WHERE username = ? AND session_id = ?");
            $stmt->execute([$username, $currentSessionId]);
            $exists = $stmt->fetchColumn();
            
            if ($exists == 0) {
                error_log("❌ Utilisateur '$username' non trouvé dans active_users avec session '$currentSessionId'");
                // Recréer l'entrée plutôt que de rejeter
                $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
                $stmt->execute([$username, $currentSessionId]);
                error_log("🔄 Entrée active_users recréée pour $username");
            }
        } catch (PDOException $e) {
            error_log("Erreur vérification utilisateur actif: " . $e->getMessage());
        }
        
        $username = $_SESSION['username'];
        error_log("✅ Utilisateur authentifié pour message: $username (session: " . session_id() . ")");
        $is_audio = isset($_POST['is_audio']) && $_POST['is_audio'] === '1';
        
        if ($is_audio) {
            // Gestion des messages vocaux
            error_log("Traitement message vocal pour utilisateur: " . $username);
            error_log("Fichiers reçus: " . print_r($_FILES, true));
            
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== 0) {
                $error_msg = 'Erreur upload audio';
                if (isset($_FILES['audio']['error'])) {
                    $error_msg .= ' (code: ' . $_FILES['audio']['error'] . ')';
                }
                error_log($error_msg);
                sendResponse(false, null, $error_msg);
            }
            
            $uploadDir = 'uploads/audio/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $audioFile = $_FILES['audio'];
            $fileName = uniqid() . '_' . time() . '.webm';
            $filePath = $uploadDir . $fileName;
            $duration = intval($_POST['duration'] ?? 0);
            
            if (move_uploaded_file($audioFile['tmp_name'], $filePath)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (username, message, is_audio, audio_path, audio_duration, timestamp) VALUES (?, ?, 1, ?, ?, NOW())");
                $stmt->execute([$username, '[Message vocal]', $fileName, $duration]);
                
                // Mettre à jour l'activité de l'utilisateur pour cette session
                $stmt = $pdo->prepare("UPDATE active_users SET last_activity = NOW() WHERE username = ? AND session_id = ?");
                $updated = $stmt->execute([$username, session_id()]);
                error_log("🔄 Activité mise à jour pour $username (session: " . session_id() . ") - Lignes affectées: " . $stmt->rowCount());
                sendResponse(true, ['id' => $pdo->lastInsertId()], 'Message vocal envoyé');
                return;
                } catch (PDOException $e) {
                    error_log("Erreur lors de l'envoi du message vocal : " . $e->getMessage());
                    sendResponse(false, null, 'Erreur serveur');
                    return;
                }
            } else {
                sendResponse(false, null, 'Erreur sauvegarde audio');
                return;
            }
        } else {
            // Messages texte normaux - support FormData ET JSON
            if (isset($_POST['message'])) {
                $message = sanitizeInput($_POST['message']);
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                $message = sanitizeInput($input['message'] ?? '');
            }
            
            error_log("📤 Message reçu: \"$message\" de $username");
            
            if (!isValidMessage($message)) {
                sendResponse(false, null, "Message invalide (1-500 caractères requis)");
                return;
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (username, message, timestamp) VALUES (?, ?, NOW())");
                $stmt->execute([$username, $message]);
                
                // Mettre à jour l'activité de l'utilisateur pour cette session spécifique
                $stmt = $pdo->prepare("UPDATE active_users SET last_activity = NOW() WHERE username = ? AND session_id = ?");
                $stmt->execute([$username, session_id()]);
                
                // Si c'est un invité, calculer les messages restants
                $responseData = ['id' => $pdo->lastInsertId()];
                if ($isGuest) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE username = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $stmt->execute([$username]);
                    $messageCount = $stmt->fetchColumn();
                    $guestLimit = $config['limits']['guest_message_limit'];
                    $warningAt = $config['limits']['warning_at_message'];
                    
                    $responseData['is_guest'] = true;
                    $responseData['message_count'] = $messageCount;
                    $responseData['remaining'] = $guestLimit - $messageCount;
                    $responseData['limit'] = $guestLimit;
                    $responseData['show_warning'] = $messageCount >= $warningAt;
                } else {
                    // Utilisateur avec compte - pas de limite
                    $responseData['is_guest'] = false;
                    error_log("✅ Message envoyé par utilisateur avec compte: $username (user_id: {$_SESSION['user_id']})");
                }
                
                sendResponse(true, $responseData, 'Message envoyé');
                return;
            } catch (PDOException $e) {
                error_log("Erreur lors de l'envoi du message : " . $e->getMessage());
                sendResponse(false, null, 'Erreur lors de l\'envoi du message');
                return;
            }
        }
        break;
        
    case 'get_messages':
        // Nettoyage léger des sessions inactives toutes les 5 requêtes (pour ne pas surcharger)
        if (rand(1, 5) === 1) {
            try {
                $stmt = $pdo->prepare("DELETE FROM active_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
                $stmt->execute();
            } catch (PDOException $e) {
                error_log("Erreur nettoyage get_messages: " . $e->getMessage());
            }
        }
        
        $last_id = (int)($_GET['last_id'] ?? 0);
        $limit = 50;
        
        try {
            if ($last_id > 0) {
                // Récupérer seulement les nouveaux messages
                $stmt = $pdo->prepare("SELECT id, username, message, is_audio, audio_path, audio_duration, media_type, media_path, timestamp FROM messages WHERE id > ? ORDER BY id ASC");
                $stmt->execute([$last_id]);
            } else {
                // Récupérer les derniers messages
                $stmt = $pdo->prepare("SELECT id, username, message, is_audio, audio_path, audio_duration, media_type, media_path, timestamp FROM messages ORDER BY id DESC LIMIT " . intval($limit));
                $stmt->execute();
                $messages = $stmt->fetchAll();
                $messages = array_reverse($messages); // Inverser pour avoir l'ordre chronologique
                sendResponse(true, ['messages' => $messages]);
                break;
            }
            
            $messages = $stmt->fetchAll();
            sendResponse(true, ['messages' => $messages]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des messages : " . $e->getMessage());
            sendResponse(false, null, 'Erreur PDO: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Erreur générale lors de la récupération des messages : " . $e->getMessage());
            sendResponse(false, null, 'Erreur générale: ' . $e->getMessage());
        }
        break;
        
    case 'typing':
        $currentSessionId = session_id();
        $username = $_SESSION['username'] ?? '';
        
        error_log("⌨️ typing - Session ID: '$currentSessionId', Username: '$username'");
        
        if (empty($username)) {
            error_log("❌ Échec d'authentification pour typing - Username vide");
            sendResponse(false, null, 'Session expirée. Veuillez vous reconnecter.');
            return;
        }
        
        // Support FormData ET JSON pour typing
        if (isset($_POST['typing'])) {
            $is_typing = $_POST['typing'] === '1' || $_POST['typing'] === 'true';
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            $is_typing = $input['typing'] ?? false;
        }
        
        $username = $_SESSION['username'];
        
        try {
            if ($is_typing) {
                // Créer ou mettre à jour le statut de frappe avec session_id
                $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
                $stmt->execute([$username, session_id()]);
                error_log("⌨️ Typing mis à jour pour $username (session: " . session_id() . ")");
            }
            sendResponse(true, null, 'Statut de frappe mis à jour');
        } catch (PDOException $e) {
            error_log("Erreur typing: " . $e->getMessage());
            sendResponse(false, null, 'Erreur lors de la mise à jour du statut');
        }
        break;
        
    case 'get_active_users':
        $timeout_seconds = 30; // 30 secondes pour réactivité instantanée
        
        // Mettre à jour l'activité de l'utilisateur courant s'il est connecté
        $currentUser = $_SESSION['username'] ?? '';
        if ($currentUser) {
            try {
                $stmt = $pdo->prepare("UPDATE active_users SET last_activity = NOW() WHERE username = ? AND session_id = ?");
                $stmt->execute([$currentUser, session_id()]);
            } catch (PDOException $e) {
                error_log("Erreur mise à jour activité get_active_users: " . $e->getMessage());
            }
        }
        
        try {
            // Nettoyer les utilisateurs vraiment inactifs (30 secondes)
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            $cleaned = $stmt->execute();
            $cleanedRows = $stmt->rowCount();
            
            // Récupérer tous les utilisateurs actifs avec leurs sessions (délai cohérent)
            $stmt = $pdo->prepare("SELECT username, session_id, last_activity FROM active_users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 SECOND) ORDER BY username");
            $stmt->execute();
            $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Préparer la liste détaillée des utilisateurs avec comptage
            $userCounts = [];
            foreach ($allUsers as $user) {
                $baseName = preg_replace('/_\d+$/', '', $user['username']); // Enlever le suffixe numérique
                if (!isset($userCounts[$baseName])) {
                    $userCounts[$baseName] = [];
                }
                $userCounts[$baseName][] = $user['username'];
            }
            
            // Créer la liste d'affichage avec compteurs
            $displayUsers = [];
            foreach ($userCounts as $baseName => $usernames) {
                if (count($usernames) > 1) {
                    $displayUsers[] = $baseName . ' (' . count($usernames) . ')';
                } else {
                    $displayUsers[] = $usernames[0];
                }
            }
            
            sort($displayUsers);
            
            error_log("🔍 Utilisateurs en base (" . count($allUsers) . " entrées, $cleanedRows nettoyées): " . json_encode($allUsers));
            error_log("👥 Utilisateurs affichés (" . count($displayUsers) . "): " . implode(', ', $displayUsers));
            
            sendResponse(true, [
                'users' => $displayUsers,
                'total_connections' => count($allUsers),
                'unique_users' => count($userCounts)
            ]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des utilisateurs actifs : " . $e->getMessage());
            sendResponse(false, null, 'Erreur lors de la récupération des utilisateurs actifs');
        }
        break;
        
    case 'clear_messages':
        // Action d'administration pour nettoyer les anciens messages
        if (!isset($_SESSION['username'])) {
            sendResponse(false, null, 'Non authentifié');
        }
        
        $cleanup_hours = 24;
        
        try {
            // Supprimer les messages anciens
            $stmt = $pdo->prepare("DELETE FROM messages WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? HOUR)");
            $stmt->execute([$cleanup_hours]);
            $deleted = $stmt->rowCount();
            
            sendResponse(true, ['deleted' => $deleted], "Messages nettoyés ($deleted supprimés)");
        } catch (PDOException $e) {
            error_log("Erreur lors du nettoyage des messages : " . $e->getMessage());
            sendResponse(false, null, 'Erreur lors du nettoyage');
        }
        break;
        
    case 'send_media':
        // Vérification d'authentification
        $currentSessionId = session_id();
        $username = $_SESSION['username'] ?? '';
        
        if (empty($username)) {
            error_log("❌ Échec d'authentification pour send_media - Username vide");
            sendResponse(false, null, 'Session expirée. Veuillez vous reconnecter.');
            return;
        }
        
        // Vérifier en DB si l'utilisateur est un abonné
        $isGuest = true;
        $userId = null;
        
        if (!empty($username)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $isGuest = false;
                    $userId = $user['id'];
                    $_SESSION['user_id'] = $userId;
                    error_log("👤 ABONNÉ envoi média: $username (user_id: $userId)");
                } else {
                    error_log("👥 INVITÉ envoi média: $username");
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification utilisateur en DB: " . $e->getMessage());
            }
        }
        
        // Vérifier la limite pour les invités
        if ($isGuest) {
            $guestLimit = $config['limits']['guest_message_limit'];
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE username = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt->execute([$username]);
                $messageCount = $stmt->fetchColumn();
                
                if ($messageCount >= $guestLimit) {
                    error_log("🚫 Limite atteinte pour invité '$username': $messageCount/$guestLimit messages");
                    sendResponse(false, ['limit_reached' => true, 'message_count' => $messageCount, 'limit' => $guestLimit], 
                        "Limite de $guestLimit messages atteinte. Créez un compte pour continuer !");
                    return;
                }
            } catch (PDOException $e) {
                error_log("Erreur vérification limite invité: " . $e->getMessage());
            }
        }
        
        // Vérifier qu'un fichier a été uploadé
        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Erreur lors de l\'upload du fichier';
            
            if (isset($_FILES['media']['error'])) {
                $errorCode = $_FILES['media']['error'];
                switch ($errorCode) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_msg = 'Le fichier dépasse la limite définie dans php.ini (upload_max_filesize: ' . ini_get('upload_max_filesize') . '). Vidéos max 20MB.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = 'Le fichier est trop volumineux (dépassement de MAX_FILE_SIZE).';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_msg = 'Le fichier n\'a été que partiellement uploadé. Réessayez.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg = 'Aucun fichier n\'a été uploadé.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_msg = 'Dossier temporaire manquant sur le serveur.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_msg = 'Impossible d\'écrire le fichier sur le disque.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_msg = 'Upload bloqué par une extension PHP.';
                        break;
                    default:
                        $error_msg .= ' (code d\'erreur: ' . $errorCode . ')';
                }
            }
            
            error_log("❌ Erreur upload média: $error_msg");
            sendResponse(false, null, $error_msg);
            return;
        }
        
        $mediaFile = $_FILES['media'];
        $caption = sanitizeInput($_POST['caption'] ?? '');
        
        // Déterminer le type de média
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $mediaFile['tmp_name']);
        finfo_close($finfo);
        
        $isImage = strpos($mimeType, 'image/') === 0;
        $isVideo = strpos($mimeType, 'video/') === 0;
        
        if (!$isImage && !$isVideo) {
            sendResponse(false, null, 'Type de fichier non supporté. Utilisez des images ou vidéos.');
            return;
        }
        
        // Vérifier la taille du fichier
        $maxImageSize = 5 * 1024 * 1024; // 5MB
        $maxVideoSize = 20 * 1024 * 1024; // 20MB
        
        if ($isImage && $mediaFile['size'] > $maxImageSize) {
            sendResponse(false, null, 'L\'image ne doit pas dépasser 5MB');
            return;
        }
        
        if ($isVideo && $mediaFile['size'] > $maxVideoSize) {
            sendResponse(false, null, 'La vidéo ne doit pas dépasser 20MB');
            return;
        }
        
        // Créer les dossiers si nécessaire
        $uploadDir = $isImage ? 'uploads/images/' : 'uploads/videos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($mediaFile['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($mediaFile['tmp_name'], $filePath)) {
            try {
                // Insérer dans la base de données
                $mediaType = $isImage ? 'image' : 'video';
                $message = $caption ? $caption : '[' . ucfirst($mediaType) . ']';
                
                $stmt = $pdo->prepare("INSERT INTO messages (username, message, media_type, media_path, timestamp) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $message, $mediaType, $fileName]);
                
                // Mettre à jour l'activité
                $stmt = $pdo->prepare("UPDATE active_users SET last_activity = NOW() WHERE username = ? AND session_id = ?");
                $stmt->execute([$username, $currentSessionId]);
                
                // Préparer la réponse
                $responseData = ['id' => $pdo->lastInsertId()];
                
                // Compteurs pour les invités
                if ($isGuest) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE username = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $stmt->execute([$username]);
                    $messageCount = $stmt->fetchColumn();
                    $guestLimit = $config['limits']['guest_message_limit'];
                    $warningAt = $config['limits']['warning_at_message'];
                    
                    $responseData['is_guest'] = true;
                    $responseData['message_count'] = $messageCount;
                    $responseData['remaining'] = $guestLimit - $messageCount;
                    $responseData['limit'] = $guestLimit;
                    $responseData['show_warning'] = $messageCount >= $warningAt;
                } else {
                    $responseData['is_guest'] = false;
                }
                
                error_log("✅ Média envoyé: $mediaType - $fileName par $username");
                sendResponse(true, $responseData, 'Média envoyé avec succès');
                return;
                
            } catch (PDOException $e) {
                error_log("Erreur lors de l'enregistrement du média : " . $e->getMessage());
                // Supprimer le fichier en cas d'erreur
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                sendResponse(false, null, 'Erreur lors de l\'enregistrement');
                return;
            }
        } else {
            sendResponse(false, null, 'Erreur lors de la sauvegarde du fichier');
            return;
        }
        break;
        
    case 'get_media':
        $type = $_GET['type'] ?? '';
        $filename = $_GET['file'] ?? '';
        error_log("Demande fichier média: " . $type . "/" . $filename);
        
        if (empty($filename) || !preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9]+$/', $filename)) {
            error_log("Nom de fichier invalide: " . $filename);
            http_response_code(404);
            exit('Fichier non trouvé');
        }
        
        $uploadDir = $type === 'image' ? 'uploads/images/' : 'uploads/videos/';
        $filePath = $uploadDir . $filename;
        
        if (!file_exists($filePath)) {
            error_log("Fichier n'existe pas: " . $filePath);
            http_response_code(404);
            exit('Fichier non trouvé');
        }
        
        // Déterminer le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        readfile($filePath);
        exit;
        break;
    
    case 'get_audio':
        $filename = $_GET['file'] ?? '';
        error_log("Demande fichier audio: " . $filename);
        
        if (empty($filename) || !preg_match('/^[a-zA-Z0-9_]+\.(webm|mp4|ogg)$/', $filename)) {
            error_log("Nom de fichier invalide: " . $filename);
            http_response_code(404);
            exit('Fichier non trouvé');
        }
        
        $filePath = 'uploads/audio/' . $filename;
        if (!file_exists($filePath)) {
            error_log("Fichier n'existe pas: " . $filePath);
            http_response_code(404);
            exit('Fichier non trouvé');
        }
        
        $mimeType = 'audio/webm';
        if (strpos($filename, '.mp4') !== false) {
            $mimeType = 'audio/mp4';
        } elseif (strpos($filename, '.ogg') !== false) {
            $mimeType = 'audio/ogg';
        }
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        readfile($filePath);
        exit;
        break;
        
    case 'register':
        // Récupérer les données d'inscription
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation des données
        if (!isValidUsername($username)) {
            sendResponse(false, null, 'Nom d\'utilisateur invalide (1-20 caractères, lettres, chiffres, _ - et espaces autorisés)');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, null, 'Adresse email invalide');
        }
        
        if (strlen($password) < 6) {
            sendResponse(false, null, 'Le mot de passe doit contenir au moins 6 caractères');
        }
        
        if ($password !== $confirmPassword) {
            sendResponse(false, null, 'Les mots de passe ne correspondent pas');
        }
        
        try {
            // Vérifier si le nom d'utilisateur existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                sendResponse(false, null, 'Ce nom d\'utilisateur est déjà utilisé');
            }
            
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                sendResponse(false, null, 'Cette adresse email est déjà utilisée');
            }
            
            // Créer le compte
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash]);
            
            error_log("✅ Nouveau compte créé: $username ($email)");
            sendResponse(true, ['username' => $username], 'Compte créé avec succès! Vous pouvez maintenant vous connecter.');
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du compte : " . $e->getMessage());
            sendResponse(false, null, 'Erreur lors de la création du compte');
        }
        break;
        
    case 'login_account':
        // Connexion avec compte utilisateur
        $identifier = sanitizeInput($_POST['identifier'] ?? ''); // username ou email
        $password = $_POST['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            sendResponse(false, null, 'Veuillez remplir tous les champs');
        }
        
        try {
            // Rechercher l'utilisateur par username ou email
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, is_active FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(false, null, 'Identifiant ou mot de passe incorrect');
            }
            
            // Vérifier si le compte est actif
            if (!$user['is_active']) {
                sendResponse(false, null, 'Ce compte a été désactivé');
            }
            
            // Vérifier le mot de passe
            if (!password_verify($password, $user['password_hash'])) {
                sendResponse(false, null, 'Identifiant ou mot de passe incorrect');
            }
            
            // Nettoyer les sessions inactives
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            $stmt->execute();
            
            // Créer la session
            $username = $user['username'];
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['session_start'] = time();
            
            // NE PAS faire session_write_close/session_start ici car ça réinitialise la session
            // Les données de session sont automatiquement sauvegardées à la fin du script
            error_log("🔐 Session créée avec user_id: {$user['id']} pour $username");
            
            // Mettre à jour la dernière connexion
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Ajouter à la liste des utilisateurs actifs
            $currentSessionId = session_id();
            $stmt = $pdo->prepare("DELETE FROM active_users WHERE session_id = ?");
            $stmt->execute([$currentSessionId]);
            
            $stmt = $pdo->prepare("INSERT INTO active_users (username, last_activity, session_id) VALUES (?, NOW(), ?)");
            $stmt->execute([$username, $currentSessionId]);
            
            error_log("✅ Connexion réussie avec compte: $username (ID: {$user['id']})");
            sendResponse(true, [
                'username' => $username,
                'session_id' => $currentSessionId,
                'session_start' => $_SESSION['session_start'],
                'is_guest' => false,
                'user_id' => $user['id']
            ], 'Connexion réussie!');
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la connexion : " . $e->getMessage());
            sendResponse(false, null, 'Erreur lors de la connexion');
        }
        break;
        
    default:
        sendResponse(false, null, 'Action non reconnue');
        break;
}
?>