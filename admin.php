<?php
// Script d'administration pour l'application de chat
session_start();

// Configuration
$db_file = __DIR__ . '/chat.db';

// Connexion à la base de données
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Traitement des actions
$action = $_GET['action'] ?? '';
$message = '';

switch ($action) {
    case 'clear_messages':
        $stmt = $pdo->prepare("DELETE FROM messages");
        $stmt->execute();
        $count = $stmt->rowCount();
        $message = "Tous les messages ont été supprimés ($count messages)";
        break;
        
    case 'clear_old_messages':
        $stmt = $pdo->prepare("DELETE FROM messages WHERE datetime(timestamp, '+24 hours') < datetime('now')");
        $stmt->execute();
        $count = $stmt->rowCount();
        $message = "Messages de plus de 24h supprimés ($count messages)";
        break;
        
    case 'clear_users':
        $stmt = $pdo->prepare("DELETE FROM active_users");
        $stmt->execute();
        $count = $stmt->rowCount();
        $message = "Tous les utilisateurs actifs ont été déconnectés ($count utilisateurs)";
        break;
        
    case 'reset_db':
        $pdo->exec("DROP TABLE IF EXISTS messages");
        $pdo->exec("DROP TABLE IF EXISTS active_users");
        
        $pdo->exec("CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            message TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE active_users (
            username TEXT PRIMARY KEY,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $message = "Base de données réinitialisée avec succès";
        break;
}

// Statistiques
$stmt = $pdo->query("SELECT COUNT(*) FROM messages");
$total_messages = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM active_users");
$active_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT username) FROM messages");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT * FROM messages ORDER BY id DESC LIMIT 10");
$recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM active_users ORDER BY last_activity DESC");
$current_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Chat en Temps Réel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #4facfe;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4facfe;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        
        .section {
            margin: 2rem 0;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        
        .section h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }
        
        .back-link {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4facfe;
            color: white;
            padding: 1rem;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .back-link:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <a href="index.html" class="back-link">← Retour au Chat</a>
    
    <div class="container">
        <header>
            <h1>🔧 Administration du Chat</h1>
            <p>Gestion et statistiques de l'application</p>
        </header>
        
        <div class="admin-content">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_messages ?></div>
                    <div>Messages total</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?= $active_users ?></div>
                    <div>Utilisateurs connectés</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?= $total_users ?></div>
                    <div>Utilisateurs uniques</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?= file_exists($db_file) ? number_format(filesize($db_file) / 1024, 1) : '0' ?>KB</div>
                    <div>Taille de la DB</div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="section">
                <h3>Actions d'administration</h3>
                <div class="actions">
                    <a href="?action=clear_old_messages" class="btn btn-warning" 
                       onclick="return confirm('Supprimer les messages de plus de 24h ?')">
                        Nettoyer anciens messages
                    </a>
                    
                    <a href="?action=clear_messages" class="btn btn-danger" 
                       onclick="return confirm('Supprimer TOUS les messages ?')">
                        Supprimer tous les messages
                    </a>
                    
                    <a href="?action=clear_users" class="btn btn-warning" 
                       onclick="return confirm('Déconnecter tous les utilisateurs ?')">
                        Déconnecter tous
                    </a>
                    
                    <a href="?action=reset_db" class="btn btn-danger" 
                       onclick="return confirm('RÉINITIALISER complètement la base de données ?')">
                        Reset complet
                    </a>
                    
                    <a href="install.php" class="btn btn-primary">Vérifier installation</a>
                </div>
            </div>
            
            <!-- Utilisateurs connectés -->
            <div class="section">
                <h3>Utilisateurs connectés (<?= count($current_users) ?>)</h3>
                <?php if (empty($current_users)): ?>
                    <div class="no-data">Aucun utilisateur connecté</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom d'utilisateur</th>
                                <th>Dernière activité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td>
                                        <?php
                                        $time = new DateTime($user['last_activity']);
                                        echo $time->format('d/m/Y H:i:s');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Messages récents -->
            <div class="section">
                <h3>Messages récents (10 derniers)</h3>
                <?php if (empty($recent_messages)): ?>
                    <div class="no-data">Aucun message</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilisateur</th>
                                <th>Message</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_messages as $msg): ?>
                                <tr>
                                    <td><?= $msg['id'] ?></td>
                                    <td><?= htmlspecialchars($msg['username']) ?></td>
                                    <td><?= htmlspecialchars(substr($msg['message'], 0, 50)) . (strlen($msg['message']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <?php
                                        $time = new DateTime($msg['timestamp']);
                                        echo $time->format('d/m H:i');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Informations système -->
            <div class="section">
                <h3>Informations système</h3>
                <table>
                    <tr>
                        <td><strong>Version PHP:</strong></td>
                        <td><?= PHP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td><strong>Extension SQLite:</strong></td>
                        <td><?= extension_loaded('sqlite3') ? '✅ Activée' : '❌ Non disponible' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Fichier DB:</strong></td>
                        <td><?= $db_file ?></td>
                    </tr>
                    <tr>
                        <td><strong>Permissions écriture:</strong></td>
                        <td><?= is_writable(__DIR__) ? '✅ OK' : '❌ Manquantes' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sessions:</strong></td>
                        <td><?= session_status() === PHP_SESSION_ACTIVE ? '✅ Actives' : '❌ Inactives' ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => {
            if (!window.location.search) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>