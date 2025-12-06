-- Ajouter un UNIQUE INDEX pour empêcher les doublons
-- Si deux requêtes tentent d'insérer le même message en même temps, la deuxième sera rejetée

ALTER TABLE messages ADD UNIQUE KEY `unique_message_send` (username, message, timestamp);

-- Alternative: si vous voulez garder les 2 premières secondes comme "même moment"
-- ALTER TABLE messages ADD UNIQUE KEY `unique_message_send` (username, message, DATE(timestamp));
