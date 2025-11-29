// Extension pour gérer les messages vocaux
class VoiceMessageManager {
    constructor(chatApp) {
        this.chatApp = chatApp;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.startTime = null;
        this.currentAudio = null;
        
        this.initVoiceControls();
    }
    
    initVoiceControls() {
        console.log('Initialisation des contrôles vocaux...');
        
        const voiceBtn = document.getElementById('voice-btn');
        const micIcon = document.getElementById('mic-icon');
        const stopIcon = document.getElementById('stop-icon');
        
        console.log('Bouton vocal trouvé:', voiceBtn);
        console.log('Icône micro trouvée:', micIcon);
        console.log('Icône stop trouvée:', stopIcon);
        
        if (!voiceBtn) {
            console.error('Bouton vocal non trouvé!');
            return;
        }
        
        voiceBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Clic sur le bouton vocal, isRecording:', this.isRecording);
            
            if (this.isRecording) {
                this.stopRecording();
            } else {
                this.startRecording();
            }
        });
        
        console.log('Event listener ajouté au bouton vocal');
    }
    
    async startRecording() {
        console.log('Tentative de démarrage de l\'enregistrement...');
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.showNotification('Votre navigateur ne supporte pas l\'enregistrement audio', 'error');
            return;
        }
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                } 
            });
            
            console.log('Accès au microphone autorisé');
            
            // Vérifier les types MIME supportés
            let mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'audio/webm';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'audio/mp4';
                    if (!MediaRecorder.isTypeSupported(mimeType)) {
                        mimeType = undefined; // Utiliser le type par défaut
                    }
                }
            }
            
            console.log('Type MIME utilisé:', mimeType);
            
            this.mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
            
            this.audioChunks = [];
            this.startTime = Date.now();
            
            this.mediaRecorder.ondataavailable = (event) => {
                console.log('Données audio reçues:', event.data.size);
                this.audioChunks.push(event.data);
            };
            
            this.mediaRecorder.onstop = () => {
                console.log('Enregistrement arrêté');
                this.processRecording();
                stream.getTracks().forEach(track => track.stop());
            };
            
            this.mediaRecorder.onerror = (event) => {
                console.error('Erreur MediaRecorder:', event.error);
                this.showNotification('Erreur lors de l\'enregistrement', 'error');
            };
            
            this.mediaRecorder.start(1000); // Collecte des données chaque seconde
            this.updateRecordingUI(true);
            this.isRecording = true;
            
            console.log('Enregistrement démarré');
            
        } catch (error) {
            console.error('Erreur d\'accès au microphone:', error);
            if (error.name === 'NotAllowedError') {
                this.showNotification('Accès au microphone refusé. Veuillez autoriser l\'accès dans les paramètres du navigateur.', 'error');
            } else if (error.name === 'NotFoundError') {
                this.showNotification('Aucun microphone détecté sur votre appareil.', 'error');
            } else {
                this.showNotification('Erreur d\'accès au microphone: ' + error.message, 'error');
            }
        }
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.stop();
        }
        this.updateRecordingUI(false);
        this.isRecording = false;
    }
    
    updateRecordingUI(recording) {
        const voiceBtn = document.getElementById('voice-btn');
        const micIcon = document.getElementById('mic-icon');
        const stopIcon = document.getElementById('stop-icon');
        
        if (recording) {
            voiceBtn.classList.add('recording');
            micIcon.style.display = 'none';
            stopIcon.style.display = 'block';
            voiceBtn.title = 'Arrêter l\'enregistrement';
        } else {
            voiceBtn.classList.remove('recording');
            micIcon.style.display = 'block';
            stopIcon.style.display = 'none';
            voiceBtn.title = 'Enregistrer un message vocal';
        }
    }
    
    processRecording() {
        if (this.audioChunks.length === 0) return;
        
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const duration = Math.round((Date.now() - this.startTime) / 1000);
        
        // Vérifier la durée minimale (1 seconde)
        if (duration < 1) {
            this.showNotification('Message trop court (minimum 1 seconde)', 'warning');
            return;
        }
        
        // Vérifier la durée maximale (5 minutes)
        if (duration > 300) {
            this.showNotification('Message trop long (maximum 5 minutes)', 'warning');
            return;
        }
        
        this.sendVoiceMessage(audioBlob, duration);
    }
    
    async sendVoiceMessage(audioBlob, duration) {
        console.log('Envoi du message vocal...', { size: audioBlob.size, duration });
        
        // Récupérer les informations de session depuis l'instance ChatApp
        const chatApp = window.chatApp;
        if (!chatApp || !chatApp.username) {
            console.error('Pas d\'utilisateur connecté pour envoyer un message vocal');
            this.showNotification('Vous devez être connecté pour envoyer un message vocal', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('is_audio', '1');
        formData.append('duration', duration.toString());
        formData.append('audio', audioBlob, 'voice_message.webm');
        formData.append('client_id', chatApp.clientId);
        formData.append('username', chatApp.username);
        
        // Debug FormData
        for (let pair of formData.entries()) {
            console.log('FormData:', pair[0], typeof pair[1] === 'object' ? 'File(' + pair[1].size + ' bytes)' : pair[1]);
        }
        
        try {
            // Utiliser XMLHttpRequest pour un meilleur contrôle de l'upload
            const xhr = new XMLHttpRequest();
            
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = (event.loaded / event.total) * 100;
                    console.log('Upload progress:', percent.toFixed(2) + '%');
                }
            };
            
            xhr.onload = () => {
                console.log('Réponse HTTP:', xhr.status, xhr.statusText);
                console.log('Réponse brute:', xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const result = JSON.parse(xhr.responseText);
                        console.log('Résultat:', result);
                        
                        if (result.success) {
                            this.showNotification('Message vocal envoyé!', 'success');
                            // Recharger les messages pour voir le nouveau message vocal
                            if (window.chatApp) {
                                window.chatApp.loadMessages();
                            }
                        } else {
                            this.showNotification('Erreur: ' + result.message, 'error');
                            // Gérer les erreurs de session
                            if (result.message && (result.message.includes('expirée') || result.message.includes('reconnecter'))) {
                                console.warn('Session expirée détectée lors de l\'envoi vocal');
                                if (window.chatApp) {
                                    window.chatApp.handleSessionExpired();
                                }
                            }
                        }
                    } catch (parseError) {
                        console.error('Erreur parsing JSON:', parseError);
                        this.showNotification('Erreur serveur: réponse invalide', 'error');
                    }
                } else {
                    this.showNotification('Erreur HTTP: ' + xhr.status, 'error');
                }
            };
            
            xhr.onerror = () => {
                console.error('Erreur réseau');
                this.showNotification('Erreur réseau lors de l\'envoi', 'error');
            };
            
            xhr.open('POST', 'api.php?action=send_message', true);
            xhr.send(formData);
            
        } catch (error) {
            console.error('Erreur envoi message vocal:', error);
            this.showNotification('Erreur lors de l\'envoi: ' + error.message, 'error');
        }
    }
    
    createAudioMessageElement(message) {
        const audioDiv = document.createElement('div');
        audioDiv.className = 'audio-message';
        
        const audioUrl = `api.php?action=get_audio&file=${message.audio_path}`;
        const duration = this.formatDuration(message.audio_duration || 0);
        
        audioDiv.innerHTML = `
            <div class="audio-controls">
                <button class="play-btn" onclick="voiceManager.toggleAudio('${audioUrl}', this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>
                    </svg>
                </button>
                <div class="audio-waveform" onclick="voiceManager.toggleAudio('${audioUrl}', this.previousElementSibling)">
                    <div class="audio-progress"></div>
                </div>
                <span class="audio-duration">${duration}</span>
            </div>
        `;
        
        return audioDiv;
    }
    
    async toggleAudio(url, button) {
        // Arrêter l'audio actuel si il y en a un
        if (this.currentAudio && !this.currentAudio.paused) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
            this.updateAllPlayButtons(false);
        }
        
        // Si c'est le même bouton, ne pas relancer
        if (this.currentAudio && this.currentAudio.src.includes(url.split('file=')[1])) {
            this.currentAudio = null;
            return;
        }
        
        try {
            this.currentAudio = new Audio(url);
            
            this.currentAudio.onloadstart = () => {
                this.updatePlayButton(button, 'loading');
            };
            
            this.currentAudio.oncanplay = () => {
                this.updatePlayButton(button, 'playing');
                this.currentAudio.play();
            };
            
            this.currentAudio.ontimeupdate = () => {
                this.updateProgress(button);
            };
            
            this.currentAudio.onended = () => {
                this.updatePlayButton(button, 'stopped');
                this.updateProgress(button, 0);
                this.currentAudio = null;
            };
            
            this.currentAudio.onerror = () => {
                this.updatePlayButton(button, 'error');
                this.showNotification('Erreur de lecture audio', 'error');
            };
            
        } catch (error) {
            console.error('Erreur lecture audio:', error);
            this.showNotification('Impossible de lire le fichier audio', 'error');
        }
    }
    
    updatePlayButton(button, state) {
        const svg = button.querySelector('svg');
        
        switch (state) {
            case 'playing':
                svg.innerHTML = '<rect x="6" y="4" width="4" height="16" fill="currentColor"/><rect x="14" y="4" width="4" height="16" fill="currentColor"/>';
                break;
            case 'loading':
                svg.innerHTML = '<circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2" fill="none"/>';
                break;
            case 'error':
            case 'stopped':
                svg.innerHTML = '<path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>';
                break;
        }
    }
    
    updateProgress(button, progress = null) {
        const audioControls = button.closest('.audio-controls');
        const progressBar = audioControls.querySelector('.audio-progress');
        
        if (progress !== null) {
            progressBar.style.width = progress + '%';
        } else if (this.currentAudio) {
            const percent = (this.currentAudio.currentTime / this.currentAudio.duration) * 100;
            progressBar.style.width = percent + '%';
        }
    }
    
    updateAllPlayButtons(playing) {
        const buttons = document.querySelectorAll('.play-btn');
        buttons.forEach(btn => {
            this.updatePlayButton(btn, playing ? 'playing' : 'stopped');
        });
    }
    
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    showNotification(message, type = 'info') {
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // Utiliser le système de notification existant via window.chatApp
        if (window.chatApp && window.chatApp.showNotification) {
            window.chatApp.showNotification(message, type);
        } else {
            // Fallback: notification simple
            const notification = document.getElementById('notification');
            if (notification) {
                notification.textContent = message;
                notification.style.display = 'block';
                notification.className = `notification ${type}`;
                
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000);
            } else {
                alert(`[${type.toUpperCase()}] ${message}`);
            }
        }
    }
}

// Initialisation globale
let voiceManager = null;

// Initialiser immédiatement
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVoiceManager);
} else {
    initVoiceManager();
}

function initVoiceManager() {
    console.log('Initialisation du gestionnaire vocal...');
    voiceManager = new VoiceMessageManager(null);
    console.log('Gestionnaire vocal initialisé:', voiceManager);
}