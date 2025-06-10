<?php

require 'autoload.php';

$beneficiario = new Adautopro\LaravelBoleto\Pessoa([
    'nome'      => 'ACME',
    'endereco'  => 'Rua um, 123',
    'cep'       => '99999-999',
    'uf'        => 'UF',
    'cidade'    => 'CIDADE',
    'documento' => '99.999.999/9999-99',
]);

$api = new Adautopro\LaravelBoleto\Api\Banco\Inter([
    'conta'            => '123456789',
    'certificado'      => realpath(__DIR__ . '/certs/') . DIRECTORY_SEPARATOR . 'cert.crt',
    'certificadoChave' => realpath(__DIR__ . '/certs/') . DIRECTORY_SEPARATOR . 'key.key',
]);

$retorno = $api->retrieveList();

dd($retorno);
//$pdf = new Adautopro\LaravelBoleto\Boleto\Render\Pdf();
//$pdf->addBoletos($retorno);
//$pdf->gerarBoleto($pdf::OUTPUT_SAVE, __DIR__ . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'inter_lista_v2.pdf');
