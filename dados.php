<?php

$dados = [
    ["cpf" => "03051241024", "name" => "Thais Santos"],
    ["cpf" => "03052016113", "name" => "Thais Santos"],
    ["cpf" => "03052299581", "name" => "Thais Santos"],
    ["cpf" => "03075669008", "name" => "Thais Santos"],
    ["cpf" => "03076228005", "name" => "Thais Santos"],
    ["cpf" => "03106553073", "name" => "Thais Santos"],
    ["cpf" => "03109539284", "name" => "Thais Santos"],
    ["cpf" => "03111846504", "name" => "Thais Santos"],
    ["cpf" => "03112361547", "name" => "Thais Santos"],
    ["cpf" => "03112576586", "name" => "Thais Santos"],
    ["cpf" => "03117349014", "name" => "Thais Santos"],
    ["cpf" => "03120356247", "name" => "Thais Santos"],
    ["cpf" => "03131172509", "name" => "Thais Santos"],
    ["cpf" => "03158855569", "name" => "Thais Santos"],
    ["cpf" => "03167991062", "name" => "Thais Santos"],
    ["cpf" => "03170486519", "name" => "Thais Santos"],
    ["cpf" => "03173373510", "name" => "Thais Santos"],
    ["cpf" => "03183673517", "name" => "Thais Santos"],
    ["cpf" => "03194482319", "name" => "Thais Santos"],
    ["cpf" => "03196867285", "name" => "Thais Santos"],
    ["cpf" => "03197004027", "name" => "Thais Santos"],
    ["cpf" => "03212800593", "name" => "Thais Santos"],
    ["cpf" => "03226834592", "name" => "Thais Santos"],
    ["cpf" => "03227875217", "name" => "Thais Santos"],
    ["cpf" => "03230183274", "name" => "Thais Santos"],
    ["cpf" => "03232370292", "name" => "Thais Santos"],
    ["cpf" => "03235965505", "name" => "Thais Santos"],
    ["cpf" => "03236423145", "name" => "Thais Santos"],
    ["cpf" => "03239717093", "name" => "Thais Santos"],
    ["cpf" => "03243807204", "name" => "Thais Santos"],
    ["cpf" => "03254315588", "name" => "Thais Santos"],
    ["cpf" => "03255582130", "name" => "Thais Santos"],
    ["cpf" => "03259789480", "name" => "Thais Santos"],
    ["cpf" => "03262231509", "name" => "Thais Santos"],
    ["cpf" => "03267187532", "name" => "Thais Santos"],
    ["cpf" => "03268257542", "name" => "Thais Santos"],
    ["cpf" => "03268603556", "name" => "Thais Santos"],
    ["cpf" => "03279084083", "name" => "Thais Santos"],
    ["cpf" => "03290100502", "name" => "Thais Santos"],
    ["cpf" => "03303503230", "name" => "Thais Santos"],
    ["cpf" => "03304613544", "name" => "Thais Santos"],
    ["cpf" => "03308001080", "name" => "Thais Santos"],
    ["cpf" => "03311921011", "name" => "Thais Santos"],
    ["cpf" => "03312083508", "name" => "Thais Santos"],
    ["cpf" => "03325106544", "name" => "Thais Santos"],
    ["cpf" => "03327271577", "name" => "Thais Santos"],
    ["cpf" => "03329408022", "name" => "Thais Santos"],
    ["cpf" => "03329701595", "name" => "Thais Santos"],
    ["cpf" => "03336714141", "name" => "Thais Santos"],
    ["cpf" => "03343439509", "name" => "Thais Santos"],
    ["cpf" => "03352452008", "name" => "Thais Santos"],
    ["cpf" => "03353902007", "name" => "Thais Santos"],
    ["cpf" => "03357802538", "name" => "Thais Santos"],
    ["cpf" => "03357956500", "name" => "Thais Santos"],
    ["cpf" => "03365910042", "name" => "Thais Santos"],
    ["cpf" => "03366532122", "name" => "Thais Santos"],
    ["cpf" => "03371116506", "name" => "Thais Santos"],
    ["cpf" => "03372155106", "name" => "Thais Santos"],
    ["cpf" => "03373824508", "name" => "Thais Santos"],
    ["cpf" => "03385168244", "name" => "Thais Santos"],
    ["cpf" => "03385233593", "name" => "Thais Santos"],
    ["cpf" => "03387581190", "name" => "Thais Santos"],
    ["cpf" => "03389569219", "name" => "Thais Santos"]
];

function sortearDados($dados) {
    $indice = array_rand($dados);
    return $dados[$indice];
}

$dadosSorteados = sortearDados($dados);

header('Content-Type: application/json');
echo json_encode($dadosSorteados, JSON_UNESCAPED_UNICODE);
?>
