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
        
        // Adicionar loading ao clicar em links com profile
        document.addEventListener('click', function(e) {
            const profile = e.target.closest('.profile');
            if (profile && profile.hasAttribute('data-redirect')) {
                const url = profile.getAttribute('data-redirect');
                if (url) {
                    e.preventDefault();
                    // Criar elemento de loading
                    const pageLoader = document.createElement('div');
                    pageLoader.className = 'page-loader';
                    pageLoader.innerHTML = `
                        <img src="logo400x130.png" alt="Logo">
                        <div class="loader"></div>
                    `;
                    document.body.appendChild(pageLoader);
                    
                    // Redirecionar com atraso para mostrar o loading
                    setTimeout(function() {
                        window.location.href = url + '&loading=1';
                    }, 500);
                }
            }
        });
    });
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
        
        :root {
            --primary: #9c0050;
            --primary-dark: #7a003c;
            --primary-light: #d4267e;
            --accent: #ffbe00;
            --accent-dark: #e29c00;
            --dark: #151515;
            --darker: #0a0a0a;
            --light: #f8f8f8;
            --gray: #333333;
            --gray-light: #555555;
            --online: #1eb980;
            --offline: #757575;
            --verified: #00c969;
            --gradient-start: rgba(156, 0, 80, 1);
            --gradient-end: rgba(210, 38, 126, 0.9);
            --tiktok-red: #fe2c55;
            --tiktok-blue: #25f4ee;
            --tiktok-dark: #010101;
            --tiktok-light: #e9e9e9;
            --status-pending: #ff9800;
            --live-badge: #ff3366;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        
        body {
            background-color: var(--dark);
            color: var(--light);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Header styling with gradient and shadow */
        .header {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .menu-icon {
            display: flex;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        
        .menu-icon:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .menu-icon div {
            width: 6px;
            height: 6px;
            background-color: white;
            border-radius: 50%;
            box-shadow: 0 0 4px rgba(255, 255, 255, 0.5);
        }
        
        .logo-text {
             width: 180px;
             height: 60px;
             display: block;
             background-size: contain;
             background-repeat: no-repeat;
             background-position: center;
        }
        
        .search-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .search-icon:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        /* Navigation tabs with improved styling */
        .nav-tabs {
            display: flex;
            background-color: var(--darker);
            border-bottom: 1px solid #333;
            position: sticky;
            top: 70px;
            z-index: 99;
        }
        
        .tab {
            padding: 15px 20px;
            text-transform: uppercase;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            letter-spacing: 1px;
            font-size: 14px;
            flex-grow: 1;
            text-align: center;
        }
        
        .tab:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--accent);
        }
        
        .tab.active {
            color: var(--accent);
            font-weight: 700;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, var(--accent-dark), var(--accent));
            box-shadow: 0 0 8px var(--accent);
        }
        
        /* Filters section with improved styling */
        .filters-container {
            background-color: var(--darker);
            padding: 5px 0;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .filters {
            display: flex;
            padding: 15px 40px;
            gap: 12px;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .filters::-webkit-scrollbar {
            display: none;
        }
        
        .filter-button {
            padding: 8px 20px;
            border-radius: 20px;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            font-weight: 500;
            border: none;
            outline: none;
        }
        
        .filter-button.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(212, 38, 126, 0.4);
        }
        
        .filter-button:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
            color: #d8d8d8;
        }
        
        .filter-button:not(.active):hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }
        
        .nav-arrow:hover {
            background: rgba(156, 0, 80, 0.8);
            box-shadow: 0 0 10px rgba(156, 0, 80, 0.5);
        }
        
        .left-arrow {
            left: 5px;
        }
        
        .right-arrow {
            right: 5px;
        }
        
        /* View options styling */
        .view-options {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 15px;
        }
        
        .view-option {
            width: 36px;
            height: 36px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .view-option:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .view-option.active {
            background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
            box-shadow: 0 2px 10px rgba(255, 190, 0, 0.3);
        }
        
        .grid-icon {
            display: grid;
            grid-template-columns: repeat(2, 5px);
            grid-template-rows: repeat(2, 5px);
            gap: 3px;
        }
        
        .grid-icon div {
            background-color: white;
            width: 100%;
            height: 100%;
            border-radius: 1px;
        }
        
        .grid3-icon {
            display: grid;
            grid-template-columns: repeat(3, 4px);
            grid-template-rows: repeat(3, 4px);
            gap: 2px;
        }
        
        .grid3-icon div {
            background-color: white;
            width: 100%;
            height: 100%;
            border-radius: 1px;
        }
        
        /* Profiles grid with improved styling */
        .profiles-container {
            padding: 10px 15px 30px;
        }
        
        .profiles {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Simplificado para 2 colunas consistentes */
            gap: 15px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 5px; /* Evita que o conteúdo encoste nas bordas */
        }
        
        .profile {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--gray);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            cursor: pointer;
            width: 100%; /* Garante que o perfil ocupe 100% da largura disponível */
        }
        
        .profile:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
        }
        
        /* Estilos para perfis recomendados */
        .profile.recommended {
            grid-column: 1 / -1; /* Ocupa todas as colunas disponíveis */
            grid-row: span 1; /* Reduzido para mobile */
            transform: scale(1.02); /* Escala reduzida para melhor visualização */
            box-shadow: 0 8px 25px rgba(255, 190, 0, 0.3);
            z-index: 2;
        }
        
        .profile.recommended:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 30px rgba(255, 190, 0, 0.4);
        }
        
        .recommended-badge {
            position: absolute;
            top: 9px;
            right: 64px;
            background-color: rgb(180 24 104 / 58%);
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 3;
            box-shadow: 0 4px 10px rgb(207 98 154 / 92%);
        }
        
        .profile-image-container {
            position: relative;
            overflow: hidden;
            height: 310px;
            display: block;
        }
        
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .profile:hover .profile-image {
            transform: scale(1.05);
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            align-items: center;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 2;
        }
        
        .profile-name {
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .brazil-flag {
            width: 16px;
            height: 12px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><g fill-rule="evenodd" stroke-width="1pt"><path fill="%23229e45" d="M0 0h640v480H0z"/><path fill="%23f8e509" d="M321.4 436l301.5-195.7L319.6 44 17.1 240.7 321.4 436z"/><path fill="%232b49a3" d="M452.8 240c0 70.3-57.1 127.3-127.6 127.3A127.4 127.4 0 1 1 452.8 240z"/><path fill="%23ffffef" d="M283.3 316.3l-4-2.3-4 2 .9-4.5-3.2-3.4 4.5-.5 2.2-4 1.9 4.2 4.4.8-3.3 3m86 26.3l-3.9-2.3-4 2 .8-4.5-3.1-3.3 4.5-.5 2.1-4.1 2 4.2 4.4.8-3.4 3.1m-36.2-30l-3.4-2-3.5 1.8.8-3.9-2.8-2.9 4-.4 1.8-3.6 1.6 3.7 3.9.7-3 2.7m87-8.5l-3.4-2-3.5 1.8.8-3.9-2.7-2.8 3.9-.4 1.8-3.5 1.6 3.6 3.8.7-2.9 2.6m-87.3-22l-4-2.2-4 2 .8-4.6-3.1-3.3 4.5-.5 2.1-4.1 2 4.2 4.4.8-3.4 3.2m-104.6-35l-4-2.2-4 2 1-4.6-3.3-3.3 4.6-.5 2-4.1 2 4.2 4.4.8-3.3 3.2m13.3 57.2l-4-2.3-4 2 .9-4.5-3.2-3.3 4.5-.6 2.1-4 2 4.2 4.4.8-3.3 3.1m132-67.3l-3.6-2-3.6 1.8.8-4-2.8-3 4-.5 1.9-3.6 1.7 3.8 4 .7-3 2.7m-6.7 38.3l-2.7-1.6-2.9 1.4.6-3.2-2.2-2.3 3.2-.4 1.5-2.8 1.3 3 3 .5-2.2 2.2m-142.2 50.4l-2.7-1.5-2.7 1.3.6-3-2.1-2.2 3-.4 1.4-2.7 1.3 2.8 3 .5-2.3 2.1m200.2 15.3l-2.2-1.1-2.2 1 .5-2.3-1.7-1.6 2.3-.3 1.1-2 1 2 2.3.5-1.8 1.5"/><path fill="%23ffffef" d="M219.3 287.6l-2.7-1.5-2.7 1.3.6-3-2.1-2.2 3-.4 1.4-2.7 1.3 2.8 3 .5-2.3 2.1m35.2 6l-2.2-1.1-2.2 1 .5-2.3-1.7-1.7 2.3-.3 1.1-2 1 2.1 2.2.4-1.7 1.6m-25.1 10l-2.7-1.5-2.7 1.4.6-3.1-2.1-2.2 3-.4 1.4-2.7 1.3 2.8 3 .5-2.3 2.1m179.8-32.4l-2.2-1.1-2.2 1 .5-2.3-1.7-1.6 2.3-.3 1.1-2 1 2 2.2.5-1.7 1.5m-37 79.6l-2.7-1.5-2.7 1.4.6-3.1-2.1-2.2 3-.4 1.4-2.7 1.3 2.8 3 .5-2.3 2.1m65 23.5l-2.2-1.1-2.2 1 .5-2.3-1.7-1.6 2.3-.3 1.1-2 1 2 2.3.5-1.8 1.5m-15.7-37.7l-1.7-.9-1.7.8.4-1.8-1.3-1.3 1.8-.2.8-1.6.8 1.6 1.7.4-1.3 1.2m-128 17.8l-2.2-1.1-2.2 1 .5-2.3-1.7-1.6 2.3-.3 1.1-2 1 2 2.3.5-1.8 1.5"/><path fill="%23ffffef" d="M354.3 262l-2.2-1-2.2 1 .5-2.4-1.7-1.6 2.3-.3 1.1-2 1 2 2.2.5-1.7 1.6m-16 59.5l-1.8-.8-1.7.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m-94.1 29.3l-2.2-1.1-2.2 1 .5-2.3-1.7-1.6 2.3-.3 1.1-2 1 2 2.2.5-1.7 1.5m61.5-67.5l-1.8-.8-1.7.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m-89.8 42.7l-1.8-.8-1.8.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m-5.7-33.4l-1.8-.8-1.8.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m111-5.7l-1.8-.8-1.8.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m.6 49.7l-1.8-.8-1.8.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m-102-18.2l-1.8-.8-1.8.8.4-2-1.4-1.4 1.9-.3.9-1.6.8 1.6 1.9.3-1.4 1.3m152.4 13l-1.8-.8-1.7.8.4-2-1.4-1.4 1.9-.2.9-1.7.8 1.7 1.9.3-1.4 1.3m30.7-6.4l-1.3-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.3.6 1.3 1.4.2-1 1m-38.2-9.4l-1.2-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.2.6 1.3 1.4.2-1 1m-9.5 33.2l-1.3-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.2.6 1.3 1.4.2-1 1m15.7 21.3l-1.4-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.3.6 1.3 1.4.2-1 1"/><path fill="%23fff" d="M224.8 187.3l.8-1-1-.7.6-1.1-.9-.8 1.1-.4.2-1.2.8.9 1.1-.3-.5 1.2 1 .7-.3.2m-3.8 81.7l-1.8-.8-1.7.8.3-2-1.3-1.4 1.9-.2.8-1.7.8 1.7 1.9.3-1.4 1.3m-15.2-63.6l-1.4-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.3.6 1.3 1.4.2-1 1m52.5 10.3l-1.4-.6-1.3.6.3-1.5-1-1 1.4-.2.6-1.3.6 1.3 1.4.2-1 1"/></g></svg>');
            background-size: cover;
            background-position: center;
            display: inline-block;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            vertical-align: middle;
        }
        
        .profile-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            padding: 4px 10px;
            border-radius: 12px;
            background-color: rgba(0, 0, 0, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            z-index: 3;
            display: inline-block; /* Ensure it doesn't get cut off */
            white-space: nowrap; /* Prevent line breaks */
        }
        
        .profile-link:hover {
            background-color: rgba(0, 0, 0, 0.5);
            color: #fff;
        }
        
        .status-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 1px;
            z-index: 3;
        }
        
        .online {
            background: linear-gradient(to right, rgba(30, 185, 128, 0.9), rgba(30, 185, 128, 0.7));
            box-shadow: 0 -3px 10px rgba(30, 185, 128, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .offline {
            background: linear-gradient(to right, rgba(117, 117, 117, 0.9), rgba(117, 117, 117, 0.7));
            box-shadow: 0 -3px 10px rgba(0, 0, 0, 0.2);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        /* Online profile with green background */
        .profile.is-online {
            box-shadow: 0 0 15px rgba(30, 185, 128, 0.4);
            border: 2px solid var(--online);
        }
        
        /* Overlay effect on profile images */
        .profile-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, 
                rgba(0,0,0,0.1) 0%, 
                rgba(0,0,0,0) 40%, 
                rgba(0,0,0,0.2) 80%, 
                rgba(0,0,0,0.6) 100%);
            z-index: 1;
        }
        
        .profile:hover .profile-overlay {
            background: linear-gradient(to bottom, 
                rgba(0,0,0,0.2) 0%, 
                rgba(0,0,0,0) 40%, 
                rgba(0,0,0,0.3) 80%, 
                rgba(0,0,0,0.7) 100%);
        }
        
        /* Glow effects for active elements */
        .glow-effect {
            position: relative;
        }
        
        .glow-effect::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            border-radius: inherit;
            box-shadow: 0 0 20px var(--primary-light);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .glow-effect:hover::after {
            opacity: 1;
        }
        
        /* Live badge for online profiles */
        .live-badge {
            position: absolute;
            bottom: 50px; /* Position above the status indicator */
            left: 10px;
            background-color: var(--live-badge);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            z-index: 4;
            box-shadow: 0 2px 8px rgba(255, 51, 102, 0.5);
            letter-spacing: 0.5px;
        }
        
        /* Profile Popup */
        .profile-popup-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .profile-popup {
            width: 90%;
            max-width: 450px;
            background-color: var(--dark);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .profile-popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
            color: white;
            font-size: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--light);
        }
        
        .profile-popup-close:hover {
            background-color: var(--primary);
            transform: rotate(90deg);
        }
        
        .profile-popup-header {
            position: relative;
            height: 160px;
            overflow: hidden;
        }
        
        .profile-popup-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.7);
            margin-bottom: -80px;
        }
        
        .profile-popup-avatar {
            position: relative;
            z-index: 9;
            width: 150px; /* Aumentado em 50px (era 100px) */
            height: 150px; /* Aumentado em 50px (era 100px) */
            border-radius: 50%;
            border: 4px solid var(--dark);
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            margin: -75px auto 0; /* Ajustado para compensar o tamanho maior */
        }
        
        .profile-popup-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Etiqueta "AO VIVO" para perfis online */
        .live-indicator {
            position: absolute;
            right: 179px;
            top: 89%;
            transform: translateY(-50%);
            background-color: var(--live-badge);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: none; /* Escondido por padrão */
            animation: pulsate 1.5s infinite alternate;
            box-shadow: 0 0 10px var(--live-badge);
            z-index: 10;
        }

        @keyframes pulsate {
            0% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .profile-popup-content {
            padding: 20px 20px 20px;
        }
        
        .profile-popup-name {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .profile-popup-verified {
            color: var(--verified);
            font-size: 18px;
        }
        
        .verified-docs {
            margin: 10px auto;
            text-align: center;
            background: linear-gradient(to right, var(--verified), #62d97a);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(0, 201, 105, 0.3);
        }
        
        .verified-docs i {
            align-items: center;
            justify-content: center;
        }
        
        .profile-popup-stats {
            display: grid;
            grid-template-columns: repeat(3, 50%);
            gap: 10px;
            margin: 20px 0;
        }
        
        .profile-popup-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .profile-popup-stat-value {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-light);
        }
        
        .profile-popup-stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .profile-popup-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
        }
        
        .profile-popup-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-popup-info-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .profile-popup-info-value {
            font-size: 14px;
            font-weight: 600;
        }
        
        .profile-popup-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .profile-popup-button {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .profile-popup-button-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }
        
        .profile-popup-button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156, 0, 80, 0.4);
        }
        
        .profile-popup-button-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .profile-popup-button-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .profile-popup-button-following {
            background: linear-gradient(135deg, var(--online) 0%, var(--online) 100%);
            color: white;
        }
        
        /* TikTok Interface */
        .tiktok-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--tiktok-dark);
            z-index: 1000;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        
        .tiktok-header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px 0;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        
        .tiktok-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--light);
        }
        
        .tiktok-back {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light);
            font-size: 22px;
            cursor: pointer;
        }
        
        .tiktok-videos {
            flex: 1;
            overflow-y: auto;
            scroll-snap-type: y mandatory;
            height: calc(100% - 60px);
        }
        
        .tiktok-video-container {
            height: 100%;
            scroll-snap-align: start;
            position: relative;
            overflow: hidden;
        }
        
        .tiktok-video {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Alterado de cover para contain para não preencher toda a tela */
            position: absolute; /* Posicionamento absoluto para ficar atrás dos elementos */
            top: 0;
            left: 0;
            z-index: 1; /* Menor z-index para ficar no fundo */
            background-color: var(--tiktok-dark); /* Adicionado para garantir que o fundo seja visível */
        }
        
        .tiktok-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 80px;
            z-index: 2; /* Z-index maior para ficar à frente do vídeo */
        }
        
        .tiktok-user-info {
            padding: 0 15px 10px;
            z-index: 2; /* Garantir que todos os elementos estejam à frente do vídeo */
        }
        
        .tiktok-username {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }
        
        .tiktok-caption {
            font-size: 14px;
            margin-bottom: 10px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
            max-width: 80%;
        }
        
        .tiktok-music {
            display: flex;
            align-items: center;
            font-size: 14px;
            margin-bottom: 15px;
            text-shadow: 0 1px 3px rgb(240 0 34);
        }
        
        .tiktok-music-icon {
            width: 20px;
            height: 20px;
            background-color: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            animation: musicSpin 3s linear infinite;
        }
        
        @keyframes musicSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .tiktok-music-icon i {
            color: var(--tiktok-dark);
            font-size: 12px;
        }
        
        .tiktok-actions {
            position: absolute;
            right: 15px;
            bottom: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px; /* Reduced gap to bring profile pic closer to follow button */
            z-index: 2;
        }
        
        .tiktok-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .tiktok-action-icon {
            background-color: rgba(0, 0, 0, 0.3);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.2s;
        }
        
        .tiktok-action-icon:hover {
            background-color: rgba(0, 0, 0, 0.5);
            transform: scale(1.1);
        }
        
        .tiktok-action-count {
            font-size: 12px;
            font-weight: 600;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }
        
        .tiktok-user-profile {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px solid var(--light);
            overflow: hidden;
            margin-bottom: -15px; /* Reduced margin */
            position: relative;
            cursor: pointer;
        }
        
        .tiktok-user-profile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Estilo para usuários TikTok que estão ao vivo */
        .tiktok-user-profile.live-creator {
            animation: flash 1.5s infinite alternate;
        }
        
        .tiktok-user-profile .live-badge-tiktok {
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--live-badge);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 3;
        }
        
        @keyframes flash {
            0% { box-shadow: 0 0 0 2px var(--live-badge); }
            100% { box-shadow: 0 0 10px 2px var(--live-badge); }
        }
        
        .tiktok-follow-button {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #fe0024;
            color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 30px; /* Reduced margin */
            transition: transform 0.2s;
            z-index: 999;
        }
        
        .tiktok-follow-button:hover {
            transform: scale(1.1);
        }
        
        .tiktok-bottom-gradient {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
            z-index: 1;
        }
        
        /* VIP Popup Container */
        .vip-popup-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 3000;
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .vip-popup {
            width: 90%;
            max-width: 400px;
            background-color: var(--dark);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .vip-popup h3 {
            margin-bottom: 20px;
            color: white;
            font-size: 20px;
        }
        
        .vip-popup-button {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(156, 0, 80, 0.4);
        }
        
        .vip-popup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(156, 0, 80, 0.5);
        }
        
        /* Payment Container */
        .payment-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            color: #333;
            padding: 25px 25px 40px;
            border-radius: 25px 25px 0 0;
            z-index: 2000;
            transform: translateY(100%);
            transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.7);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .payment-container.active {
            transform: translateY(0);
        }
        
        .payment-container h2 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .payment-container h3 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #222;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .payment-container .divider {
            width: 50px;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            margin: 15px auto;
            border-radius: 2px;
        }
        
        .qr-code-container {
            width: 100%;
            max-width: 250px;
            margin: 15px auto;
            overflow: visible;
        }
        
        .qr-code-img {
            width: 100%;
            aspect-ratio: 1/1;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: white;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .qr-code-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .pix-code {
            width: 100%;
            max-width: 350px;
            padding: 12px 15px;
            border: 1px solid #eee;
            border-radius: 10px;
            font-size: 13px;
            margin: 15px 0;
            background-color: #f9f9f9;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .copy-button {
            background: linear-gradient(to bottom, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin: 20px 0;
            width: 100%;
            max-width: 300px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 51, 102, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .payment-note {
            font-size: 14px;
            color: #777;
            margin: 10px 0 20px;
            max-width: 350px;
            line-height: 1.5;
        }
        
        .status-payment {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
            width: 100%;
            max-width: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            background-color: var(--status-pending);
            color: white;
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.3);
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 2500;
            display: none;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            border-left: 3px solid var(--verified);
        }
        
        .loader {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .page-loader img {
            width: 200px;
            margin-bottom: 20px;
            animation: pulse 1.5s infinite ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Sidebar Menu */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background-color: var(--dark);
            z-index: 3000;
            transition: left 0.3s ease;
            box-shadow: 5px 0 20px rgba(0, 0, 0, 0.5);
            overflow-y: auto;
            padding: 20px 0;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 2999;
            display: none;
            backdrop-filter: blur(2px);
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logo {
            font-size: 22px;
            font-weight: 700;
            color: var(--light);
            letter-spacing: 1px;
        }
        
        .sidebar-close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .sidebar-close:hover {
            background-color: var(--primary);
        }
        
        .sidebar-content {
            padding: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu-item {
            margin-bottom: 15px;
        }
        
        .sidebar-menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--light);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar-menu-link:hover {
            background-color: var(--primary);
            transform: translateX(5px);
        }
        
        .sidebar-menu-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Responsive adjustments */
        @media (min-width: 1201px) {
            .profiles {
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
            }
            
            .profile.recommended {
                grid-column: span 2;
                grid-row: span 2;
                transform: scale(1.05);
            }
        }
        
        @media (min-width: 993px) and (max-width: 1200px) {
            .profiles {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
            
            .profile.recommended {
                grid-column: span 2;
                grid-row: span 1;
            }
        }
        
        @media (min-width: 769px) and (max-width: 992px) {
            .profiles {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
            
            .profile.recommended {
                grid-column: span 2;
                grid-row: span 1;
            }
        }
        
        @media (min-width: 577px) and (max-width: 768px) {
            .profiles {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 0 10px;
            }
            
            .profile.recommended {
                grid-column: 1 / -1;
                grid-row: span 1;
            }
            
            .logo-text {
                width: 150px;
                height: 50px;
            }
            
            .tab {
                padding: 12px 10px;
                font-size: 13px;
            }
            
            .profile-image-container {
                height: 310px;
            }
            
            .profile-popup-stats {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .profile-popup-avatar {
                width: 130px;
                height: 130px;
                margin: -65px auto 0;
            }
            
            .profile-header {
                padding: 10px 12px;
            }
            
            .profile-link {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
        
        @media (min-width: 401px) and (max-width: 576px) {
            .profiles {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 0 8px;
            }
            
            .profile.recommended {
                grid-column: 1 / -1;
                grid-row: span 1;
                transform: scale(1);
            }
            
            .logo-text {
                width: 120px;
                height: 40px;
            }
            
            .filter-button {
                padding: 6px 15px;
                font-size: 12px;
            }
            
            .tab {
                padding: 10px 5px;
                font-size: 12px;
            }
            
            .tiktok-action-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            
            .profile-image-container {
                height: 310px;
            }
            
            .profile-name {
                font-size: 12px;
            }
            
            .profile-popup-avatar {
                width: 110px;
                height: 110px;
                margin: -55px auto 0;
            }
            
            .profile-popup-content {
                padding: 15px 12px 15px;
            }
            
            .profile-popup-name {
                font-size: 16px;
            }
            
            .profile-popup-stats {
                margin: 12px 0;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .profile-popup-stat-value {
                font-size: 14px;
            }
            
            .profile-popup-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .profile-header {
                padding: 8px 10px; 
                background: linear-gradient(to bottom, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.2) 100%);
            }
            
            .profile-link {
                padding: 3px 7px;
                font-size: 11px;
                background-color: rgba(0, 0, 0, 0.5);
            }
            
            .recommended-badge {
                right: 64px;
                top: 9px;
                font-size: 10px;
                padding: 3px 6px;
            }
        }
        
        @media (max-width: 400px) {
            .profiles {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 0 10px;
            }
            
            .profile.recommended {
                grid-column: 1;
                transform: scale(1);
            }
            
            .profile-image-container {
                height: 310px;
            }
            
            .profile-popup-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .recommended-badge {
                right: 73px;
                top: 15px;
                font-size: 10px;
                padding: 3px 6px;
            }
        }
    </style>
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
            <div class="tiktok-video-container" id="video-1">
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
        <input type="hidden" id="chat-transaction-id" value="<?php echo $chatTransactionId; ?>"
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
            setupProfileClicks();
        });
        
        // Setup Profile Clicks - Implementa o redirecionamento e abrir popup
        function setupProfileClicks() {
            document.querySelectorAll('.profile').forEach(profile => {
                profile.addEventListener('click', function(e) {
                    // Ignorar clique se for no botão de perfil
                    if (e.target.classList.contains('profile-link') || e.target.closest('.profile-link')) {
                        return;
                    }
                    
                    // Captura o nome do perfil para o cookie
                    const profileName = this.getAttribute('data-profile');
                    if (profileName) {
                        // Verifica se está definida a função de cookie do VIP
                        if (typeof setProfileVisitedCookie === 'function') {
                            setProfileVisitedCookie(profileName);
                        }
                    }
                    
                    const status = this.getAttribute('data-status');
                    
                    // Se tiver overlay de VIP, não redireciona
                    if (this.querySelector('.vip-lock-overlay')) {
                        return;
                    }
                    
                    if (status === 'online') {
                        // Redirecionar para o URL específico
                        const redirectUrl = this.getAttribute('data-redirect');
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                        }
                    } else {
                        // Mostrar popup de perfil para OFFLINE
                        showProfilePopup(profileName);
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
                document.getElementById('profile-popup-live-hours').textContent = profile.liveHours;
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