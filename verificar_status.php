<?php
include 'database.php';

$transactionId = $_GET['transaction_id'] ?? null;
$logFile = "verificar_status.log";

if ($transactionId === null) {
    file_put_contents($logFile, "ID da transação não foi informado." . PHP_EOL, FILE_APPEND);
    echo json_encode(['message' => 'ID da transação não foi informado.']);
    exit;
}

$pdo = connectDatabase();
$stmt = $pdo->prepare("SELECT status FROM transactions WHERE transaction_id = ?");
$stmt->execute([$transactionId]);
$status = $stmt->fetchColumn();

if ($status) {
    file_put_contents($logFile, "Status da transação: $status" . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => $status]);
} else {
    file_put_contents($logFile, "Transação não encontrada. ID: $transactionId" . PHP_EOL, FILE_APPEND);
    echo json_encode(['message' => 'Transação não encontrada.']);
}
?>
