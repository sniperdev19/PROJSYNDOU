-- Script SQL pour ajouter les colonnes media_type et media_path à la table messages
-- Exécutez ce script sur votre base de données pour activer la fonctionnalité d'envoi de médias

USE chat_db;

-- Ajouter la colonne media_type (image, video, ou NULL pour texte/audio)
ALTER TABLE messages 
ADD COLUMN media_type VARCHAR(10) NULL AFTER audio_duration,
ADD INDEX idx_media_type (media_type);

-- Ajouter la colonne media_path pour stocker le nom du fichier
ALTER TABLE messages 
ADD COLUMN media_path VARCHAR(255) NULL AFTER media_type,
ADD INDEX idx_media_path (media_path);

-- Vérifier les modifications
DESCRIBE messages;

-- Afficher un message de confirmation
SELECT 'Colonnes media_type et media_path ajoutées avec succès!' AS Status;
