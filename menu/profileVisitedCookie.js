/**
 * profileVisitedCookie.js
 * Handles cookie management for tracking visited profiles and implementing the VIP lock functionality
 */

// Set a cookie for a visited profile
function setProfileVisitedCookie(profileId) {
  const cookieName = `profile_${profileId}_visited`;
  const expirationDays = 30;
  const date = new Date();
  date.setTime(date.getTime() + (expirationDays * 24 * 60 * 60 * 1000));
  const expires = `expires=${date.toUTCString()}`;
  document.cookie = `${cookieName}=true;${expires};path=/`;
}

// Check if a profile has been visited
function hasProfileBeenVisited(profileId) {
  const cookieName = `profile_${profileId}_visited`;
  const cookies = document.cookie.split(';');
  for (let i = 0; i < cookies.length; i++) {
    let cookie = cookies[i].trim();
    if (cookie.indexOf(cookieName + '=') === 0) {
      return true;
    }
  }
  return false;
}

// Get all visited profile IDs
function getVisitedProfileIds() {
  const visitedIds = [];
  const cookies = document.cookie.split(';');
  
  for (let i = 0; i < cookies.length; i++) {
    let cookie = cookies[i].trim();
    if (cookie.indexOf('profile_') === 0 && cookie.indexOf('_visited=') > 0) {
      const match = cookie.match(/profile_([^_]+)_visited/);
      if (match && match[1]) {
        visitedIds.push(match[1]);
      }
    }
  }
  
  return visitedIds;
}

// Apply VIP lock to profiles that have been visited (with delay)
function applyVIPLockToProfiles() {
  // Add delay of 4 seconds before applying VIP locks
  setTimeout(() => {
    const profileElements = document.querySelectorAll('.profile');
    
    profileElements.forEach(element => {
      const profileName = element.getAttribute('data-profile');
      if (profileName && hasProfileBeenVisited(profileName)) {
        // Only apply if the profile is online (we lock online profiles)
        if (element.getAttribute('data-status') === 'online') {
          transformToVIPLocked(element, profileName);
        }
      }
    });
  }, 1800); // 4 seconds delay
}

// Transform a profile element to show VIP locked state
function transformToVIPLocked(profileElement, profileId) {
  // 1. Add VIP locked class
  profileElement.classList.add('vip-locked');
  
  // 2. Create overlay elements
  const overlay = document.createElement('div');
  overlay.className = 'vip-lock-overlay';
  
  // 3. Create lock icon and text
  const lockContent = document.createElement('div');
  lockContent.className = 'vip-lock-content';
  lockContent.innerHTML = `
    <i class="fas fa-lock"></i>
    <p>Apenas para VIP</p>
    <button class="vip-button" data-profile-id="${profileId}">Tornar-se VIP</button>
  `;
  
  // 4. Append elements
  overlay.appendChild(lockContent);
  profileElement.appendChild(overlay);
  
  // 5. Remove click event that redirects to live
  profileElement.style.pointerEvents = 'none';
  overlay.style.pointerEvents = 'auto';
  
  // 6. Add click event to VIP button
  const vipButton = lockContent.querySelector('.vip-button');
  vipButton.addEventListener('click', function(e) {
    e.stopPropagation();
    const profileId = this.getAttribute('data-profile-id');
    showVIPPayment(profileId);
  });
}

// Show VIP payment modal
function showVIPPayment(profileId) {
  // Get payment container
  const paymentContainer = document.getElementById('payment-container');
  
  // Set the profile ID as data attribute for later use
  paymentContainer.setAttribute('data-profile-id', profileId);
  
  // Update the payment container title if needed
  const paymentTitle = paymentContainer.querySelector('h2');
  if (paymentTitle) {
    paymentTitle.textContent = `VIP para perfil: ${profileId}`;
  }
  
  // Show payment container
  paymentContainer.classList.add('active');
  
  // Generate PIX QR code based on profile ID
  generatePix(profileId);
}

// Generate PIX QR code
function generatePix(profileId) {
  // Get the current value associated with the profile from the live page link
  const profileElement = document.querySelector(`.profile[data-profile="${profileId}"]`);
  const redirectUrl = profileElement ? profileElement.getAttribute('data-redirect') : null;
  
  // Extract the ID parameter from the URL if it exists
  let amount = 29.90; // Default fallback value
  if (redirectUrl) {
    const urlParams = new URLSearchParams(redirectUrl.split('?')[1]);
    const idParam = urlParams.get('id');
    if (idParam && !isNaN(idParam)) {
      amount = parseFloat(idParam);
    }
  }
  
  // Directly include BSPayAPI and create the PIX code
  // This is an internal implementation which calls the same endpoint as live2.php
  // but ensures we handle all the responses correctly
  const xhr = new XMLHttpRequest();
  xhr.open('GET', `/live2.php?id=${amount}`, true);
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      try {
        if (xhr.status === 200) {
          // Successfully loaded the live2.php page
          // Extract QR code and other data from the response
          const parser = new DOMParser();
          const htmlDoc = parser.parseFromString(xhr.responseText, 'text/html');
          
          // Extract QR code image URL
          const qrCodeImg = htmlDoc.getElementById('qrCodeImg');
          const qrImgSrc = qrCodeImg ? qrCodeImg.getAttribute('src') : null;
          
          // Extract PIX code text
          const pixCodeElem = htmlDoc.getElementById('pixCode');
          const pixCodeText = pixCodeElem ? pixCodeElem.textContent : null;
          
          // Extract transaction ID from page (hidden in script)
          const scriptContent = xhr.responseText;
          const transactionIdMatch = scriptContent.match(/transactionId\s*=\s*["']([^"']+)["']/);
          const transactionId = transactionIdMatch ? transactionIdMatch[1] : null;
          
          if (qrImgSrc && pixCodeText && transactionId) {
            // Update QR code image
            const qrCodeImg = document.getElementById('qrCodeImg');
            if (qrCodeImg) {
              qrCodeImg.src = qrImgSrc;
            }
            
            // Update PIX code
            const pixCode = document.getElementById('pixCode');
            if (pixCode) {
              pixCode.textContent = pixCodeText;
            }
            
            // Update amount display
            updateAmountDisplay(amount);
            
            // Start checking payment status
            startCheckingPaymentStatus(transactionId);
          } else {
            console.error('Could not extract all required data from live2.php');
            alert('Erro ao gerar pagamento PIX. Por favor, tente novamente.');
          }
        } else {
          console.error('Error loading payment data:', xhr.status);
          alert('Erro ao gerar pagamento PIX. Por favor, tente novamente.');
        }
      } catch (error) {
        console.error('Error processing PIX generation:', error);
        alert('Erro ao processar pagamento PIX. Por favor, tente novamente.');
      }
    }
  };
  xhr.send();
}

// Update amount display in payment container
function updateAmountDisplay(amount) {
  const amountElement = document.querySelector('#payment-container h3');
  if (amountElement) {
    amountElement.textContent = `R$ ${amount.toFixed(2).replace('.', ',')}`;
  }
}

// Check payment status
function startCheckingPaymentStatus(transactionId) {
  const statusElement = document.getElementById('statusPagamento');
  
  // Start checking status every 3 seconds
  const checkInterval = setInterval(() => {
    fetch(`/verificar_status.php?transaction_id=${transactionId}`)
      .then(response => response.json())
      .then(data => {
        if (data.status === "PAID") {
          // Update visual status
          statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Pagamento confirmado! Redirecionando...';
          statusElement.classList.remove('status-pending');
          statusElement.classList.add('status-success');
          
          // Clear the interval
          clearInterval(checkInterval);
          
          // Redirect to VIP area or enable access
          setTimeout(() => {
            enableVIPAccess();
          }, 2000);
        }
      })
      .catch(error => {
        console.error('Error checking payment status:', error);
      });
  }, 3000);
}

// Enable VIP access after successful payment
function enableVIPAccess() {
  // Hide payment container
  const paymentContainer = document.getElementById('payment-container');
  paymentContainer.classList.remove('active');
  
  // Get the profile ID from the payment container
  const profileId = paymentContainer.getAttribute('data-profile-id');
  
  if (profileId) {
    // Get the profile element
    const profileElement = document.querySelector(`.profile[data-profile="${profileId}"]`);
    
    if (profileElement) {
      // Remove VIP lock overlay
      const overlay = profileElement.querySelector('.vip-lock-overlay');
      if (overlay) {
        overlay.remove();
      }
      
      // Remove VIP locked class
      profileElement.classList.remove('vip-locked');
      
      // Restore pointer events
      profileElement.style.pointerEvents = 'auto';
      
      // Set a VIP access cookie
      setVIPAccessCookie(profileId);
      
      // Redirect to the original URL
      const redirectUrl = profileElement.getAttribute('data-redirect');
      if (redirectUrl) {
        window.location.href = redirectUrl;
      }
    }
  }
}

// Set VIP access cookie
function setVIPAccessCookie(profileId) {
  const cookieName = `profile_${profileId}_vip`;
  const expirationDays = 30;
  const date = new Date();
  date.setTime(date.getTime() + (expirationDays * 24 * 60 * 60 * 1000));
  const expires = `expires=${date.toUTCString()}`;
  document.cookie = `${cookieName}=true;${expires};path=/`;
}

// Check if a profile has VIP access
function hasVIPAccess(profileId) {
  const cookieName = `profile_${profileId}_vip`;
  const cookies = document.cookie.split(';');
  for (let i = 0; i < cookies.length; i++) {
    let cookie = cookies[i].trim();
    if (cookie.indexOf(cookieName + '=') === 0) {
      return true;
    }
  }
  return false;
}

// Copy PIX code to clipboard
function copyPixCode() {
  const pixCode = document.getElementById('pixCode');
  if (pixCode) {
    navigator.clipboard.writeText(pixCode.textContent)
      .then(() => {
        // Show success message
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        if (toast && toastMessage) {
          toastMessage.textContent = 'Código PIX copiado com sucesso!';
          toast.classList.add('show');
          setTimeout(() => {
            toast.classList.remove('show');
          }, 3000);
        }
      })
      .catch(err => {
        console.error('Erro ao copiar: ', err);
        alert('Não foi possível copiar o código. Por favor, copie manualmente.');
      });
  }
}

// Function to handle chat payment clicks in profile popups
function setupChatPaymentButtons() {
  // Use event delegation to handle dynamically created buttons
  document.addEventListener('click', function(event) {
    if (event.target.classList.contains('profile-popup-message') || 
        event.target.closest('.profile-popup-message')) {
      
      const button = event.target.classList.contains('profile-popup-message') ? 
                    event.target : event.target.closest('.profile-popup-message');
      
      const profileId = button.getAttribute('data-profile-id');
      if (profileId) {
        event.preventDefault();
        event.stopPropagation();
        // Open chat payment container and generate PIX
        const chatPaymentContainer = document.getElementById('chat-payment-container');
        if (chatPaymentContainer) {
          chatPaymentContainer.classList.add('active');
          generatePix(profileId);
        }
      }
    }
  });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // Apply VIP locks to visited profiles
  applyVIPLockToProfiles();
  
  // Set up redirects to save visited profiles
  const profileElements = document.querySelectorAll('.profile[data-status="online"]');
  profileElements.forEach(element => {
    const originalClick = element.onclick;
    
    element.onclick = function(e) {
      // Get profile ID
      const profileId = this.getAttribute('data-profile');
      
      // Set cookie for this profile
      if (profileId) {
        setProfileVisitedCookie(profileId);
      }
      
      // If VIP access exists, or profile wasn't visited before, continue with original click
      if (profileId && (hasVIPAccess(profileId) || !hasProfileBeenVisited(profileId))) {
        if (originalClick) {
          originalClick.call(this, e);
        }
      } else {
        // Otherwise prevent default and show VIP lock
        e.preventDefault();
        e.stopPropagation();
        transformToVIPLocked(this, profileId);
      }
    };
  });
  
  // Set up copy PIX button if it exists
  const copyButton = document.querySelector('.copy-button');
  if (copyButton) {
    copyButton.addEventListener('click', copyPixCode);
  }
  
  // Set up chat payment buttons
  setupChatPaymentButtons();
});