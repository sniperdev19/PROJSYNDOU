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

// Gérer les sessions multiples avec IDs vraiment uniques
$sessionStarted = false;

try {
    if (isset($_POST['client_id']) && !empty($_POST['client_id'])) {
        $providedId = $_POST['client_id'];
        // Valider l'ID fourni
        if (preg_match('/^[a-zA-Z0-9_]+$/', $providedId) && strlen($providedId) > 10) {
            session_id($providedId);
            error_log("🔑 Utilisation client_id fourni: " . $providedId);
        }
    } else {
        // Générer un ID de session unique s'il n'existe pas
        if (!isset($_COOKIE['client_id']) || empty($_COOKIE['client_id'])) {
            $clientId = 'chat_' . bin2hex(random_bytes(8)) . '_' . time() . '_' . mt_rand(1000, 9999);
            setcookie('client_id', $clientId, time() + 86400, '/', '', false, true); // 24 heures, secure
            session_id($clientId);
            error_log("🆕 Nouveau client_id généré: " . $clientId);
        } else {
            session_id($_COOKIE['client_id']);
            error_log("🍪 Utilisation cookie client_id: " . $_COOKIE['client_id']);
        }
    }

    session_start();
    $sessionStarted = true;
    error_log("✅ Session démarrée avec ID: " . session_id());
    
} catch (Exception $e) {
    error_log("❌ Erreur démarrage session: " . $e->getMessage());
    // Fallback avec session par défaut
    session_start();
    $sessionStarted = true;
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
        
        sendResponse(true, ['username' => $username, 'session_id' => $currentSessionId, 'session_start' => $sessionStart]);
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
                
                sendResponse(true, ['id' => $pdo->lastInsertId()], 'Message envoyé');
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
                $stmt = $pdo->prepare("SELECT id, username, message, is_audio, audio_path, audio_duration, timestamp FROM messages WHERE id > ? ORDER BY id ASC");
                $stmt->execute([$last_id]);
            } else {
                // Récupérer les derniers messages
                $stmt = $pdo->prepare("SELECT id, username, message, is_audio, audio_path, audio_duration, timestamp FROM messages ORDER BY id DESC LIMIT " . intval($limit));
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
        
    default:
        sendResponse(false, null, 'Action non reconnue');
        break;
}
?>