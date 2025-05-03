/**
 * profileVisitedCookie.js - Versão Limpa e Direta
 * Gerencia cookies para perfis visitados e implementa o bloqueio VIP
 */

// Função para definir um cookie simples
function setCookie(name, value, days) {
  let expires = "";
  if (days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "") + expires + "; path=/";
  console.log(`Cookie definido: ${name}=${value}`);
}

// Função para obter um cookie pelo nome
function getCookie(name) {
  const nameEQ = name + "=";
  const ca = document.cookie.split(';');
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

// Função para marcar um perfil como visitado
function setProfileVisitedCookie(profileId) {
  if (!profileId) return;
  setCookie(`profile_${profileId}_visited`, "true", 30);
}

// Função para verificar se um perfil já foi visitado
function hasProfileBeenVisited(profileId) {
  if (!profileId) return false;
  return getCookie(`profile_${profileId}_visited`) === "true";
}

// Função para verificar se um usuário tem acesso VIP a um perfil
function hasVIPAccess(profileId) {
  if (!profileId) return false;
  return getCookie(`profile_${profileId}_vip`) === "true";
}

// Função para definir acesso VIP a um perfil
function setVIPAccessCookie(profileId) {
  if (!profileId) return;
  setCookie(`profile_${profileId}_vip`, "true", 30);
}

// Função para aplicar o bloqueio VIP a um elemento de perfil
function transformToVIPLocked(profileElement, profileId) {
  console.log(`Aplicando bloqueio VIP ao perfil ${profileId}`);
  
  // Verifica se o elemento já tem overlay VIP
  if (profileElement.classList.contains('vip-locked')) {
    console.log(`Perfil ${profileId} já está bloqueado, ignorando`);
    return;
  }
  
  // Adiciona a classe de bloqueio
  profileElement.classList.add('vip-locked');
  
  // Cria o elemento de overlay
  const overlay = document.createElement('div');
  overlay.className = 'vip-lock-overlay';
  
  // Cria o conteúdo com ícone e botão
  const lockContent = document.createElement('div');
  lockContent.className = 'vip-lock-content';
  lockContent.innerHTML = `
    <i class="fas fa-lock"></i>
    <p>Apenas para VIP</p>
    <button class="vip-button" data-profile-id="${profileId}">Tornar-se VIP</button>
  `;
  
  // Adiciona elementos ao DOM
  overlay.appendChild(lockContent);
  profileElement.appendChild(overlay);
  
  // Adiciona evento ao botão VIP
  const vipButton = lockContent.querySelector('.vip-button');
  vipButton.addEventListener('click', function(e) {
    e.stopPropagation();
    e.preventDefault();
    showVIPPayment(profileId);
  });
  
  console.log(`Bloqueio VIP aplicado com sucesso ao perfil ${profileId}`);
}

// Função para mostrar o modal de pagamento VIP
function showVIPPayment(profileId) {
  console.log(`Mostrando pagamento VIP para perfil ${profileId}`);
  
  const paymentContainer = document.getElementById('payment-container');
  if (!paymentContainer) {
    console.error("Container de pagamento não encontrado");
    return;
  }
  
  // Define o ID do perfil para uso posterior
  paymentContainer.setAttribute('data-profile-id', profileId);
  
  // Atualiza o título
  const paymentTitle = paymentContainer.querySelector('h2');
  if (paymentTitle) {
    paymentTitle.textContent = `VIP para perfil: ${profileId}`;
  }
  
  // Mostra o container de pagamento
  paymentContainer.classList.add('active');
  
  // Gera o código PIX
  if (typeof window.generatePix === 'function') {
    window.generatePix(profileId);
  }
}

// Forçar redirecionamento para o URL de um perfil
function forceRedirect(profile) {
  if (!profile) {
    console.error("Tentativa de redirecionamento sem perfil válido");
    return;
  }
  
  const redirectUrl = profile.getAttribute('data-redirect');
  const profileId = profile.getAttribute('data-profile');
  
  if (!redirectUrl || !profileId) {
    console.error("Perfil sem URL de redirecionamento ou ID");
    return;
  }
  
  console.log(`Forçando redirecionamento para ${profileId}: ${redirectUrl}`);
  
  try {
    // Define o cookie para marcar como visitado
    setProfileVisitedCookie(profileId);
    
    // Cria o efeito de loading
    const pageLoader = document.createElement('div');
    pageLoader.className = 'page-loader';
    pageLoader.innerHTML = `
      <img src="logo400x130.png" alt="Logo">
      <div class="loader"></div>
    `;
    document.body.appendChild(pageLoader);
    
    // Garante que o cookie foi realmente definido
    console.log(`Verificando cookie antes do redirecionamento: ${getCookie(`profile_${profileId}_visited`)}`);
    
    // Redireciona para a URL da live de forma direta
    console.log(`Executando redirecionamento para: ${redirectUrl}`);
    window.location.href = redirectUrl + '&loading=1';
  } catch (error) {
    console.error(`Erro ao redirecionar: ${error}`);
    // Tentativa de redirecionamento alternativo
    window.location.replace(redirectUrl);
  }
}

// Processa um elemento de perfil específico
function setupProfileElement(profile, profileId) {
  // Verificamos se o perfil já foi visitado
  const visited = hasProfileBeenVisited(profileId);
  console.log(`Perfil ${profileId} já foi visitado: ${visited}`);
  
  // Verifica se tem acesso VIP
  const hasVIP = hasVIPAccess(profileId);
  
  // Se já foi visitado e não tem VIP, aplicamos o bloqueio
  if (visited && !hasVIP) {
    console.log(`Aplicando bloqueio VIP ao perfil ${profileId}`);
    transformToVIPLocked(profile, profileId);
  } else {
    // Se não foi visitado ou tem VIP, configuramos o redirecionamento
    console.log(`Configurando redirecionamento para perfil ${profileId} (não visitado)`);
    
    // Adiciona um botão invisível com alto z-index para interceptar o clique
    const clickHandler = document.createElement('div');
    clickHandler.className = 'profile-click-handler';
    clickHandler.style.cssText = `
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 5;
      cursor: pointer;
    `;
    
    // Adicionamos o handler de clique no novo elemento
    clickHandler.addEventListener('click', function(e) {
      // Ignoramos cliques em elementos específicos
      if (e.target.closest('.profile-link') || e.target.closest('.vip-button') || 
          e.target.closest('.vip-lock-overlay')) {
        console.log(`Clique em elemento específico, ignorando redirecionamento`);
        return;
      }
      
      console.log(`Clique no handler de ${profileId}, processando redirecionamento`);
      e.preventDefault();
      e.stopPropagation();
      
      // Força o redirecionamento usando o profile pai
      forceRedirect(profile);
    });
    
    // Inserimos o handler logo após o header para não interferir com outros elementos
    const profileHeader = profile.querySelector('.profile-header');
    if (profileHeader && profileHeader.nextSibling) {
      profile.insertBefore(clickHandler, profileHeader.nextSibling);
    } else {
      profile.appendChild(clickHandler);
    }
    
    // Adicionamos também o listener normal por precaução
    profile.addEventListener('click', function(e) {
      // Ignoramos cliques em elementos específicos
      if (e.target.closest('.profile-link') || e.target.closest('.vip-button') || 
          e.target.closest('.vip-lock-overlay')) {
        console.log(`Clique em elemento específico, ignorando redirecionamento`);
        return;
      }
      
      console.log(`Clique direto em perfil ${profileId}, processando redirecionamento`);
      e.preventDefault();
      e.stopPropagation();
      
      // Força o redirecionamento
      forceRedirect(this);
    });
  }
}

// Configurar perfis na página
function setupProfiles() {
  console.log("Configurando perfis na página");
  
  // Para cada perfil online, verificamos se já foi visitado
  document.querySelectorAll('.profile[data-status="online"]').forEach(profile => {
    const profileId = profile.getAttribute('data-profile');
    
    // Se o perfil não tiver ID, ignoramos
    if (!profileId) {
      console.log("Perfil sem ID, ignorando");
      return;
    }
    
    // Remove qualquer overlay e classe existente para começar limpo
    const existingOverlay = profile.querySelector('.vip-lock-overlay');
    if (existingOverlay) {
      console.log(`Removendo overlay existente do perfil ${profileId}`);
      existingOverlay.remove();
    }
    
    if (profile.classList.contains('vip-locked')) {
      console.log(`Removendo classe vip-locked do perfil ${profileId}`);
      profile.classList.remove('vip-locked');
    }
    
    // Remover event listeners anteriores usando clone
    const newProfile = profile.cloneNode(true);
    if (profile.parentNode) {
      profile.parentNode.replaceChild(newProfile, profile);
      // Não reatribuímos o parâmetro, usamos a referência correta a partir daqui
      const currentProfile = newProfile;
      
      // Continuamos o processamento com o elemento clonado
      setupProfileElement(currentProfile, profileId);
      return; // Saímos da função para não processar duas vezes
    }
    
    // Se não conseguimos fazer o clone (improvável), processamos diretamente
    setupProfileElement(profile, profileId);
  });
  
  // Adiciona estilo para o handler de clique
  if (!document.getElementById('profile-handler-style')) {
    const style = document.createElement('style');
    style.id = 'profile-handler-style';
    style.textContent = `
      .profile-click-handler:hover {
        background-color: rgba(255, 255, 255, 0.1);
      }
    `;
    document.head.appendChild(style);
  }
}

// Adiciona estilos CSS necessários para os elementos de overlay e clique
function addRequiredStyles() {
  if (!document.getElementById('profile-visited-styles')) {
    const style = document.createElement('style');
    style.id = 'profile-visited-styles';
    style.textContent = `
      .profile {
        position: relative;
        cursor: pointer;
        overflow: hidden;
        z-index: 1;
      }
      
      .profile-click-handler {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 5;
        cursor: pointer;
        transition: background-color 0.2s;
      }
      
      .profile-click-handler:hover {
        background-color: rgba(255, 255, 255, 0.1);
      }
      
      .vip-locked {
        position: relative;
      }
      
      .vip-lock-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 20;
        border-radius: 15px;
      }
    `;
    document.head.appendChild(style);
    console.log("Estilos adicionados ao document");
  }
}

// Limpa todos os handlers de clique anteriores de todos os perfis
function resetAllProfiles() {
  // Para cada perfil, fazemos o processo de limpeza
  document.querySelectorAll('.profile').forEach(profile => {
    // Clonar para remover event listeners
    const newProfile = profile.cloneNode(true);
    
    // Remover overlays que possam existir
    const overlays = newProfile.querySelectorAll('.vip-lock-overlay, .profile-click-handler');
    overlays.forEach(overlay => overlay.remove());
    
    // Remover classes de estado
    newProfile.classList.remove('vip-locked');
    
    // Substituir elemento original
    if (profile.parentNode) {
      profile.parentNode.replaceChild(newProfile, profile);
    }
  });
  
  console.log("Todos os perfis foram resetados");
}

// Inicializa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
  console.log("profileVisitedCookie.js carregado: Versão 3.2");
  
  // Adicionar estilos necessários
  addRequiredStyles();
  
  // Debug: Verifica estado inicial dos cookies
  document.querySelectorAll('.profile[data-status="online"]').forEach(profile => {
    const profileId = profile.getAttribute('data-profile');
    if (profileId) {
      console.log(`Estado inicial - Perfil ${profileId}: visitado=${hasProfileBeenVisited(profileId)}, VIP=${hasVIPAccess(profileId)}`);
    }
  });
  
  // Reseta todos os perfis para começar limpo
  resetAllProfiles();
  
  // Configurar perfis imediatamente
  setupProfiles();
  
  // E também após um atraso para garantir que a DOM esteja completamente carregada
  setTimeout(function() {
    console.log("Reconfiguração de perfis após delay");
    setupProfiles();
  }, 1000);
  
  // Adicionar evento global para capturar os cliques diretos nos perfis
  document.addEventListener('click', function(e) {
    const profileElement = e.target.closest('.profile');
    
    if (profileElement && !e.target.closest('.profile-link') && 
        !e.target.closest('.vip-button') && !e.target.closest('.vip-lock-overlay')) {
      
      const profileId = profileElement.getAttribute('data-profile');
      const status = profileElement.getAttribute('data-status');
      
      console.log(`Clique global capturado em perfil: ${profileId}, status: ${status}`);
      
      // Se for online e não tiver bloqueio VIP, processa o redirecionamento
      if (status === 'online' && !profileElement.classList.contains('vip-locked')) {
        e.preventDefault();
        e.stopPropagation();
        console.log("Processando redirecionamento via handler global");
        forceRedirect(profileElement);
      }
    }
  });
});