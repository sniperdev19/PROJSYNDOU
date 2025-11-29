class ChatApp {
    constructor() {
        this.username = '';
        this.lastMessageId = 0;
        this.typingTimer = null;
        this.pollInterval = null;
        this.isTyping = false;
        this.isPageVisible = true;
        
        // Générer un ID client unique pour cette instance
        this.clientId = this.getOrCreateClientId();
        console.log('🆔 Client ID:', this.clientId);
        
        this.init();
        this.initVisibilityHandler();
    }
    
    getOrCreateClientId() {
        // Générer un ID vraiment unique pour chaque instance/fenêtre
        const instanceId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 12) + '_' + performance.now().toString().replace('.', '');
        
        // Stocker temporairement dans sessionStorage plutôt que localStorage 
        // pour que chaque onglet/fenêtre ait son propre ID
        let clientId = sessionStorage.getItem('chat_instance_id');
        if (!clientId) {
            clientId = instanceId;
            sessionStorage.setItem('chat_instance_id', clientId);
            console.log('🆕 Nouvel ID d\'instance généré:', clientId);
        }
        return clientId;
    }

    init() {
        this.bindEvents();
        this.checkSession();
    }

    bindEvents() {
        // Formulaire de connexion
        document.getElementById('username-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });

        // Formulaire de message
        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });

        // Déconnexion
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.logout();
        });

        // Détection de frappe
        document.getElementById('message-input').addEventListener('input', () => {
            this.handleTyping();
        });

        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else {
                this.startPolling();
            }
        });
    }

    async checkSession() {
        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            
            const response = await fetch('api.php?action=check_session', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            console.log('Vérification session:', data);
            
            if (data.success && data.data && data.data.username) {
                this.username = data.data.username.trim(); // Nettoyer le nom
                console.log('Session restored for user:', `"${this.username}"`);
                
                // Mettre à jour l'affichage du nom
                const usernameDisplay = document.getElementById('current-username');
                if (usernameDisplay) {
                    usernameDisplay.textContent = this.username;
                }
                
                this.showChat();
                this.loadMessages();
                this.loadActiveUsers();
                // Ne démarrer le polling que si ce n'est pas déjà en cours
                if (!this.pollInterval) {
                    this.startPolling();
                }
            } else if (data.success && data.username) {
                // Fallback pour l'ancien format
                this.username = data.username.trim();
                console.log('Session restored (fallback) for user:', `"${this.username}"`);
                
                // Mettre à jour l'affichage du nom
                const usernameDisplay = document.getElementById('current-username');
                if (usernameDisplay) {
                    usernameDisplay.textContent = this.username;
                }
                
                this.showChat();
                this.loadMessages();
                this.loadActiveUsers();
                // Ne démarrer le polling que si ce n'est pas déjà en cours
                if (!this.pollInterval) {
                    this.startPolling();
                }
            } else {
                console.log('No active session found');
                this.showLogin();
            }
        } catch (error) {
            console.error('Erreur lors de la vérification de session:', error);
            this.showLogin();
        }
    }

    async login() {
        const usernameInput = document.getElementById('username-input');
        const username = usernameInput.value.trim();

        if (!username) {
            this.showNotification('Veuillez entrer un nom d\'utilisateur', 'error');
            return;
        }

        if (username.length > 20) {
            this.showNotification('Le nom d\'utilisateur ne peut pas dépasser 20 caractères', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            formData.append('username', username);
            
            const response = await fetch('api.php?action=login', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Utiliser le nom renvoyé par le serveur (peut être différent de celui saisi)
                this.username = (data.data?.username || username).trim();
                console.log('Connexion réussie, nom d\'utilisateur:', `"${this.username}"`);
                console.log('Session ID:', data.data?.session_id);
                
                // Mettre à jour l'affichage
                const usernameDisplay = document.getElementById('current-username');
                if (usernameDisplay) {
                    usernameDisplay.textContent = this.username;
                }
                
                // Mettre à jour le champ input si le nom a changé
                if (this.username !== username) {
                    const usernameInput = document.getElementById('username-input');
                    usernameInput.value = this.username;
                }
                
                this.showChat();
                this.loadMessages();
                this.loadActiveUsers();
                this.startPolling();
                
                // Afficher un message approprié
                const message = data.message || 'Connexion réussie!';
                const notificationType = this.username !== username ? 'warning' : 'success';
                this.showNotification(message, notificationType);
            } else {
                this.showNotification(data.message || 'Erreur de connexion', 'error');
            }
        } catch (error) {
            console.error('Erreur lors de la connexion:', error);
            this.showNotification('Erreur de connexion', 'error');
        }
    }

    async logout() {
        // Éviter les déconnexions multiples
        if (!this.username) {
            return;
        }
        
        try {
            console.log('Logging out user:', this.username);
            
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            
            await fetch('api.php?action=logout', { 
                method: 'POST',
                body: formData
            });
            this.stopPolling();
            this.clearMessages();
            this.username = '';
            this.lastMessageId = 0;
            this.showLogin();
            this.showNotification('Déconnexion réussie', 'success');
        } catch (error) {
            console.error('Erreur lors de la déconnexion:', error);
        }
    }

    async sendMessage() {
        const messageInput = document.getElementById('message-input');
        const message = messageInput.value.trim();

        if (!message) {
            return;
        }

        if (message.length > 500) {
            this.showNotification('Le message ne peut pas dépasser 500 caractères', 'error');
            return;
        }

        // Vérifier que l'utilisateur est toujours connecté
        if (!this.username) {
            this.showNotification('Vous devez être connecté pour envoyer un message', 'error');
            this.showLogin();
            return;
        }

        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            formData.append('username', this.username);
            formData.append('message', message);
            
            const response = await fetch('api.php?action=send_message', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                messageInput.value = '';
                console.log('Message envoyé avec succès, ID:', data.data?.id);
                // Recharger immédiatement les messages pour voir le nouveau message
                this.loadMessages();
            } else {
                console.error('Échec envoi message - Détails complets:', {
                    success: data.success,
                    message: data.message,
                    data: data.data,
                    username: this.username,
                    clientId: this.clientId
                });
                // Gérer les erreurs d'authentification
                if (data.message && (data.message.includes('expirée') || data.message.includes('reconnecter'))) {
                    console.warn('Session expirée détectée lors de l\'envoi');
                    this.handleSessionExpired();
                } else {
                    this.showNotification(data.message || 'Erreur lors de l\'envoi', 'error');
                }
            }
        } catch (error) {
            console.error('Erreur lors de l\'envoi du message:', error);
            this.showNotification('Erreur lors de l\'envoi', 'error');
        }
    }

    handleSessionExpired() {
        console.warn('⚠️ Session expirée, retour à la page de connexion');
        this.showNotification('Session expirée. Reconnexion nécessaire.', 'warning');
        this.stopPolling();
        this.username = '';
        this.lastMessageId = 0;
        this.showLogin();
    }

    async loadMessages() {
        // Vérifier si l'utilisateur est connecté avant de charger les messages
        if (!this.username) {
            console.warn('Pas de nom d\'utilisateur - arrêt du polling');
            this.stopPolling();
            this.showLogin();
            return;
        }
        
        try {
            this.updateConnectionStatus('🟡'); // Statut: en cours de chargement
            const response = await fetch(`api.php?action=get_messages&last_id=${this.lastMessageId}`);
            const data = await response.json();

            if (data.success && data.data && data.data.messages && data.data.messages.length > 0) {
                const newMessages = data.data.messages;
                const wasAtBottom = this.isScrollAtBottom();
                
                // Filtrer les messages déjà affichés pour éviter les doublons
                const uniqueMessages = newMessages.filter(message => {
                    const exists = document.querySelector(`[data-message-id="${message.id}"]`);
                    return !exists;
                });
                
                console.log(`Nouveaux messages uniques: ${uniqueMessages.length}/${newMessages.length}`);

                uniqueMessages.forEach(message => {
                    this.displayMessage(message);
                    if (message.id > this.lastMessageId) {
                        this.lastMessageId = message.id;
                    }
                });

                // Auto-scroll si l'utilisateur était déjà en bas
                if (wasAtBottom && uniqueMessages.length > 0) {
                    this.scrollToBottom();
                } else if (uniqueMessages.some(msg => msg.username !== this.username)) {
                    // Afficher notification pour les nouveaux messages des autres
                    const newFromOthers = uniqueMessages.filter(msg => msg.username !== this.username);
                    if (newFromOthers.length === 1) {
                        this.showNotification(`Nouveau message de ${newFromOthers[0].username}`, 'info');
                    } else if (newFromOthers.length > 1) {
                        this.showNotification(`${newFromOthers.length} nouveaux messages`, 'info');
                    }
                }
            }
            this.updateConnectionStatus('🟢'); // Statut: connecté
        } catch (error) {
            console.error('Erreur lors du chargement des messages:', error);
            this.updateConnectionStatus('🔴'); // Statut: erreur
            
            // Vérifier si c'est un problème de session
            if (error.message && (error.message.includes('authentifié') || error.message.includes('session'))) {
                this.handleSessionExpired();
            }
        }
    }

    displayMessage(message) {
        const messagesContainer = document.getElementById('messages');
        const messageElement = document.createElement('div');
        
        // Comparaison normalisée (insensible à la casse et aux espaces)
        const normalizeUsername = (username) => username?.trim().toLowerCase() || '';
        const messageUser = normalizeUsername(message.username);
        const currentUser = normalizeUsername(this.username);
        const isOwnMessage = messageUser === currentUser;
        
        // Vérifier que this.username est défini
        if (!this.username) {
            console.warn('this.username n\'est pas défini ! Vérification de session...');
            this.checkSession();
            return; // Ne pas afficher le message si pas de session
        }
        
        // Debug détaillé pour tous les messages
        console.log('Message debug:', {
            messageUsername: `"${message.username}"`,
            currentUsername: `"${this.username}"`,
            messageUserNormalized: `"${messageUser}"`,
            currentUserNormalized: `"${currentUser}"`,
            isOwnMessage: isOwnMessage,
            messageId: message.id,
            comparison: messageUser === currentUser ? '✅ EGAL' : '❌ DIFFÉRENT'
        });
        
        messageElement.className = `message ${isOwnMessage ? 'own' : 'other'}`;
        messageElement.setAttribute('data-username', message.username);
        messageElement.setAttribute('data-current-user', this.username);
        messageElement.setAttribute('data-message-id', message.id);
        
        // Créer le header du message
        const headerHtml = `
            <div class="message-header">
                <span class="username">${this.escapeHtml(message.username)}</span>
                <span class="timestamp">${this.formatTime(message.timestamp)}</span>
            </div>
        `;
        
        // Créer le contenu selon le type de message
        let contentHtml;
        if (message.is_audio && message.is_audio == 1) {
            // Message vocal
            const duration = this.formatDuration(message.audio_duration || 0);
            const audioUrl = `api.php?action=get_audio&file=${message.audio_path}`;
            
            contentHtml = `
                <div class="audio-message">
                    <div class="audio-controls">
                        <button class="play-btn" onclick="voiceManager?.toggleAudio('${audioUrl}', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>
                            </svg>
                        </button>
                        <div class="audio-waveform" onclick="voiceManager?.toggleAudio('${audioUrl}', this.previousElementSibling)">
                            <div class="audio-progress"></div>
                        </div>
                        <span class="audio-duration">${duration}</span>
                    </div>
                </div>
            `;
        } else {
            // Message texte normal
            contentHtml = `<div class="message-content">${this.escapeHtml(message.message)}</div>`;
        }
        
        messageElement.innerHTML = headerHtml + contentHtml;

        // Ajouter une animation pour les nouveaux messages et des styles distinctifs
        if (!isOwnMessage) {
            messageElement.classList.add('highlight');
            // Ajouter une bordure colorée pour les autres utilisateurs
            messageElement.style.borderLeft = '4px solid #667eea';
        } else {
            // Ajouter une bordure différente pour ses propres messages
            messageElement.style.borderLeft = '4px solid #48bb78';
        }
        
        // Ajouter un attribut data pour le CSS
        messageElement.setAttribute('data-message-type', isOwnMessage ? 'own' : 'other');

        messagesContainer.appendChild(messageElement);
        
        // Limiter le nombre de messages affichés pour éviter la surcharge
        this.cleanupOldMessages();
        
        // Scroll vers le bas pour voir le nouveau message
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    cleanupOldMessages() {
        const messagesContainer = document.getElementById('messages');
        const messages = messagesContainer.querySelectorAll('.message');
        const maxMessages = 100; // Garder maximum 100 messages
        
        if (messages.length > maxMessages) {
            const messagesToRemove = messages.length - maxMessages;
            for (let i = 0; i < messagesToRemove; i++) {
                messages[i].remove();
            }
            console.log(`Supprimé ${messagesToRemove} anciens messages`);
        }
    }
    
    formatDuration(seconds) {
        if (!seconds) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    handleTyping() {
        if (!this.isTyping) {
            this.isTyping = true;
            this.sendTypingStatus(true);
        }

        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => {
            this.isTyping = false;
            this.sendTypingStatus(false);
        }, 1000);
    }

    async sendTypingStatus(isTyping) {
        try {
            await fetch('api.php?action=typing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ typing: isTyping })
            });
        } catch (error) {
            console.error('Erreur lors de l\'envoi du statut de frappe:', error);
        }
    }

    startPolling() {
        this.stopPolling(); // S'assurer qu'il n'y a pas de polling multiple
        
        // Ne démarrer le polling que si l'utilisateur est connecté
        if (!this.username) {
            console.warn('Impossible de démarrer le polling sans utilisateur connecté');
            return;
        }
        
        this.pollInterval = setInterval(() => {
            // Ne poll que si la page est visible et l'utilisateur connecté
            if (this.isPageVisible && this.username) {
                this.loadMessages();
                this.loadActiveUsers();
            }
        }, 1000); // Vérifier les nouveaux messages chaque seconde pour plus de réactivité
        
        // Vérification périodique de la session toutes les 15 secondes
        this.sessionCheckInterval = setInterval(() => {
            if (this.username) {
                this.checkSessionValidity();
            }
        }, 15000);
        
        console.log('Polling démarré pour:', this.username);
    }
    
    async checkSessionValidity() {
        // Vérification périodique de la validité de la session
        if (!this.username) {
            console.log('Pas d\'utilisateur connecté, pas de vérification nécessaire');
            return false;
        }
        
        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            formData.append('username', this.username);
            
            const response = await fetch('api.php?action=check_session', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            console.log('Vérification périodique session:', data);
            
            if (data.success) {
                const sessionUsername = data.data?.username || data.username || '';
                
                if (sessionUsername && sessionUsername !== this.username) {
                    console.log('Synchronisation nom d\'utilisateur:', this.username, '->', sessionUsername);
                    this.username = sessionUsername;
                    
                    const usernameDisplay = document.getElementById('current-username');
                    if (usernameDisplay) {
                        usernameDisplay.textContent = this.username;
                    }
                }
                return true;
            } else {
                console.warn('Session invalide détectée lors de la vérification périodique');
                this.handleSessionExpired();
                return false;
            }
        } catch (error) {
            console.error('Erreur vérification session:', error);
            return false;
        }
    }
    
    initVisibilityHandler() {
        // Gérer la visibilité de la page pour optimiser le polling
        document.addEventListener('visibilitychange', () => {
            this.isPageVisible = !document.hidden;
            
            if (this.isPageVisible) {
                console.log('Page visible - reprendre polling');
                // Recharger immédiatement quand on revient sur la page
                if (this.username && this.pollInterval) {
                    this.loadMessages();
                    this.loadActiveUsers();
                }
            } else {
                console.log('Page cachée - polling continue mais réduit');
            }
        });
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
            this.sessionCheckInterval = null;
        }
    }

    async loadActiveUsers() {
        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            
            const response = await fetch('api.php?action=get_active_users', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success && data.data && data.data.users) {
                this.updateActiveUsersList(data.data.users, data.data.total_connections, data.data.unique_users);
            } else if (data.message && (data.message.includes('expirée') || data.message.includes('authentifié'))) {
                this.handleSessionExpired();
            }
        } catch (error) {
            console.error('Erreur lors du chargement des utilisateurs actifs:', error);
        }
    }

    updateActiveUsersList(users, totalConnections, uniqueUsers) {
        const userInfo = document.querySelector('.user-info');
        let userListElement = document.getElementById('active-users-list');
        
        if (!userListElement) {
            userListElement = document.createElement('div');
            userListElement.id = 'active-users-list';
            userListElement.className = 'active-users';
            userInfo.appendChild(userListElement);
        }

        const userCount = users.length;
        const userList = users.slice(0, 4).join(', ');
        const moreText = userCount > 4 ? ` +${userCount - 4}` : '';
        
        // Créer l'affichage avec informations détaillées sur les connexions multiples
        const connectionsInfo = totalConnections && uniqueUsers && totalConnections > uniqueUsers 
            ? `<div class="connections-info">🔗 ${totalConnections} connexions actives</div>`
            : '';
        
        userListElement.innerHTML = `
            <small>👥 ${userCount} utilisateur${userCount > 1 ? 's' : ''}: ${userList}${moreText}</small>
            ${connectionsInfo}
        `;
    }

    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.textContent = status;
        }
    }

    showLogin() {
        document.getElementById('login-form').style.display = 'flex';
        document.getElementById('chat-interface').style.display = 'none';
        document.getElementById('username-input').focus();
    }

    showChat() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('chat-interface').style.display = 'flex';
        document.getElementById('current-username').textContent = this.username;
        document.getElementById('message-input').focus();
        
        console.log('Chat shown for user:', this.username);
    }

    clearMessages() {
        document.getElementById('messages').innerHTML = '';
        this.lastMessageId = 0;
        console.log('Messages cleared for user:', this.username);
    }

    isScrollAtBottom() {
        const container = document.querySelector('.messages-container');
        return container.scrollTop + container.clientHeight >= container.scrollHeight - 5;
    }

    scrollToBottom() {
        const container = document.querySelector('.messages-container');
        container.scrollTop = container.scrollHeight;
    }

    showNotification(message, type = 'info') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';

        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return 'À l\'instant';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `Il y a ${minutes} min`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `Il y a ${hours}h`;
        } else {
            return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', () => {
    window.chatApp = new ChatApp();
});

// Gestion des raccourcis clavier
document.addEventListener('keydown', (e) => {
    // Envoyer le message avec Ctrl+Entrée
    if (e.ctrlKey && e.key === 'Enter') {
        const messageForm = document.getElementById('message-form');
        if (messageForm && document.getElementById('chat-interface').style.display !== 'none') {
            messageForm.dispatchEvent(new Event('submit'));
        }
    }
    
    // Focus sur l'input de message avec "/"
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        const messageInput = document.getElementById('message-input');
        if (messageInput && document.getElementById('chat-interface').style.display !== 'none') {
            messageInput.focus();
        }
    }
});

// Gestion des erreurs globales
window.addEventListener('error', (e) => {
    console.error('Erreur JavaScript:', e.error);
});

// Gestion de la reconnexion automatique
window.addEventListener('online', () => {
    console.log('Connexion rétablie');
    // Recharger les messages si l'utilisateur est connecté
    if (window.chatApp && window.chatApp.username) {
        window.chatApp.loadMessages();
        window.chatApp.startPolling();
    }
});

window.addEventListener('offline', () => {
    console.log('Connexion perdue');
    if (window.chatApp) {
        window.chatApp.stopPolling();
    }
});

// Gestion de la fermeture de page/onglet
window.addEventListener('beforeunload', () => {
    if (window.chatApp && window.chatApp.username) {
        // Déconnexion synchrone rapide
        const formData = new FormData();
        formData.append('client_id', window.chatApp.clientId);
        
        navigator.sendBeacon('api.php?action=logout', formData);
        console.log('Déconnexion automatique lors de la fermeture de page');
    }
});

// Gestion de la perte de visibilité prolongée (pour mobile/fermeture d'onglet)
let inactiveTimer = null;
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        // Démarrer un timer pour déconnexion après 15 secondes d'inactivité
        inactiveTimer = setTimeout(() => {
            if (window.chatApp && window.chatApp.username) {
                console.log('Déconnexion automatique après inactivité prolongée');
                window.chatApp.logout();
            }
        }, 15000); // 15 secondes
    } else {
        // Annuler le timer si l'utilisateur revient
        if (inactiveTimer) {
            clearTimeout(inactiveTimer);
            inactiveTimer = null;
        }
    }
});