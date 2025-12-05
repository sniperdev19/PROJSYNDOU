<?php
/**
 * Diagnostic et Configuration PHP pour l'Upload de Vidéos
 * Ce script vérifie et affiche les paramètres PHP nécessaires pour l'upload de médias
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Upload Vidéos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .code-block {
            background: #2d3748;
            color: #fff;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
        }
        .recommendation {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
            margin: 15px 0;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Upload de Vidéos</h1>
        
        <?php
        // Récupérer les paramètres PHP importants
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size = ini_get('post_max_size');
        $max_execution_time = ini_get('max_execution_time');
        $max_input_time = ini_get('max_input_time');
        $memory_limit = ini_get('memory_limit');
        $file_uploads = ini_get('file_uploads');
        
        // Convertir en bytes pour comparaison
        function parseSize($size) {
            $unit = strtoupper(substr($size, -1));
            $value = (int) $size;
            switch($unit) {
                case 'G': return $value * 1024 * 1024 * 1024;
                case 'M': return $value * 1024 * 1024;
                case 'K': return $value * 1024;
                default: return $value;
            }
        }
        
        $upload_bytes = parseSize($upload_max_filesize);
        $post_bytes = parseSize($post_max_size);
        $required_bytes = 25 * 1024 * 1024; // 25MB recommandé pour 20MB de vidéo
        
        $upload_ok = $upload_bytes >= $required_bytes;
        $post_ok = $post_bytes >= $required_bytes;
        $time_ok = $max_execution_time >= 60 || $max_execution_time == 0;
        $uploads_enabled = $file_uploads == 1;
        
        $all_ok = $upload_ok && $post_ok && $time_ok && $uploads_enabled;
        ?>
        
        <?php if ($all_ok): ?>
            <div class="status-ok">
                <h3>✅ Configuration PHP : PARFAITE</h3>
                <p>Tous les paramètres sont correctement configurés pour l'upload de vidéos jusqu'à 20MB.</p>
            </div>
        <?php else: ?>
            <div class="status-error">
                <h3>⚠️ Configuration PHP : PROBLÈMES DÉTECTÉS</h3>
                <p>Certains paramètres doivent être ajustés pour permettre l'upload de vidéos.</p>
            </div>
        <?php endif; ?>
        
        <h2>📊 Paramètres PHP Actuels</h2>
        <table>
            <thead>
                <tr>
                    <th>Paramètre</th>
                    <th>Valeur Actuelle</th>
                    <th>Recommandé</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>file_uploads</strong></td>
                    <td><?= $file_uploads ? 'Activé' : 'Désactivé' ?></td>
                    <td>Activé</td>
                    <td><?= $uploads_enabled ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td><strong>upload_max_filesize</strong></td>
                    <td><?= $upload_max_filesize ?></td>
                    <td>≥ 25M</td>
                    <td><?= $upload_ok ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td><strong>post_max_size</strong></td>
                    <td><?= $post_max_size ?></td>
                    <td>≥ 25M</td>
                    <td><?= $post_ok ? '✅' : '❌' ?></td>
                </tr>
                <tr>
                    <td><strong>max_execution_time</strong></td>
                    <td><?= $max_execution_time ?> secondes</td>
                    <td>≥ 60s</td>
                    <td><?= $time_ok ? '✅' : '⚠️' ?></td>
                </tr>
                <tr>
                    <td><strong>max_input_time</strong></td>
                    <td><?= $max_input_time ?> secondes</td>
                    <td>≥ 60s</td>
                    <td>ℹ️</td>
                </tr>
                <tr>
                    <td><strong>memory_limit</strong></td>
                    <td><?= $memory_limit ?></td>
                    <td>≥ 128M</td>
                    <td>ℹ️</td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!$all_ok): ?>
            <h2>🔧 Comment Corriger</h2>
            
            <div class="recommendation">
                <h3>Pour WAMP (Windows)</h3>
                <p><strong>Étape 1 :</strong> Cliquez sur l'icône WAMP dans la barre des tâches</p>
                <p><strong>Étape 2 :</strong> PHP → php.ini</p>
                <p><strong>Étape 3 :</strong> Recherchez et modifiez les lignes suivantes :</p>
            </div>
            
            <div class="code-block">
; Cherchez ces lignes dans php.ini et modifiez-les :

upload_max_filesize = 25M
post_max_size = 30M
max_execution_time = 120
max_input_time = 120
memory_limit = 256M
            </div>
            
            <div class="status-warning">
                <p><strong>⚠️ Important :</strong> Après avoir modifié php.ini :</p>
                <ol>
                    <li>Sauvegardez le fichier</li>
                    <li>Redémarrez WAMP (Icône WAMP → Redémarrer tous les services)</li>
                    <li>Rechargez cette page pour vérifier</li>
                </ol>
            </div>
            
            <div class="recommendation">
                <h3>Fichier php.ini</h3>
                <p><strong>Emplacement :</strong> <?= php_ini_loaded_file() ?></p>
                <button onclick="window.open('<?= php_ini_loaded_file() ?>', '_blank')">Ouvrir php.ini</button>
            </div>
        <?php endif; ?>
        
        <h2>🧪 Test d'Upload</h2>
        <div class="test-section">
            <p>Testez l'upload d'une vidéo directement ici :</p>
            <form id="test-form" enctype="multipart/form-data">
                <input type="file" id="test-file" accept="video/*" style="margin: 10px 0;">
                <br>
                <button type="button" onclick="testUpload()">Tester l'Upload</button>
            </form>
            <div id="test-result" style="margin-top: 15px;"></div>
        </div>
        
        <h2>📁 Vérification des Dossiers</h2>
        <?php
        $directories = [
            'uploads' => 'uploads',
            'uploads/images' => 'uploads/images',
            'uploads/videos' => 'uploads/videos',
            'uploads/audio' => 'uploads/audio'
        ];
        
        echo '<table>';
        echo '<tr><th>Dossier</th><th>Existe</th><th>Permissions</th><th>Statut</th></tr>';
        
        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists ? is_writable($path) : false;
            $status = $exists && $writable ? '✅' : '❌';
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($name) . '</td>';
            echo '<td>' . ($exists ? 'Oui' : 'Non') . '</td>';
            echo '<td>' . ($writable ? 'Lecture/Écriture' : ($exists ? 'Lecture seule' : 'N/A')) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
            
            // Créer le dossier s'il n'existe pas
            if (!$exists) {
                @mkdir($path, 0755, true);
            }
        }
        
        echo '</table>';
        ?>
        
        <h2>📋 Codes d'Erreur PHP Upload</h2>
        <table>
            <tr><th>Code</th><th>Signification</th><th>Solution</th></tr>
            <tr>
                <td>0 (UPLOAD_ERR_OK)</td>
                <td>Aucune erreur, upload réussi</td>
                <td>✅ Tout va bien</td>
            </tr>
            <tr>
                <td>1 (UPLOAD_ERR_INI_SIZE)</td>
                <td>Fichier trop gros (upload_max_filesize)</td>
                <td>Augmenter upload_max_filesize dans php.ini</td>
            </tr>
            <tr>
                <td>2 (UPLOAD_ERR_FORM_SIZE)</td>
                <td>Fichier trop gros (MAX_FILE_SIZE dans le formulaire)</td>
                <td>Vérifier le formulaire HTML</td>
            </tr>
            <tr>
                <td>3 (UPLOAD_ERR_PARTIAL)</td>
                <td>Fichier partiellement uploadé</td>
                <td>Réessayer, vérifier la connexion</td>
            </tr>
            <tr>
                <td>4 (UPLOAD_ERR_NO_FILE)</td>
                <td>Aucun fichier uploadé</td>
                <td>Sélectionner un fichier</td>
            </tr>
            <tr>
                <td>6 (UPLOAD_ERR_NO_TMP_DIR)</td>
                <td>Dossier temporaire manquant</td>
                <td>Vérifier la configuration du serveur</td>
            </tr>
            <tr>
                <td>7 (UPLOAD_ERR_CANT_WRITE)</td>
                <td>Impossible d'écrire sur le disque</td>
                <td>Vérifier les permissions</td>
            </tr>
        </table>
        
        <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 5px;">
            <h3>✅ Checklist Finale</h3>
            <ul>
                <li><?= $uploads_enabled ? '✅' : '❌' ?> file_uploads activé</li>
                <li><?= $upload_ok ? '✅' : '❌' ?> upload_max_filesize ≥ 25M</li>
                <li><?= $post_ok ? '✅' : '❌' ?> post_max_size ≥ 25M</li>
                <li><?= $time_ok ? '✅' : '⚠️' ?> max_execution_time ≥ 60s</li>
                <li><?= is_dir('uploads/videos') && is_writable('uploads/videos') ? '✅' : '❌' ?> Dossier uploads/videos accessible</li>
            </ul>
        </div>
    </div>
    
    <script>
        async function testUpload() {
            const fileInput = document.getElementById('test-file');
            const resultDiv = document.getElementById('test-result');
            
            if (!fileInput.files || !fileInput.files[0]) {
                resultDiv.innerHTML = '<div class="status-warning">⚠️ Veuillez sélectionner une vidéo</div>';
                return;
            }
            
            const file = fileInput.files[0];
            const maxSize = 20 * 1024 * 1024; // 20MB
            
            if (file.size > maxSize) {
                resultDiv.innerHTML = '<div class="status-error">❌ Fichier trop gros : ' + 
                    (file.size / 1024 / 1024).toFixed(2) + ' MB (max: 20MB)</div>';
                return;
            }
            
            resultDiv.innerHTML = '<div class="status-warning">⏳ Upload en cours... (' + 
                (file.size / 1024 / 1024).toFixed(2) + ' MB)</div>';
            
            const formData = new FormData();
            formData.append('media', file);
            formData.append('caption', 'Test depuis le diagnostic');
            
            try {
                const response = await fetch('api.php?action=send_media', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="status-ok">✅ Upload réussi ! ID: ' + 
                        data.data.id + '<br>Votre vidéo a été uploadée correctement.</div>';
                } else {
                    resultDiv.innerHTML = '<div class="status-error">❌ Erreur : ' + 
                        data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="status-error">❌ Erreur réseau : ' + 
                    error.message + '</div>';
            }
        }
    </script>
</body>
</html>
