<?php

namespace Adautopro\LaravelBoleto\Contracts;

interface Pessoa
{
    public function getTipo();

    public function getNome();

    public function getNomeDocumento();

    public function getTipoDocumento();

    public function getDocumento();

    public function getBairro();

    public function getEndereco();

    public function getCepCidadeUf();

    public function getEnderecoCompleto();

    public function getCep();

    public function getCidade();

    public function getUf();

    public function getEmail();

    public function isDda();

    public function toArray();
}
