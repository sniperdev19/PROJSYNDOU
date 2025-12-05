class MediaManager {
    constructor(chatApp) {
        this.chatApp = chatApp;
        this.selectedFile = null;
        this.maxImageSize = 5 * 1024 * 1024; // 5MB
        this.maxVideoSize = 20 * 1024 * 1024; // 20MB
        this.init();
    }

    init() {
        const mediaBtn = document.getElementById('media-btn');
        const mediaInput = document.getElementById('media-input');
        const cancelBtn = document.getElementById('cancel-media');

        // Ouvrir le sélecteur de fichier
        mediaBtn.addEventListener('click', () => {
            mediaInput.click();
        });

        // Gérer la sélection de fichier
        mediaInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleFileSelect(file);
            }
        });

        // Annuler la sélection
        cancelBtn.addEventListener('click', () => {
            this.cancelMediaSelection();
        });

        // Modifier l'envoi de message pour inclure les médias
        const messageForm = document.getElementById('message-form');
        const originalSubmit = messageForm.onsubmit;
        
        messageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (this.selectedFile) {
                this.sendMediaMessage();
            } else {
                // Appeler la méthode d'envoi de message texte normale
                this.chatApp.sendMessage();
            }
        });
    }

    handleFileSelect(file) {
        const isImage = file.type.startsWith('image/');
        const isVideo = file.type.startsWith('video/');

        if (!isImage && !isVideo) {
            this.chatApp.showNotification('Veuillez sélectionner une image ou une vidéo', 'error');
            return;
        }

        // Vérifier la taille du fichier
        if (isImage && file.size > this.maxImageSize) {
            this.chatApp.showNotification('L\'image ne doit pas dépasser 5MB', 'error');
            return;
        }

        if (isVideo && file.size > this.maxVideoSize) {
            this.chatApp.showNotification('La vidéo ne doit pas dépasser 20MB', 'error');
            return;
        }

        this.selectedFile = file;
        this.showPreview(file);
    }

    showPreview(file) {
        const preview = document.getElementById('media-preview');
        const previewImage = document.getElementById('preview-image');
        const previewVideo = document.getElementById('preview-video');
        const filename = document.getElementById('media-filename');

        // Cacher les deux prévisualisations
        previewImage.style.display = 'none';
        previewVideo.style.display = 'none';

        const isImage = file.type.startsWith('image/');
        const reader = new FileReader();

        reader.onload = (e) => {
            if (isImage) {
                previewImage.src = e.target.result;
                previewImage.style.display = 'block';
            } else {
                previewVideo.src = e.target.result;
                previewVideo.style.display = 'block';
            }
            filename.textContent = file.name;
            preview.style.display = 'flex';
        };

        reader.readAsDataURL(file);
    }

    cancelMediaSelection() {
        this.selectedFile = null;
        document.getElementById('media-input').value = '';
        document.getElementById('media-preview').style.display = 'none';
        document.getElementById('preview-image').src = '';
        document.getElementById('preview-video').src = '';
    }

    async sendMediaMessage() {
        if (!this.selectedFile) {
            return;
        }

        const messageInput = document.getElementById('message-input');
        const caption = messageInput.value.trim();

        // Vérifier que l'utilisateur est connecté
        if (!this.chatApp.username) {
            this.chatApp.showNotification('Vous devez être connecté pour envoyer un média', 'error');
            return;
        }

        try {
            // Créer FormData pour l'upload
            const formData = new FormData();
            formData.append('client_id', this.chatApp.clientId);
            formData.append('username', this.chatApp.username);
            formData.append('media', this.selectedFile);
            
            if (caption) {
                formData.append('caption', caption);
            }

            // Afficher un indicateur de chargement
            this.chatApp.showNotification('Envoi du média en cours...', 'info');

            const response = await fetch('api.php?action=send_media', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                messageInput.value = '';
                this.cancelMediaSelection();
                
                // Mettre à jour les compteurs pour les invités
                if (data.data?.is_guest) {
                    this.chatApp.isGuest = true;
                    this.chatApp.messageCount = data.data.message_count;
                    this.chatApp.messagesRemaining = data.data.remaining;
                    this.chatApp.messageLimit = data.data.limit;
                    
                    if (data.data.show_warning) {
                        this.chatApp.showGuestWarning();
                    }
                }
                
                this.chatApp.showNotification('Média envoyé avec succès!', 'success');
                this.chatApp.loadMessages();
            } else {
                // Vérifier si la limite est atteinte
                if (data.data && data.data.limit_reached) {
                    this.chatApp.handleGuestLimitReached();
                    return;
                }
                
                this.chatApp.showNotification(data.message || 'Erreur lors de l\'envoi du média', 'error');
            }
        } catch (error) {
            console.error('Erreur lors de l\'envoi du média:', error);
            this.chatApp.showNotification('Erreur lors de l\'envoi du média', 'error');
        }
    }
}

// Initialiser le gestionnaire de médias après le chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    // Attendre que ChatApp soit initialisé
    setTimeout(() => {
        if (window.chatApp) {
            window.mediaManager = new MediaManager(window.chatApp);
            console.log('📷 Media Manager initialisé');
        }
    }, 100);
});
