<?php
session_start();

// Permitir CORS e tratar requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json');
    exit(0); // Finaliza a resposta para o preflight
}

// Configurar os cabeçalhos CORS para todas as requisições
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json'); // Define o cabeçalho para JSON

// Valida se o 'valor' foi passado na URL corretamente
if (!isset($_GET['valor']) || !is_numeric($_GET['valor'])) {
    echo json_encode(['message' => 'Valor inválido ou não informado.']);
    exit();
}

$valor = floatval($_GET['valor']);
if ($valor < 1 || $valor > 100000) {
    echo json_encode(['message' => 'Valor fora do limite permitido.']);
    exit();
}

include 'BSPayAPI-7.php';
include 'database.php';

$randid = rand(11111, 99999);

// Busca dados na API externa com tratamento de erro
$url = 'https://chat-whatsapp.bugylrbiykfbevlust2al3.com/dados.php';

// Adicionando context para evitar warnings em `file_get_contents`
$options = [
    "http" => [
        "method" => "GET",
        "header" => "Accept: application/json\r\n"
    ]
];
$context = stream_context_create($options);
$responseData = @file_get_contents($url, false, $context);
$data = $responseData ? json_decode($responseData, true) : null;

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['message' => 'Erro ao buscar dados na API externa.']);
    exit();
}

// Verifica se os dados necessários existem
$cpf = $data['cpf'] ?? null;
$name = $data['name'] ?? null;

if (!$cpf || !$name) {
    echo json_encode(['message' => 'Dados insuficientes da API externa.']);
    exit();
}

$cpf_formatado = preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $cpf);

// Defina as credenciais da BSPay
$clientId = 'brando777_4762981867';
$clientSecret = '942b448dc5da47f5b389303c51c1b620b1ef337be014f9c7d0030906303ca53e';

$bspay = new BSPayAPI($clientId, $clientSecret);

try {
    $response = $bspay->gerarQrCodePix($valor, $name, $cpf_formatado, $randid);
    if (!$response || empty($response['qrcode']) || empty($response['transactionId'])) {
        throw new Exception("Resposta inválida da BSPay");
    }
} catch (Exception $e) {
    echo json_encode(['message' => 'Erro ao gerar pagamento.', 'error' => $e->getMessage()]);
    exit();
}

$pixqr = $response['qrcode'];
$transactionId = $response['transactionId'];

$pdo = connectDatabase();
$stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, external_id, status) VALUES (?, ?, ?)");
$stmt->execute([$transactionId, $randid, 'PENDING']);

// Retorna apenas o código Pix em formato JSON
echo json_encode([
    'success' => true,
    'pix' => $pixqr,
    'transaction_id' => $transactionId
]);
?>