<?php
/**
 * Script de vérification et d'ajout automatique des colonnes media_type et media_path
 * Exécutez ce fichier une fois via votre navigateur pour mettre à jour la base de données
 */

// Configuration de la base de données
$config = include __DIR__ . '/config.php';
$db_config = $config['database'];

try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Vérification de la structure de la base de données</h2>";
    
    // Vérifier la structure actuelle de la table messages
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Colonnes actuelles :</h3><pre>";
    $columnNames = array_column($columns, 'Field');
    print_r($columnNames);
    echo "</pre>";
    
    $hasMediaType = in_array('media_type', $columnNames);
    $hasMediaPath = in_array('media_path', $columnNames);
    
    // Ajouter media_type si elle n'existe pas
    if (!$hasMediaType) {
        echo "<p style='color: orange;'>⚠️ Colonne 'media_type' manquante. Ajout en cours...</p>";
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_type VARCHAR(10) NULL AFTER audio_duration");
        $pdo->exec("ALTER TABLE messages ADD INDEX idx_media_type (media_type)");
        echo "<p style='color: green;'>✅ Colonne 'media_type' ajoutée avec succès!</p>";
    } else {
        echo "<p style='color: green;'>✅ Colonne 'media_type' existe déjà.</p>";
    }
    
    // Ajouter media_path si elle n'existe pas
    if (!$hasMediaPath) {
        echo "<p style='color: orange;'>⚠️ Colonne 'media_path' manquante. Ajout en cours...</p>";
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_path VARCHAR(255) NULL AFTER media_type");
        $pdo->exec("ALTER TABLE messages ADD INDEX idx_media_path (media_path)");
        echo "<p style='color: green;'>✅ Colonne 'media_path' ajoutée avec succès!</p>";
    } else {
        echo "<p style='color: green;'>✅ Colonne 'media_path' existe déjà.</p>";
    }
    
    // Afficher la structure finale
    echo "<h3>Structure finale de la table messages :</h3>";
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($hasMediaType && $hasMediaPath) {
        echo "<h2 style='color: green;'>✅ Tout est prêt ! La fonctionnalité d'envoi de médias est opérationnelle.</h2>";
        echo "<p>Vous pouvez maintenant envoyer des images et vidéos dans le chat.</p>";
    } else {
        echo "<h2 style='color: green;'>✅ Mise à jour terminée ! Rechargez votre page de chat pour utiliser la fonctionnalité.</h2>";
    }
    
    // Vérifier les dossiers d'upload
    echo "<h3>Vérification des dossiers d'upload :</h3>";
    $directories = ['uploads/images', 'uploads/videos', 'uploads/audio'];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "<p style='color: green;'>✅ $dir existe</p>";
            if (is_writable($dir)) {
                echo "<p style='color: green;'>  → Permissions d'écriture : OK</p>";
            } else {
                echo "<p style='color: red;'>  → ⚠️ Pas de permissions d'écriture ! Exécutez : chmod 755 $dir</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ $dir n'existe pas. Création...</p>";
            if (mkdir($dir, 0755, true)) {
                echo "<p style='color: green;'>  → Créé avec succès</p>";
            } else {
                echo "<p style='color: red;'>  → Échec de la création</p>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Erreur de base de données</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><em>Vous pouvez fermer cette page et retourner au chat.</em></p>";
?>
