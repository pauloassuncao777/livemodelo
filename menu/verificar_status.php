<?php
header('Content-Type: application/json');
include 'database.php';

$transaction_id = $_GET['transaction_id'] ?? null;

if (!$transaction_id) {
    echo json_encode(['error' => 'ID da transação não fornecido']);
    exit;
}

try {
    $pdo = connectDatabase();
    $stmt = $pdo->prepare("SELECT status FROM transactions WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        echo json_encode(['status' => $transaction['status']]);
    } else {
        echo json_encode(['error' => 'Transação não encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao verificar status: ' . $e->getMessage()]);
}
?>