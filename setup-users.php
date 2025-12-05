<?php
/**
 * Script d'installation pour la table des utilisateurs
 * À exécuter une fois pour créer la table users dans la base de données
 */

// Charger la configuration
$config = include __DIR__ . '/config.php';
$db_config = $config['database'];

try {
    // Connexion à la base de données
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Installation de la table des utilisateurs</h2>";
    
    // Vérifier si la table existe déjà
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: orange;'>⚠️ La table 'users' existe déjà.</p>";
        echo "<p>Voulez-vous la recréer ? (Attention : cela supprimera toutes les données existantes)</p>";
        echo "<form method='post'>";
        echo "<button type='submit' name='recreate' value='yes' style='background: #d9534f; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Oui, recréer la table</button> ";
        echo "<button type='submit' name='cancel' value='yes' style='background: #5bc0de; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Non, annuler</button>";
        echo "</form>";
        
        if (isset($_POST['recreate'])) {
            $pdo->exec("DROP TABLE IF EXISTS users");
            echo "<p style='color: blue;'>✅ Ancienne table supprimée.</p>";
            $tableExists = false;
        } elseif (isset($_POST['cancel'])) {
            echo "<p style='color: green;'>✅ Installation annulée. La table existante n'a pas été modifiée.</p>";
            exit;
        } else {
            exit;
        }
    }
    
    if (!$tableExists) {
        // Créer la table users
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Table 'users' créée avec succès!</p>";
        
        // Afficher la structure de la table
        echo "<h3>Structure de la table :</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
        
        $stmt = $pdo->query("DESCRIBE users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>✅ Installation terminée avec succès!</h3>";
        echo "<p>Vous pouvez maintenant utiliser le système d'inscription et de connexion.</p>";
        echo "<p><a href='index.html' style='background: #5cb85c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>Accéder au Chat</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
    echo "<p>Assurez-vous que :</p>";
    echo "<ul>";
    echo "<li>Le serveur MySQL est démarré</li>";
    echo "<li>La base de données '{$db_config['dbname']}' existe</li>";
    echo "<li>Les identifiants de connexion dans config.php sont corrects</li>";
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Table Users</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2 {
            color: #333;
        }
        table {
            background: white;
            width: 100%;
        }
        th {
            background: #3182ce;
            color: white;
        }
    </style>
</head>
<body>
</body>
</html>
