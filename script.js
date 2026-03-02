class ChatApp {
    constructor() {
        this.username = '';
        this.lastMessageId = 0;
        this.typingTimer = null;
        this.pollInterval = null;
        this.isTyping = false;
        this.isPageVisible = true;
        this.isGuest = false;
        this.messageCount = 0;
        this.messageLimit = 10;
        this.messagesRemaining = 10;
        this.isLoadingMessages = false; // Verrou pour éviter les appels simultanés
        this.isSendingMessage = false; // Verrou pour éviter les envois en double
        
        // Générer un ID client unique pour cette instance
        this.clientId = this.getOrCreateClientId();
        console.log('🆔 Client ID:', this.clientId);
        
        this.init();
        this.initVisibilityHandler();
    }
    
    getOrCreateClientId() {
        // Générer un ID valide pour PHP sessions (seulement A-Z, a-z, 0-9, tirets)
        // Pas d'underscores ni de points !
        const timestamp = Date.now().toString();
        const random = Math.random().toString(36).substring(2, 15);
        const instanceId = 'chat-' + timestamp + '-' + random;
        
        // Stocker dans sessionStorage pour que chaque onglet ait son propre ID
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
        // Formulaire de connexion invité
        document.getElementById('username-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });
        
        // Formulaire de connexion avec compte
        document.getElementById('login-account-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.loginWithAccount();
        });
        
        // Formulaire d'inscription
        document.getElementById('register-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.register();
        });

        // Formulaire de message - Sera remplacé par media-manager.js s'il est chargé
        const messageForm = document.getElementById('message-form');
        messageForm.onsubmit = (e) => {
            e.preventDefault();
            this.sendMessage();
            return false;
        };

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
        
        // Navigation entre les modes d'authentification
        document.getElementById('show-login').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('login');
        });
        
        document.getElementById('show-register').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('register');
        });
        
        document.getElementById('show-register-from-login').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('register');
        });
        
        document.getElementById('show-login_from_register').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('login');
        });
        
        document.getElementById('back-to-guest-from-login').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('guest');
        });
        
        document.getElementById('back-to-guest-from_register').addEventListener('click', (e) => {
            e.preventDefault();
            this.showAuthMode('guest');
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
                
                // Gérer les données invité
                if (data.data.is_guest) {
                    this.isGuest = true;
                    this.messageCount = data.data.message_count || 0;
                    this.messageLimit = data.data.limit || 10;
                    this.messagesRemaining = data.data.remaining || 0;
                    console.log(`Invité - Messages: ${this.messageCount}/${this.messageLimit}, Restants: ${this.messagesRemaining}`);
                    if (data.data.show_warning) {
                        this.showGuestWarning();
                    }
                } else {
                    // Utilisateur avec compte - réinitialiser les variables invité
                    this.isGuest = false;
                    this.messageCount = 0;
                    this.messagesRemaining = 0;
                    this.messageLimit = 0;
                    console.log('Utilisateur avec compte - Pas de limite de messages');
                }
                
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
                
                // Marquer comme invité (connexion sans compte)
                this.isGuest = true;
                this.messageCount = 0;
                this.messageLimit = 10;
                this.messagesRemaining = 10;
                console.log('Connexion en tant qu\'invité - Limite: 10 messages');
                
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

    async register() {
        const username = document.getElementById('register-username').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm-password').value;

        if (!username || !email || !password || !confirmPassword) {
            this.showNotification('Veuillez remplir tous les champs', 'error');
            return;
        }

        if (password !== confirmPassword) {
            this.showNotification('Les mots de passe ne correspondent pas', 'error');
            return;
        }

        if (password.length < 6) {
            this.showNotification('Le mot de passe doit contenir au moins 6 caractères', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);

            const response = await fetch('api.php?action=register', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message || 'Compte créé avec succès!', 'success');
                // Remplir automatiquement le formulaire de connexion
                document.getElementById('login-identifier').value = username;
                // Passer au mode connexion
                setTimeout(() => {
                    this.showAuthMode('login');
                }, 1500);
            } else {
                this.showNotification(data.message || 'Erreur lors de la création du compte', 'error');
            }
        } catch (error) {
            console.error('Erreur lors de l\'inscription:', error);
            this.showNotification('Erreur lors de l\'inscription', 'error');
        }
    }

    async loginWithAccount() {
        const identifier = document.getElementById('login-identifier').value.trim();
        const password = document.getElementById('login-password').value;

        if (!identifier || !password) {
            this.showNotification('Veuillez remplir tous les champs', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('client_id', this.clientId);
            formData.append('identifier', identifier);
            formData.append('password', password);

            const response = await fetch('api.php?action=login_account', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.username = data.data.username;
                console.log('Connexion avec compte réussie:', this.username);
                console.log('Données reçues du serveur:', data.data);
                console.log('is_guest reçu:', data.data.is_guest);
                console.log('user_id reçu:', data.data.user_id);
                
                // Réinitialiser les variables invité (utilisateur avec compte)
                this.isGuest = false;
                this.messageCount = 0;
                this.messagesRemaining = 0;
                this.messageLimit = 0;
                
                // Mettre à jour l'affichage
                const usernameDisplay = document.getElementById('current-username');
                if (usernameDisplay) {
                    usernameDisplay.textContent = this.username;
                }
                
                this.showChat();
                this.loadMessages();
                this.loadActiveUsers();
                this.startPolling();
                
                this.showNotification(data.message || 'Connexion réussie!', 'success');
            } else {
                this.showNotification(data.message || 'Erreur de connexion', 'error');
            }
        } catch (error) {
            console.error('Erreur lors de la connexion avec compte:', error);
            this.showNotification('Erreur de connexion', 'error');
        }
    }

    showAuthMode(mode) {
        const guestMode = document.getElementById('guest-mode');
        const loginMode = document.getElementById('login-mode');
        const registerMode = document.getElementById('register-mode');
        const authTitle = document.getElementById('auth-title');

        // Cacher tous les modes
        guestMode.style.display = 'none';
        loginMode.style.display = 'none';
        registerMode.style.display = 'none';

        // Afficher le mode demandé
        switch(mode) {
            case 'guest':
                guestMode.style.display = 'block';
                authTitle.textContent = 'Rejoindre le Chat';
                break;
            case 'login':
                loginMode.style.display = 'block';
                authTitle.textContent = 'Connexion au Chat';
                break;
            case 'register':
                registerMode.style.display = 'block';
                authTitle.textContent = 'Créer un Compte';
                break;
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
            // Réinitialiser les variables invité
            this.isGuest = false;
            this.messageCount = 0;
            this.messagesRemaining = 0;
            this.messageLimit = 10;
            this.showLogin();
            this.showNotification('Déconnexion réussie', 'success');
        } catch (error) {
            console.error('Erreur lors de la déconnexion:', error);
        }
    }

    async sendMessage() {
        // VERROU pour empêcher les appels simultanés
        if (this.isSendingMessage) {
            return;
        }
        this.isSendingMessage = true;
        
        // Désactiver le bouton d'envoi pour éviter les double-clics
        const submitBtn = document.querySelector('#message-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';
        }
        
        const messageInput = document.getElementById('message-input');
        const message = messageInput.value.trim();

        if (!message) {
            this.isSendingMessage = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Envoyer';
            }
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
                
                // Mettre à jour les compteurs pour les invités
                if (data.data?.is_guest) {
                    this.isGuest = true;
                    this.messageCount = data.data.message_count;
                    this.messagesRemaining = data.data.remaining;
                    this.messageLimit = data.data.limit;
                    
                    console.log(`Invité - Messages: ${this.messageCount}/${this.messageLimit}, Restants: ${this.messagesRemaining}`);
                    
                    // Afficher l'avertissement si nécessaire
                    if (data.data.show_warning) {
                        this.showGuestWarning();
                    }
                }
                
                // Recharger immédiatement les messages (le verrou empêche les doublons)
                this.loadMessages();
            } else {
                // Vérifier si la limite est atteinte
                if (data.data && data.data.limit_reached) {
                    this.handleGuestLimitReached();
                    return;
                }
                
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
        } finally {
            // Déverrouiller à la fin
            this.isSendingMessage = false;
            
            // Réactiver le bouton d'envoi
            const submitBtn = document.querySelector('#message-form button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Envoyer';
            }
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
        
        // Éviter les appels simultanés qui créent des doublons
        if (this.isLoadingMessages) {
            console.log('⏳ loadMessages déjà en cours, appel ignoré');
            return;
        }
        
        this.isLoadingMessages = true;
        
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
        } finally {
            // Toujours libérer le verrou
            this.isLoadingMessages = false;
        }
    }

    displayMessage(message) {
        // Vérifier que l'utilisateur est connecté avant d'afficher
        if (!this.username) {
            console.warn('displayMessage appelé sans utilisateur connecté, message ignoré');
            return;
        }
        
        const messagesContainer = document.getElementById('messages');
        const messageElement = document.createElement('div');
        
        // Comparaison normalisée (insensible à la casse et aux espaces)
        const normalizeUsername = (username) => username?.trim().toLowerCase() || '';
        const messageUser = normalizeUsername(message.username);
        const currentUser = normalizeUsername(this.username);
        const isOwnMessage = messageUser === currentUser;
        
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
        } else if (message.media_type && message.media_path) {
            // Message avec média (photo ou vidéo)
            const mediaUrl = `api.php?action=get_media&type=${message.media_type}&file=${message.media_path}`;
            
            if (message.media_type === 'image') {
                contentHtml = `
                    <div class="media-container">
                        <img src="${mediaUrl}" alt="Image partagée" onclick="window.openMediaModal('${mediaUrl}')" loading="lazy">
                        ${message.message && message.message !== '[Image]' ? `<div class="media-caption">${this.escapeHtml(message.message)}</div>` : ''}
                    </div>
                `;
            } else if (message.media_type === 'video') {
                contentHtml = `
                    <div class="media-container">
                        <video controls preload="metadata">
                            <source src="${mediaUrl}" type="video/mp4">
                            Votre navigateur ne supporte pas la lecture de vidéos.
                        </video>
                        ${message.message && message.message !== '[Video]' ? `<div class="media-caption">${this.escapeHtml(message.message)}</div>` : ''}
                    </div>
                `;
            }
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
        let html = div.innerHTML;
        // Remplacer tous les codes d'apostrophe par '
        html = html.replace(/&#39;|&#039;/g, "'");
        return html;
    }

    showGuestWarning() {
        if (!this.isGuest || this.messagesRemaining <= 0) return;
        
        const warningHtml = `
            <div class="guest-warning" id="guest-warning">
                <div class="warning-content">
                    <span class="warning-icon">⚠️</span>
                    <span class="warning-text">
                        <strong>Attention !</strong> Il vous reste <strong>${this.messagesRemaining}</strong> message${this.messagesRemaining > 1 ? 's' : ''} en tant qu'invité.
                        <a href="#" id="create-account-link" style="color: #3182ce; text-decoration: underline; margin-left: 5px;">Créer un compte</a> pour continuer à discuter.
                    </span>
                    <button class="warning-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            </div>
        `;
        
        // Vérifier si l'avertissement existe déjà
        const existingWarning = document.getElementById('guest-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
        
        // Ajouter l'avertissement dans l'interface de chat
        const chatContainer = document.querySelector('.chat-container');
        if (chatContainer) {
            chatContainer.insertAdjacentHTML('afterbegin', warningHtml);
            
            // Ajouter l'événement pour le lien
            const createAccountLink = document.getElementById('create-account-link');
            if (createAccountLink) {
                createAccountLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.logout();
                    setTimeout(() => {
                        this.showAuthMode('register');
                    }, 500);
                });
            }
        }
    }

    handleGuestLimitReached() {
        // Bloquer l'interface de saisie
        const messageInput = document.getElementById('message-input');
        const sendButton = document.querySelector('#message-form button[type="submit"]');
        const voiceBtn = document.getElementById('voice-btn');
        
        if (messageInput) {
            messageInput.disabled = true;
            messageInput.placeholder = 'Limite atteinte - Créez un compte pour continuer';
        }
        if (sendButton) sendButton.disabled = true;
        if (voiceBtn) voiceBtn.disabled = true;
        
        // Afficher une notification modale
        const modalHtml = `
            <div class="guest-limit-modal" id="guest-limit-modal">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>🚫 Limite de messages atteinte</h2>
                    </div>
                    <div class="modal-body">
                        <p>Vous avez atteint la limite de <strong>${this.messageLimit} messages</strong> en tant qu'invité.</p>
                        <p><strong>Créez un compte gratuit</strong> pour continuer à discuter sans limites !</p>
                        <div class="benefits">
                            <div class="benefit-item">✅ Messages illimités</div>
                            <div class="benefit-item">✅ Historique sauvegardé</div>
                            <div class="benefit-item">✅ Connexion permanente</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-primary" id="modal-create-account">Créer un compte</button>
                        <button class="btn-secondary" id="modal-disconnect">Se déconnecter</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Gestionnaires d'événements
        document.getElementById('modal-create-account').addEventListener('click', () => {
            document.getElementById('guest-limit-modal').remove();
            this.logout();
            setTimeout(() => {
                this.showAuthMode('register');
            }, 500);
        });
        
        document.getElementById('modal-disconnect').addEventListener('click', () => {
            document.getElementById('guest-limit-modal').remove();
            this.logout();
        });
        
        this.showNotification('Limite de messages atteinte. Créez un compte pour continuer !', 'error');
    }
}

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', () => {
    if (!window.chatApp) {
        window.chatApp = new ChatApp();
    }
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