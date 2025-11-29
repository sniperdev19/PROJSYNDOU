<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation MySQL - Chat en Temps Réel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            width: 100%;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
            font-size: 2.5rem;
        }
        
        .step {
            margin: 2rem 0;
            padding: 1.5rem;
            border-left: 4px solid #4facfe;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
        }
        
        .step h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .step p {
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        
        .code {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            overflow-x: auto;
        }
        
        .btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .status {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        
        .config-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .form-group {
            margin: 1rem 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group small {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>🗄️ Installation MySQL</h1>
        
        <div class="step">
            <h3>📋 Prérequis</h3>
            <p>Assurez-vous que votre serveur WAMP/XAMPP est démarré avec :</p>
            <ul>
                <li>✅ Apache en cours d'exécution</li>
                <li>✅ MySQL en cours d'exécution</li>
                <li>✅ phpMyAdmin accessible</li>
            </ul>
        </div>

        <div class="step">
            <h3>🔧 Configuration de la base de données</h3>
            
            <div class="config-form">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="host">Hôte MySQL :</label>
                        <input type="text" id="host" name="host" value="localhost" required>
                        <small>Généralement 'localhost' pour WAMP/XAMPP</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur :</label>
                        <input type="text" id="username" name="username" value="root" required>
                        <small>Par défaut 'root' pour WAMP/XAMPP</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe :</label>
                        <input type="password" id="password" name="password" value="">
                        <small>Laissez vide si pas de mot de passe (WAMP par défaut)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="dbname">Nom de la base de données :</label>
                        <input type="text" id="dbname" name="dbname" value="chat_app" required>
                        <small>Sera créée automatiquement si elle n'existe pas</small>
                    </div>
                    
                    <button type="submit" name="install" class="btn">🚀 Installer MySQL</button>
                </form>
            </div>
        </div>

        <?php
        if (isset($_POST['install'])) {
            $host = $_POST['host'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $dbname = $_POST['dbname'];
            
            echo '<div class="step"><h3>📝 Résultats de l\'installation</h3>';
            
            try {
                // Test de connexion
                $dsn = "mysql:host=$host;charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo '<div class="status success">✅ Connexion à MySQL réussie</div>';
                
                // Créer la base de données
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo '<div class="status success">✅ Base de données créée : ' . $dbname . '</div>';
                
                // Se connecter à la base
                $pdo->exec("USE `$dbname`");
                
                // Créer les tables
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `messages` (
                        `id` INT(11) NOT NULL AUTO_INCREMENT,
                        `username` VARCHAR(20) NOT NULL,
                        `message` TEXT NOT NULL,
                        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_timestamp` (`timestamp`),
                        INDEX `idx_username` (`username`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `active_users` (
                        `username` VARCHAR(20) NOT NULL,
                        `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`username`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                echo '<div class="status success">✅ Tables créées avec succès</div>';
                
                // Mettre à jour le fichier de configuration
                $config_content = "<?php\n// Configuration MySQL pour l'application de chat\n\nreturn [\n    'database' => [\n        'host' => '$host',\n        'dbname' => '$dbname',\n        'username' => '$username',\n        'password' => '$password',\n        'charset' => 'utf8mb4',\n        'table_messages' => 'messages',\n        'table_users' => 'active_users'\n    ],\n    'limits' => [\n        'username_max_length' => 20,\n        'message_max_length' => 500,\n        'max_messages_history' => 50,\n        'user_timeout_minutes' => 5,\n        'message_cleanup_hours' => 24\n    ],\n    'polling' => [\n        'interval_seconds' => 2,\n        'timeout_seconds' => 30\n    ],\n    'ui' => [\n        'app_title' => 'Chat en Temps Réel',\n        'welcome_message' => 'Bienvenue dans le chat!',\n        'max_notification_time' => 3000,\n        'scroll_behavior' => 'smooth'\n    ],\n    'security' => [\n        'session_timeout_minutes' => 60,\n        'max_login_attempts' => 5,\n        'enable_html_escape' => true,\n        'allowed_username_chars' => '/^[a-zA-Z0-9_\-\sÀ-ÿ]+$/u'\n    ],\n    'features' => [\n        'typing_indicator' => true,\n        'user_list' => true,\n        'message_timestamps' => true,\n        'auto_cleanup' => true,\n        'sound_notifications' => false\n    ]\n];\n?>";
                
                file_put_contents(__DIR__ . '/config_mysql.php', $config_content);
                echo '<div class="status success">✅ Fichier de configuration mis à jour</div>';
                
                echo '<div class="status info">
                    <strong>🎉 Installation terminée avec succès !</strong><br>
                    Vous pouvez maintenant utiliser votre chat avec MySQL.
                </div>';
                
                echo '<a href="index.html" class="btn">🚀 Accéder au Chat</a>';
                echo '<a href="http://localhost/phpmyadmin" class="btn" target="_blank">📊 Ouvrir phpMyAdmin</a>';
                
            } catch (PDOException $e) {
                echo '<div class="status error">❌ Erreur de connexion : ' . $e->getMessage() . '</div>';
                echo '<div class="status warning">⚠️ Vérifiez que MySQL est démarré et que les paramètres sont corrects</div>';
            }
            
            echo '</div>';
        }
        ?>

        <div class="step">
            <h3>📚 Étapes manuelles alternatives</h3>
            <p>Si l'installation automatique ne fonctionne pas, vous pouvez :</p>
            <ol>
                <li><strong>Ouvrir phpMyAdmin</strong> : <a href="http://localhost/phpmyadmin" target="_blank" class="btn">Ouvrir phpMyAdmin</a></li>
                <li><strong>Créer une nouvelle base</strong> nommée <code>chat_app</code></li>
                <li><strong>Importer le fichier</strong> <code>database.sql</code> dans cette base</li>
                <li><strong>Modifier</strong> le fichier <code>api.php</code> pour qu'il utilise <code>api_mysql.php</code></li>
            </ol>
        </div>

        <div class="step">
            <h3>🔄 Migration depuis SQLite</h3>
            <p>Si vous avez des données existantes dans SQLite :</p>
            <a href="migrate_to_mysql.php" class="btn">🔄 Migrer les données</a>
        </div>
    </div>
</body>
</html>