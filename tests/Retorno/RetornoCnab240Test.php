<?php

namespace Retorno;

use Illuminate\Support\Collection;
use Adautopro\LaravelBoleto\Tests\TestCase;
use Adautopro\LaravelBoleto\Cnab\Retorno\Cnab240\Detalhe;
use Adautopro\LaravelBoleto\Exception\ValidationException;

class RetornoCnab240Test extends TestCase
{
    public function testRetornoSantanderCnab240()
    {
        $retorno = \Adautopro\LaravelBoleto\Cnab\Retorno\Factory::make(__DIR__ . '/files/cnab240/santander.ret');
        $retorno->processar();

        $this->assertNotNull($retorno->getHeader());
        $this->assertNotNull($retorno->getHeaderLote());
        $this->assertNotNull($retorno->getDetalhes());
        $this->assertNotNull($retorno->getTrailerLote());
        $this->assertNotNull($retorno->getTrailer());

        $this->assertEquals('Banco Santander (Brasil) S.A.', $retorno->getBancoNome());
        $this->assertEquals('033', $retorno->getCodigoBanco());

        $this->assertInstanceOf(Collection::class, $retorno->getDetalhes());

        $this->assertInstanceOf(Detalhe::class, $retorno->getDetalhe(1));

        foreach ($retorno->getDetalhes() as $detalhe) {
            $this->assertInstanceOf(Detalhe::class, $detalhe);
            $this->assertArrayHasKey('numeroDocumento', $detalhe->toArray());
        }
    }

    public function testRetornoSemDetalheCnab240()
    {
        $this->expectException(ValidationException::class);
        $retorno = \Adautopro\LaravelBoleto\Cnab\Retorno\Factory::make(__DIR__ . '/files/cnab240/retorno_sem_detalhe.ret');
        $retorno->processar();
    }
}
