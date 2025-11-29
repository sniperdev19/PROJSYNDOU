<?php
// Script pour récupérer les données brutes des utilisateurs actifs
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $config = include __DIR__ . '/config.php';
    $db_config = $config['database'];
    
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer TOUS les utilisateurs de la base, même les anciens
    $stmt = $pdo->prepare("SELECT username, session_id, last_activity, 
                          TIMESTAMPDIFF(SECOND, last_activity, NOW()) as seconds_ago
                          FROM active_users 
                          ORDER BY last_activity DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques
    $totalUsers = count($users);
    $activeUsers = 0;
    $uniqueUsernames = [];
    
    foreach ($users as $user) {
        if ($user['seconds_ago'] < 300) { // 5 minutes
            $activeUsers++;
        }
        $baseName = preg_replace('/_\d+$/', '', $user['username']);
        $uniqueUsernames[$baseName] = true;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'stats' => [
            'total_entries' => $totalUsers,
            'active_users' => $activeUsers,
            'unique_usernames' => count($uniqueUsernames)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>