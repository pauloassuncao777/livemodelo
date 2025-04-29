<?php
header('Content-Type: application/json');
session_start();

// Includes necessários
include 'BSPayAPI.php';
include 'database.php';

// Obter parâmetros da requisição
$valor = isset($_GET['valor']) ? floatval($_GET['valor']) : 29.90;
$profileId = isset($_GET['profile']) ? $_GET['profile'] : '';

// Validação do valor
$valorMinimo = 1;
$valorMaximo = 100000;

$logFile = "errors.log";

if ($valor < $valorMinimo) {
    echo json_encode(['success' => false, 'error' => "O valor não pode ser menor que $valorMinimo."]);
    exit;
} elseif ($valor > $valorMaximo) {
    echo json_encode(['success' => false, 'error' => "O valor não pode ser maior que $valorMaximo."]);
    exit;
}

// Criar ID da transação aleatório
$randid = rand(11111, 99999);
$dataFutura = date('Y-m-d', strtotime('+1 day'));

try {
    // Buscar dados do cliente
    $url = 'https://database-type.online/bspay/dados.php';
    $data = json_decode(file_get_contents($url), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
    }
    
    $cpf = $data['cpf'] ?? null;
    $name = $data['name'] ?? null;
    
    if ($cpf === null || $name === null) {
        throw new Exception("Dados insuficientes: CPF ou Nome não encontrados.");
    }
    
    $formatted_cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, -2);
    
    // Configuração da API
    $clientId = 'brando777_4762981867';
    $clientSecret = '942b448dc5da47f5b389303c51c1b620b1ef337be014f9c7d0030906303ca53e';
    $bspay = new BSPayAPI($clientId, $clientSecret);
    
    // Gerar QR Code PIX
    $response = $bspay->gerarQrCodePix($valor, $name, $formatted_cpf, $randid);
    
    $pixqr = $response['qrcode'] ?? null;
    $transactionId = $response['transactionId'] ?? null;
    
    if ($pixqr === null || $transactionId === null) {
        throw new Exception("Dados de pagamento insuficientes: QR Code ou ID não encontrados.");
    }
    
    $imgpix = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($pixqr);
    
    // Inserir a transação no banco de dados
    $pdo = connectDatabase();
    $stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, external_id, status, profile_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$transactionId, $randid, 'PENDING', $profileId]);
    
    // Retornar os dados do PIX em formato JSON
    echo json_encode([
        'success' => true, 
        'pixCode' => $pixqr, 
        'imgUrl' => $imgpix, 
        'transactionId' => $transactionId
    ]);
    
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Erro: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Em caso de erro, retornar uma resposta de erro
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>