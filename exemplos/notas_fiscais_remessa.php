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

$pagador = new Adautopro\LaravelBoleto\Pessoa([
    'nome'      => 'Cliente',
    'endereco'  => 'Rua um, 123',
    'bairro'    => 'Bairro',
    'cep'       => '99999-999',
    'uf'        => 'UF',
    'cidade'    => 'CIDADE',
    'documento' => '999.999.999-99',
]);

$boleto = new Adautopro\LaravelBoleto\Boleto\Banco\Abc([
    'logo'                   => realpath(__DIR__ . '/../logos/') . DIRECTORY_SEPARATOR . '246.png',
    'dataVencimento'         => new Carbon\Carbon(),
    'valor'                  => 100,
    'multa'                  => false,
    'juros'                  => false,
    'numero'                 => '0004309540',
    'numeroDocumento'        => 1,
    'descricaoDemonstrativo' => ['demonstrativo 1', 'demonstrativo 2', 'demonstrativo 3'],
    'instrucoes'             => ['instrucao 1', 'instrucao 2', 'instrucao 3'],
    'aceite'                 => 'S',
    'especieDoc'             => 'DM',
    'pagador'                => $pagador,
    'beneficiario'           => $beneficiario,
    'carteira'               => 6,
    'operacao'               => 1234567,
    'agencia'                => '0001',
    'conta'                  => '7654321',
    'notas_fiscais'          => [
        ['chave' => '12345678901234567890123456789012345678901234', 'numero' => 1, 'data' => new Carbon\Carbon(), 'valor' => 100],
        Adautopro\LaravelBoleto\NotaFiscal::create('12345678901234567890123456789012345678901235', 2, new Carbon\Carbon(), 100),
        new Adautopro\LaravelBoleto\NotaFiscal(['chave' => '12345678901234567890123456789012345678901236', 'numero' => 3, 'data' => new Carbon\Carbon(), 'valor' => 100]),
    ],
]);

$remessa = new Adautopro\LaravelBoleto\Cnab\Remessa\Cnab400\Banco\Abc([
    'agencia'       => '0001',
    'conta'         => '7654321',
    'carteira'      => 6,
    'codigoCliente' => '00011234567',
    'beneficiario'  => $beneficiario,
]);
$remessa->addBoleto($boleto);

echo '<pre>';
echo $remessa->gerar();
