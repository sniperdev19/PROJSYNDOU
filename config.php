<?php
// Configuration MySQL pour l'application de chat

return [
    // Base de données MySQL
    'database' => [
        'host' =>'localhost',
        'dbname' => 'chat_app',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'table_messages' => 'messages',
        'table_users' => 'active_users'
    ],
    
    // Limites
    'limits' => [
        'username_max_length' => 20,
        'message_max_length' => 500,
        'max_messages_history' => 50,
        'user_timeout_minutes' => 5,
        'message_cleanup_hours' => 24,
        'guest_message_limit' => 10, // Limite de messages pour les invités
        'warning_at_message' => 7 // Avertissement à partir de ce nombre
    ],
    
    // Paramètres de polling
    'polling' => [
        'interval_seconds' => 2,
        'timeout_seconds' => 30
    ],
    
    // Interface
    'ui' => [
        'app_title' => 'Chat en Temps Réel',
        'welcome_message' => 'Bienvenue dans le chat!',
        'max_notification_time' => 3000, // millisecondes
        'scroll_behavior' => 'smooth'
    ],
    
    // Sécurité
    'security' => [
        'session_timeout_minutes' => 60,
        'max_login_attempts' => 5,
        'enable_html_escape' => true,
        'allowed_username_chars' => '/^[a-zA-Z0-9_\-\sÀ-ÿ]+$/u'
    ],
    
    // Fonctionnalités
    'features' => [
        'typing_indicator' => true,
        'user_list' => true,
        'message_timestamps' => true,
        'auto_cleanup' => true,
        'sound_notifications' => false
    ]
];
?>