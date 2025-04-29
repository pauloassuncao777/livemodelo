<?php
class BSPayAPI {
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $accessToken;

    public function __construct($clientId, $clientSecret, $baseUrl = 'https://api.bspay.co/v2/') {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $baseUrl;
        $this->accessToken = null;
    }

    private function getAccessToken() {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $url = $this->baseUrl . 'oauth/token';
        $encodedCredentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic $encodedCredentials",
                "Content-Type: application/x-www-form-urlencoded"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Erro ao autenticar: HTTP $httpCode - $response");
        }

        $response_data = json_decode($response, true);
        $this->accessToken = $response_data['access_token'] ?? null;

        if ($this->accessToken === null) {
            throw new Exception("Token de acesso não encontrado na resposta");
        }

        return $this->accessToken;
    }

    public function gerarQrCodePix($valor, $nome, $cpf, $randid) {
        $url = $this->baseUrl . 'pix/qrcode';
        $postData = json_encode([
            "amount" => $valor,
            "payerQuestion" => "Obrigadinha",
            "external_id" => $randid,
            "postbackUrl" => "https://chat-whatsapp.livemodelo.com/webhook.php",
            "payer" => [
                "name" => $nome,
                "document" => $cpf,
                "email" => "contato@larissa.santos"
            ]
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->getAccessToken(),
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Erro ao gerar o PIX: HTTP $httpCode - $response");
        }

        return json_decode($response, true);
    }

    public function verificarStatusPagamento($transactionId) {
        $url = $this->baseUrl . 'pix/payment/status/' . $transactionId;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->getAccessToken(),
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Erro ao verificar status: HTTP $httpCode - $response");
        }

        $json = json_decode($response, true);
        if ($json === null) {
            throw new Exception("Erro ao decodificar resposta JSON: $response");
        }

        return $json;
    }
}
?>
