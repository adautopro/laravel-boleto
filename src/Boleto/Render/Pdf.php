<?php

namespace Adautopro\LaravelBoleto\Boleto\Render;

use Illuminate\Support\Str;
use Adautopro\LaravelBoleto\Util;
use Adautopro\LaravelBoleto\Exception\ValidationException;
use Adautopro\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Adautopro\LaravelBoleto\Contracts\Boleto\Render\Pdf as PdfContract;

class Pdf extends AbstractPdf implements PdfContract
{
    const OUTPUT_STANDARD = 'I';
    const OUTPUT_DOWNLOAD = 'D';
    const OUTPUT_SAVE = 'F';
    const OUTPUT_STRING = 'S';
    const PIX_INSTRUCAO = 'instrucao';
    const PIX_COD_BARRAS = 'barras';

    protected $PadraoFont = 'Arial';

    /**
     * @var BoletoContract[]
     */
    protected $boleto = [];

    /**
     * @var bool
     */
    protected $print = false;

    /**
     * @var bool
     */
    protected $showInstrucoes = true;

    protected $desc = 3; // tamanho célula descrição
    protected $cell = 4; // tamanho célula dado
    protected $fdes = 6; // tamanho fonte descrição
    protected $fcel = 8; // tamanho fonte célula
    protected $small = 0.2; // tamanho barra fina
    protected $totalBoletos = 0;
    protected $localizacao_pix = self::PIX_INSTRUCAO;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(20);
        $this->SetTopMargin(15);
        $this->SetRightMargin(20);
        $this->SetLineWidth($this->small);
    }

    /**
     * @param $localizacao
     * @return Pdf
     * @throws ValidationException
     */
    public function setLocalizacaoPix($localizacao)
    {
        if (! in_array($localizacao, [self::PIX_COD_BARRAS, self::PIX_INSTRUCAO])) {
            throw new ValidationException('Pix deve ser Pdf::PIX_COD_BARRAS ou Pdf::PIX_INSTRUCAO');
        }
        $this->localizacao_pix = $localizacao;
        return $this;
    }

    /**
     * @param int $i
     * @return Pdf
     */
    protected function instrucoes($i)
    {
        $this->SetFont($this->PadraoFont, '', 8);
        if ($this->totalBoletos > 1) {
            $this->SetAutoPageBreak(true);
            $this->SetY(5);
            $this->Cell(30, 10, date('d/m/Y H:i:s'));
            $this->Cell(0, 10, 'Boleto ' . ($i + 1) . ' de ' . $this->totalBoletos, 0, 1, 'R');
        }

        $this->SetFont($this->PadraoFont, 'B', 8);
        if ($this->showInstrucoes) {
            $this->Cell(0, 5, $this->_('Instruções de Impressão'), 0, 1, 'C');
            $this->Ln(5);
            $this->SetFont($this->PadraoFont, '', 6);
            if (count($this->boleto[$i]->getInstrucoesImpressao()) > 0) {
                $this->listaLinhas($this->boleto[$i]->getInstrucoesImpressao(), 0);
            } else {
                $this->Cell(0, $this->desc, $this->_('- Imprima em impressora jato de tinta (ink jet) ou laser em qualidade normal ou alta (Não use modo econômico).'), 0, 1, 'L');
                $this->Cell(0, $this->desc, $this->_('- Utilize folha A4 (210 x 297 mm) ou Carta (216 x 279 mm) e margens mínimas à esquerda e à direita do formulário.'), 0, 1, 'L');
                $this->Cell(0, $this->desc, $this->_('- Corte na linha indicada. Não rasure, risque, fure ou dobre a região onde se encontra o código de barras.'), 0, 1, 'L');
                $this->Cell(0, $this->desc, $this->_('- Caso não apareça o código de barras no final, clique em F5 para atualizar esta tela.'), 0, 1, 'L');
                $this->Cell(0, $this->desc, $this->_('- Caso tenha problemas ao imprimir, copie a seqüencia numérica abaixo e pague no caixa eletrônico ou no internet banking:'), 0, 1, 'L');
            }
            $this->Ln(4);

            $this->SetFont($this->PadraoFont, '', $this->fcel);
            $this->Cell(25, $this->cell, $this->_('Linha Digitável: '), 0, 0);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(0, $this->cell, $this->_($this->boleto[$i]->getLinhaDigitavel()), 0, 1);
            $this->SetFont($this->PadraoFont, '', $this->fcel);
            $this->Cell(25, $this->cell, $this->_('Número: '), 0, 0);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(0, $this->cell, $this->_($this->boleto[$i]->getNumero()), 0, 1);
            $this->SetFont($this->PadraoFont, '', $this->fcel);
            $this->Cell(25, $this->cell, $this->_('Valor: '), 0, 0);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(0, $this->cell, $this->_(Util::nReal($this->boleto[$i]->getValor())), 0, 1);
            $this->SetFont($this->PadraoFont, '', $this->fcel);
        }

        $this->traco('Recibo do Pagador', 4);

        return $this;
    }

    /**
     * @param int $i
     *
     * @return Pdf
     */
    protected function logoEmpresa($i)
    {
        $this->Ln(2);
        $this->SetFont($this->PadraoFont, '', $this->fdes);

        $logo = preg_replace('/\&.*/', '', $this->boleto[$i]->getLogo());
        $ext = pathinfo($logo, PATHINFO_EXTENSION);

        if ($this->boleto[$i]->getLogo() && ! empty($this->boleto[$i]->getLogo())) {
            $this->Image($this->boleto[$i]->getLogo(), 20, ($this->GetY()), 0, 12, $ext);
        }
        $this->Cell(56);
        $this->Cell(0, $this->desc, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 0, 1);
        $this->Cell(56);
        $this->Cell(0, $this->desc, $this->_($this->boleto[$i]->getBeneficiario()->getDocumento(), '##.###.###/####-##'), 0, 1);
        $this->Cell(56);
        $this->Cell(0, $this->desc, $this->_($this->boleto[$i]->getBeneficiario()->getEndereco()), 0, 1);
        $this->Cell(56);
        $this->Cell(0, $this->desc, $this->_($this->boleto[$i]->getBeneficiario()->getCepCidadeUf()), 0, 1);
        $this->Ln(8);
        return $this;
    }

    /**
     * @param int $i
     * @return Pdf
     */
    protected function Topo($i)
    {
        $this->Image($this->boleto[$i]->getLogoBanco(), 20, ($this->GetY() - 2), 28);
        $this->Cell(29, 6, '', 'B');
        $this->SetFont('', 'B', 13);
        $this->Cell(15, 6, $this->boleto[$i]->getCodigoBancoComDv(), 'LBR', 0, 'C');
        $this->SetFont('', 'B', 10);
        $this->Cell(0, 6, $this->boleto[$i]->getLinhaDigitavel(), 'B', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(75, $this->desc, $this->_('Beneficiário'), 'TLR');
        $this->Cell(35, $this->desc, $this->_('Agência/Código do beneficiário'), 'TR');
        $this->Cell(10, $this->desc, $this->_('Espécie'), 'TR');
        $this->Cell(15, $this->desc, $this->_('Quantidade'), 'TR');
        $this->Cell(35, $this->desc, $this->_('Nosso Número'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);

        $this->textFitCell(75, $this->cell, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 'LR', 0, 'L');

        $this->Cell(35, $this->cell, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'R');
        $this->Cell(10, $this->cell, $this->_('R$'), 'R');
        $this->Cell(15, $this->cell, $this->_(''), 'R');
        $this->Cell(35, $this->cell, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'R', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(50, $this->desc, $this->_('Número do Documento'), 'TLR');
        $this->Cell(40, $this->desc, $this->_('CPF/CNPJ'), 'TR');
        $this->Cell(30, $this->desc, $this->_('Vencimento'), 'TR');
        $this->Cell(50, $this->desc, $this->_('Valor do Documento'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(50, $this->cell, $this->_($this->boleto[$i]->getNumeroDocumento()), 'LR');
        $this->Cell(40, $this->cell, $this->_($this->boleto[$i]->getBeneficiario()->getDocumento(), '##.###.###/####-##'), 'R');
        $this->Cell(30, $this->cell, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'R');
        $this->Cell(50, $this->cell, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'R', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(30, $this->desc, $this->_('(-) Descontos/Abatimentos'), 'TLR');
        $this->Cell(30, $this->desc, $this->_('(-) Outras Deduções'), 'TR');
        $this->Cell(30, $this->desc, $this->_('(+) Mora Multa'), 'TR');
        $this->Cell(30, $this->desc, $this->_('(+) Acréscimos'), 'TR');
        $this->Cell(50, $this->desc, $this->_('(=) Valor Cobrado'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(30, $this->cell, $this->_(''), 'LR');
        $this->Cell(30, $this->cell, $this->_(''), 'R');
        $this->Cell(30, $this->cell, $this->_(''), 'R');
        $this->Cell(30, $this->cell, $this->_(''), 'R');
        $this->Cell(50, $this->cell, $this->_(''), 'R', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(0, $this->desc, $this->_('Pagador'), 'TLR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(0, $this->cell, $this->_($this->boleto[$i]->getPagador()->getNomeDocumento()), 'BLR', 1);

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(100, $this->desc, $this->_('Demonstrativo'), 0, 0, 'L');
        $this->Cell(0, $this->desc, $this->_('Autenticação mecânica'), 0, 1, 'R');
        $this->Ln(2);

        $pulaLinha = 26;
        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        if (count($this->boleto[$i]->getDescricaoDemonstrativo()) > 0) {
            $pulaLinha = $this->listaLinhas($this->boleto[$i]->getDescricaoDemonstrativo(), $pulaLinha);
        }
        $this->traco('Corte na linha pontilhada', $pulaLinha, 10);
        return $this;
    }

    /**
     * @param int $i
     * @return Pdf
     */
    protected function Bottom($i)
    {
        $this->Image($this->boleto[$i]->getLogoBanco(), 20, ($this->GetY() - 2), 28);
        $this->Cell(29, 6, '', 'B');
        $this->SetFont($this->PadraoFont, 'B', 13);
        $this->Cell(15, 6, $this->boleto[$i]->getCodigoBancoComDv(), 'LBR', 0, 'C');
        $this->SetFont($this->PadraoFont, 'B', 10);
        $this->Cell(0, 6, $this->boleto[$i]->getLinhaDigitavel(), 'B', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(120, $this->desc, $this->_('Local de pagamento'), 'TLR');
        $this->Cell(50, $this->desc, $this->_('Vencimento'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(120, $this->cell, $this->_($this->boleto[$i]->getLocalPagamento()), 'LR');
        $this->Cell(50, $this->cell, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'R', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(120, $this->desc, $this->_('Beneficiário'), 'TLR');
        $this->Cell(50, $this->desc, $this->_('Agência/Código beneficiário'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(120, $this->cell, $this->_($this->boleto[$i]->getBeneficiario()->getNomeDocumento()), 'LR');
        $xBeneficiario = $this->GetX();
        $yBeneficiario = $this->GetY();
        $this->Cell(50, $this->cell, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'R', 1, 'R');
        if ($this->boleto[$i]->getMostrarEnderecoFichaCompensacao()) {
            $this->SetXY($xBeneficiario, $yBeneficiario);
            $this->Ln(4);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(120, $this->cell, $this->_($this->boleto[$i]->getBeneficiario()->getEnderecoCompleto()), 'LR');
            $this->Cell(50, $this->cell, '', 'R', 1, 'R');
        }

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(30, $this->desc, $this->_('Data do documento'), 'TLR');
        $this->Cell(40, $this->desc, $this->_('Número do documento'), 'TR');
        $this->Cell(15, $this->desc, $this->_('Espécie Doc.'), 'TR');
        $this->Cell(10, $this->desc, $this->_('Aceite'), 'TR');
        $this->Cell(25, $this->desc, $this->_('Data processamento'), 'TR');
        $this->Cell(50, $this->desc, $this->_('Nosso número'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(30, $this->cell, $this->_($this->boleto[$i]->getDataDocumento()->format('d/m/Y')), 'LR');
        $this->Cell(40, $this->cell, $this->_($this->boleto[$i]->getNumeroDocumento()), 'R');
        $this->Cell(15, $this->cell, $this->_($this->boleto[$i]->getEspecieDoc()), 'R');
        $this->Cell(10, $this->cell, $this->_($this->boleto[$i]->getAceite()), 'R');
        $this->Cell(25, $this->cell, $this->_($this->boleto[$i]->getDataProcessamento()->format('d/m/Y')), 'R');
        $this->Cell(50, $this->cell, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'R', 1, 'R');

        $this->SetFont($this->PadraoFont, '', $this->fdes);

        if (isset($this->boleto[$i]->variaveis_adicionais['esconde_uso_banco']) && $this->boleto[$i]->variaveis_adicionais['esconde_uso_banco']) {
            $this->Cell(55, $this->desc, $this->_('Carteira'), 'TLR');
        } else {
            $cip = isset($this->boleto[$i]->variaveis_adicionais['mostra_cip']) && $this->boleto[$i]->variaveis_adicionais['mostra_cip'];

            $this->Cell(($cip ? 23 : 30), $this->desc, $this->_('Uso do Banco'), 'TLR');
            if ($cip) {
                $this->Cell(7, $this->desc, $this->_('CIP'), 'TLR');
            }
            $this->Cell(25, $this->desc, $this->_('Carteira'), 'TR');
        }

        $this->Cell(12, $this->desc, $this->_('Espécie'), 'TR');
        $this->Cell(28, $this->desc, $this->_('Quantidade'), 'TR');
        $this->Cell(25, $this->desc, $this->_('Valor Documento'), 'TR');
        $this->Cell(50, $this->desc, $this->_('Valor Documento'), 'TR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);

        if (isset($this->boleto[$i]->variaveis_adicionais['esconde_uso_banco']) && $this->boleto[$i]->variaveis_adicionais['esconde_uso_banco']) {
            $this->TextFitCell(55, $this->cell, $this->_($this->boleto[$i]->getCarteiraNome()), 'LR', 0, 'L');
        } else {
            $cip = isset($this->boleto[$i]->variaveis_adicionais['mostra_cip']) && $this->boleto[$i]->variaveis_adicionais['mostra_cip'];
            $this->Cell(($cip ? 23 : 30), $this->cell, $this->_(''), 'LR');
            if ($cip) {
                $this->Cell(7, $this->cell, $this->_($this->boleto[$i]->getCip()), 'LR');
            }
            $this->Cell(25, $this->cell, $this->_(strtoupper($this->boleto[$i]->getCarteiraNome())), 'R');
        }

        $this->Cell(12, $this->cell, $this->_('R$'), 'R');
        $this->Cell(28, $this->cell, $this->_(''), 'R');
        $this->Cell(25, $this->cell, $this->_(($this->boleto[$i]->getCodigoBanco() == '001') ? Util::nReal($this->boleto[$i]->getValor()) : ''), 'R');
        $this->Cell(50, $this->cell, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'R', 1, 'R');

        $yStartPix = $this->GetY();
        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(95, $this->desc, $this->_('Instruções de responsabilidade do beneficiário. '), 'TL');
        $xStartPix = $this->GetX();
        $this->Cell(25, $this->desc, '', 'TR');
        $this->Cell(50, $this->desc, $this->_('(-) Desconto / Abatimentos)'), 'TR', 1);

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(120, $this->cell, $this->_('Qualquer dúvida sobre este boleto, contate o beneficiário'), 'LR');
        $this->Cell(50, $this->cell, $this->_(''), 'R', 1);

        $xInstrucoes = $this->GetX();
        $yInstrucoes = $this->GetY();

        $this->Cell(120, $this->desc, $this->_(''), 'LR');
        $this->Cell(50, $this->desc, $this->_('(-) Outras deduções'), 'TR', 1);

        $this->Cell(120, $this->cell, $this->_(''), 'LR');
        $this->Cell(50, $this->cell, $this->_(''), 'R', 1);

        $this->Cell(120, $this->desc, $this->_(''), 'LR');
        $this->Cell(50, $this->desc, $this->_('(+) Mora / Multa' . ($this->boleto[$i]->getCodigoBanco() == '104' ? ' / Juros' : '')), 'TR', 1);

        $this->Cell(120, $this->cell, $this->_(''), 'LR');
        $this->Cell(50, $this->cell, $this->_(''), 'R', 1);

        $this->Cell(120, $this->desc, $this->_(''), 'LR');
        $this->Cell(50, $this->desc, $this->_('(+) Outros acréscimos'), 'TR', 1);

        $this->Cell(120, $this->cell, $this->_(''), 'LR');
        $this->Cell(50, $this->cell, $this->_(''), 'R', 1);

        $this->Cell(120, $this->desc, $this->_(''), 'LR');
        $this->Cell(50, $this->desc, $this->_('(=) Valor cobrado'), 'TR', 1);

        $this->Cell(120, $this->cell, $this->_(''), 'BLR');
        $this->Cell(50, $this->cell, $this->_(''), 'BR', 1);

        $yEndPix = $this->GetY();

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(0, $this->desc, $this->_('Pagador'), 'LR', 1);

        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(0, $this->cell, $this->_($this->boleto[$i]->getPagador()->getNomeDocumento()), 'LR', 1);
        $this->Cell(0, $this->cell, $this->_(trim($this->boleto[$i]->getPagador()->getEndereco() . ' - ' . $this->boleto[$i]->getPagador()->getBairro()), ' -'), 'LR', 1);
        $this->Cell(0, $this->cell, $this->_($this->boleto[$i]->getPagador()->getCepCidadeUf()), 'LR', 1);

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(120, $this->cell, $this->_(''), 'BLR');
        $this->Cell(12, $this->cell, $this->_('Cód. Baixa'), 'B');
        $this->SetFont($this->PadraoFont, 'B', $this->fcel);
        $this->Cell(38, $this->cell, $this->_(''), 'BR', 1);

        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Cell(20, $this->desc, $this->_('Beneficiário Final'), 0);
        $this->Cell(98, $this->desc, $this->_($this->boleto[$i]->getSacadorAvalista() ? $this->boleto[$i]->getSacadorAvalista()->getNomeDocumento() : ''), 0);
        $this->Cell(52, $this->desc, $this->_('Autenticação mecânica - Ficha de Compensação'), 0, 1);

        $xOriginal = $this->GetX();
        $yOriginal = $this->GetY();

        if (count($this->boleto[$i]->getInstrucoes()) > 0) {
            $this->SetXY($xInstrucoes, $yInstrucoes);
            $this->Ln(1);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);

            $this->listaLinhas($this->boleto[$i]->getInstrucoes(), 0);

            $this->SetXY($xOriginal, $yOriginal);
        }

        if ($this->boleto[$i]->getPixQrCode() !== null && $this->localizacao_pix == self::PIX_INSTRUCAO) {
            $this->SetXY($xStartPix, $yStartPix);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(25, 6, 'Pague com PIX', '', '', 'C');
            $this->SetFont($this->PadraoFont, '', $this->fdes);
            $this->Image($this->boleto[$i]->getPixQrCodeBase64(), $xStartPix + 1, $yStartPix + 8, 23, 23, 'png');
            $this->Line($xStartPix, $yStartPix, $xStartPix, $yEndPix);

            $this->SetXY($xOriginal, $yOriginal);
        }

        return $this;
    }

    /**
     * @param string $texto
     * @param int $ln
     * @param int $ln2
     * @param $posicaoTexto
     * @param $alinhamentoTexto
     * @param $tamanho
     */
    protected function traco($texto, $ln = null, $ln2 = null, $posicaoTexto = 1, $alinhamentoTexto = 'R', $tamanho = 261)
    {
        if ($ln == 1 || $ln) {
            $this->Ln($ln);
        }
        $this->SetFont($this->PadraoFont, '', $this->fdes);
        if ($texto && $posicaoTexto !== -1) {
            $this->Cell(0, 2, $this->_($texto), 0, 1, $alinhamentoTexto);
        }
        $this->Cell(0, 2, str_pad('-', $tamanho, ' -', STR_PAD_RIGHT), 0, 1);
        if ($texto && $posicaoTexto === -1) {
            $this->Cell(0, 2, $this->_($texto), 0, 1, $alinhamentoTexto);
        }
        if ($ln2 == 1 || $ln2) {
            $this->Ln($ln2);
        }
    }

    /**
     * @param int $i
     */
    protected function codigoBarras($i)
    {
        $yOriginal = $this->GetY();
        $xOriginal = $this->GetX();
        $this->Ln(3);
        $this->Cell(0, 15, '', 0, 1, 'L');
        $this->i25($this->GetX(), $this->GetY() - 15, $this->boleto[$i]->getCodigoBarras(), .9, 17);

        if ($this->boleto[$i]->getPixQrCode() !== null && $this->localizacao_pix == self::PIX_COD_BARRAS) {
            $this->SetXY(142, $yOriginal + 2);
            $this->SetFont($this->PadraoFont, 'B', $this->fcel);
            $this->Cell(0, 6, 'Pague com PIX', '', 1, 'L');
            $this->SetX(142);
            $this->SetFont($this->PadraoFont, 'B', $this->fdes);
            $this->Cell(13, 6, 'Vencimento:', '', 0, 'L');
            $this->SetFont($this->PadraoFont, '', $this->fdes);
            $this->Cell(0, 6, $this->boleto[$i]->getDataVencimento()->format('d/m/Y'), '', 1, 'L');
            $this->SetX(142);
            $this->SetFont($this->PadraoFont, 'B', $this->fdes);
            $this->Cell(13, 6, 'Valor:', '', 0, 'L');
            $this->SetFont($this->PadraoFont, '', $this->fdes);
            $this->Cell(0, 6, Util::nReal($this->boleto[$i]->getValor()), '', 1, 'L');
//            $this->Cell(0, $this->cell, 'Pague com PIX', '', 1, 'C');
//            $this->Image($this->boleto[$i]->getPixQrCodeBase64(), $xStartPix + 1, $yStartPix + 5, 23, 23, 'png');
//            $this->Line($xStartPix, $yStartPix, $xStartPix, $yEndPix);

            $this->Image($this->boleto[$i]->getPixQrCodeBase64(), 170, $yOriginal + 1, 20, 20, 'png');

            $this->SetXY($xOriginal, $yOriginal);
        }
    }

    /**
     * Adiciona o boletos
     *
     * @param array $boletos
     * @param bool $withGroup
     * @return Pdf
     * @throws ValidationException
     */
    public function addBoletos(array $boletos, $withGroup = true)
    {
        if ($withGroup) 
            $this->StartPageGroup();
        
        foreach ($boletos as $boleto) {
            $this->addBoleto($boleto);
        }

        return $this;
    }

    /**
     * Adiciona o boleto
     *
     * @param BoletoContract $boleto
     *
     * @return Pdf
     * @throws ValidationException
     */
    public function addBoleto(BoletoContract $boleto)
    {
        if (! $boleto->imprimeBoleto()) {
            throw new ValidationException('Boleto com modalidade/carteira não disponível para impressão');
        }
        $this->totalBoletos += 1;
        $this->boleto[] = $boleto;

        return $this;
    }

    /**
     * @return Pdf
     */
    public function hideInstrucoes()
    {
        $this->showInstrucoes = false;

        return $this;
    }

    /**
     * @return Pdf
     */
    public function showPrint()
    {
        $this->print = true;

        return $this;
    }

    /**
     * Função para gerar o boleto
     *
     * @param string $dest tipo de destino const BOLETOPDF_DEST_STANDARD | BOLETOPDF_DEST_DOWNLOAD | BOLETOPDF_DEST_SAVE | BOLETOPDF_DEST_STRING
     * @param null $save_path
     * @return string
     * @throws ValidationException
     */
    public function gerarBoleto($dest = self::OUTPUT_STANDARD, $save_path = null, $nameFile = null)
    {
        if ($this->totalBoletos == 0) {
            throw new ValidationException('Nenhum Boleto adicionado');
        }

        for ($i = 0; $i < $this->totalBoletos; $i++) {
            $this->SetDrawColor('0', '0', '0');
            $this->AddPage();
            $this->instrucoes($i)->logoEmpresa($i)->Topo($i)->Bottom($i)->codigoBarras($i);
        }

        if ($this->print) {
            $this->IncludeJS("print('true');");
        }

        if ($dest == self::OUTPUT_SAVE) {
            $this->Output($save_path, $dest);

            return $save_path;
        }
        if ($nameFile == null) {
            $nameFile = Str::random(32);
        }

        return $this->Output($nameFile . '.pdf', $dest);
    }

    /**
     * @param integer $i
     *
     * @return $this
     */
    protected function capaCarne($i)
    {

        $this->Image('./img/logofesc.png', 15, ($this->GetY() +3), 26);
        //dd(getcwd());
        //$this->Image('./img/carimbo_fesc_carta.png', 160, ($this->GetY()+5 ), 30);
        
        $this->SetFont($this->PadraoFont, 'B', 30);
        $this->Cell(35,20,'','LT');
        $this->Cell(0, 20, 'FESC', 'TR', 0, 'L');
        $this->SetFont($this->PadraoFont, 'B', 15);
        $this->Ln();
        $this->Cell(35,10,'','L');
        $this->Cell(0, 10, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 'R', 0, 'L');
        $this->ln();
        $this->Cell(0, 18,'','LR');//abaix logo
        $this->ln();
        $this->SetFont('', 'B', 10);
        $this->Cell(35,5,'','L');
        $this->Cell(0, 5, $this->_($this->boleto[$i]->getPagador()->getNome()), 'R', 0, 'L');

        $this->ln();
        $this->SetFont('', '', 10);
        $this->Cell(35,5,'','L');
        $this->Cell(0, 5, $this->_(trim($this->boleto[$i]->getPagador()->getEndereco())), 'R', 0, 'L');
        $this->ln();
        $this->Cell(35,5,'','L');
        $this->Cell(0, 5, $this->_($this->boleto[$i]->getPagador()->getBairro() .' - CEP '.$this->boleto[$i]->getPagador()->getCepCidadeUf()), 'R', 0, 'L');
        $this->ln();
        $this->Cell(0,17,'','LBR');
        $this->ln(18);
        $this->traco('', 5, 5);
        
        $this->ln();

         $this->SetFont($this->PadraoFont, 'B', 14);
        $this->Cell(25,25,'','LT');
        $this->Cell(0, 25, utf8_decode('Informações gerais:'), 'TR', 0, 'L');
        $this->SetFont($this->PadraoFont, '', 10);
        $this->Ln();
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('1 - Este carnê possui boletos referentes à Fundação Educacional São Carlos'), 'R', 0, 'L');
        $this->ln(5);
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('2 - O pagamento pode ser feito em qualquer banco, casa lotérica ou on-line mesmo após a data de vencimento.'), 'R', 0, 'L');
        $this->ln(5);
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('3 - Todos os boletos estão disponíveis no site www.fesc.com.br e via DDA (consulte seu banco).'), 'R', 0, 'L');
        $this->ln(5);
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('4 - Após o vencimento é cobrado o valor de 2% de multa e 1% ao mês.'), 'R', 0, 'L');
        $this->ln(5);
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('5 - Em caso de desistência, procure a secretaria escolar.'), 'R', 0, 'L');
        $this->ln(10);
         $this->SetFont($this->PadraoFont, 'B', 10);
        $this->Cell(10,10,'','L');
        $this->Cell(0, 10, utf8_decode('Mais informações podem ser obtidas através do 3362-0580 ou pelo fesc@fesc.com.br'), 'R', 0, 'L');
        $this->ln(5);

        $this->Cell(0, 14,'','LR');//abaix logo
        $this->ln(14);

        $this->Cell(0,5,'','LBR');
        $this->ln(5);

        $this->traco('', 5, 5);


        $this->SetFont($this->PadraoFont, 'B', 30);
        $this->Cell(35,25,'','LT');
        $this->Cell(0, 25, '', 'TR', 0, 'L');
        $this->SetFont($this->PadraoFont, 'B', 15);
        $this->Ln();
        $this->Cell(35,0,'','L');
        $this->Cell(0, 0, '', 'R', 0, 'L');
        $this->ln();
        $this->Cell(0, 20,'','LR');//abaix logo
        $this->ln();
        $this->SetFont('', '', 8);
        $this->Cell(25, 5, '', 'L');
        $this->Cell(0,5,'','R');
        
        $this->ln();
        $this->Cell(25,5,'','L');
        $this->SetFont('', 'B', 10);
        $this->Cell(0, 5, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 'R', 0, 'L');

        $this->ln();

        $this->SetFont('', '', 10);
        $this->Cell(25,5,'','L');
        $this->Cell(0, 4, $this->_(trim($this->boleto[$i]->getBeneficiario()->getEndereco() . ' - ' . $this->boleto[$i]->getBeneficiario()->getBairro()),' -'), 'R', 0, 'L');
        $this->ln();
        $this->Cell(25,5,'','L');
        $this->Cell(0, 5, $this->_($this->boleto[$i]->getBeneficiario()->getCepCidadeUf()), 'R', 0, 'L');
        $this->ln();
        $this->Cell(0,16,'','LBR');
        $this->ln(5);

        
    }

   

     /**
     * @param integer $i
     *
     * @return $this
     */
    protected function boletoCarne($i)
    {
        $referencias = $this->boleto[$i]->getDescricaoDemonstrativo();
        $this->Ln(2);
        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Image($this->boleto[$i]->getLogoBanco(), 10, ($this->GetY() - 2), 23);
        $this->Cell(23, 4,'','B');//abaix logo
        $this->SetFont('', 'B', 12);
        $this->Cell(18, 4, $this->boleto[$i]->getCodigoBancoComDv(), 'LB', 0, '');
        $this->Cell(4, 4);//espaço
        $this->Cell(27, 4, '','LB');//logo
        $this->Image($this->boleto[$i]->getLogoBanco(), 55, ($this->GetY() - 3), 27);
        $this->Cell(13, 4, $this->boleto[$i]->getCodigoBancoComDv(), 'LBR', 0, 'L');
        $this->SetFont('', 'B', 10);
        $this->Cell(0, 4, $this->boleto[$i]->getLinhaDigitavel(), 'B', 1, 'R');
       



        $this->SetFont('', '', 6);
        $this->Cell(18, 3, 'Parcela/Plano', 'LT', 0, 'L');
        $this->Cell(23, 3, 'Vencimento', 'LTR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, 'Local de pagamento', 'LT', 0, 'L');
        $this->Cell(0, 3, 'Vencimento', 'LTR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(18, 3, '', 'LB', 0, 'L');
        $this->Cell(23, 3, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode('Pagável em qualquer agência bancaria mesmo após vencimento.'), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'LBR', 0, 'R');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Ag/Cód. Beneficiário'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, utf8_decode('Beneficiário'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('Ag/Cód. Beneficiário'), 'LR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'LBR', 0, 'R');
        $this->ln();





        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Espécie'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(30, 3, utf8_decode('Data do documento'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Nº documento'), 'L', 0, 'L');
        $this->Cell(15, 3, utf8_decode('Espécie doc.'), 'L', 0, 'L');
        $this->Cell(9, 3, utf8_decode('Aceite'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Data processamento'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('Nosso número'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, 'R$', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(30, 3, $this->_($this->boleto[$i]->getDataDocumento()->format('d/m/Y')), 'LB', 0, 'L');
        $this->Cell(25, 3, $this->_($this->boleto[$i]->getNumeroDocumento()), 'LB', 0, 'L');
        $this->Cell(15, 3, $this->_($this->boleto[$i]->getEspecieDoc()), 'LB', 0, 'L');
        $this->Cell(9, 3, $this->_($this->boleto[$i]->getAceite()), 'LBR', 0, 'R');
        $this->Cell(25, 3, $this->_($this->boleto[$i]->getDataProcessamento()->format('d/m/Y')), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Quantidade'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(30, 3, utf8_decode('Uso do banco'), 'L', 0, 'L');
        $this->Cell(15, 3, utf8_decode('Carteira'), 'L', 0, 'L');
        $this->Cell(10, 3, utf8_decode('Espécie'), 'L', 0, 'L');
        $this->Cell(24, 3, utf8_decode('Quantidade'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Valor'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('(=) Valor do Documento'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(30, 3, ' ', 'LB', 0, 'L');
        $this->Cell(15, 3, $this->_(strtoupper($this->boleto[$i]->getCarteiraNome())), 'LB', 0, 'L');
        $this->Cell(10, 3, 'R$', 'LB', 0, 'L');
        $this->Cell(24, 3, ' ', 'LB', 0, 'L');
        $this->Cell(25, 3, ' ', 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'LBR', 0, 'R');
        $this->ln();



        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(=) Valor do Documento'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, utf8_decode('Instruções de responsabilidade do beneficiário. Qualquer dúvida sobre este boleto, contate o beneficiário.'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('(-) Descontos / Abatimentos'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[0]), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(-) Descontos / Abatimentos'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode($referencias[1]), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, utf8_decode('(-) Outras deduções'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[2]), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(-) Outras deduções'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode($referencias[3]), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, utf8_decode('(+) Mora / multa'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[4]), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(+) Mora / multa'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, '', 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, utf8_decode('(+) Outros acréscimos'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
    
        $this->Cell(104, 3, utf8_decode(' '), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(+) Outros acréscimos'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode(''), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, utf8_decode('(=) Valor cobrado'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode('Sr. Caixa, cobrar multa de 2% após o vencimento e 1% ao mês de atraso.'), 'LB', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(=) Valor cobrado'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(0, 3, utf8_decode('Pagador'), 'LR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getPagador()->getNomeDocumento()), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Nosso Número'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(0, 3, $this->_(trim($this->boleto[$i]->getPagador()->getEndereco() . ' - ' . $this->boleto[$i]->getPagador()->getBairro()),' -'), 'LR', 0, 'L');
        $this->ln();
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getPagador()->getCepCidadeUf()), 'LBR', 0, 'L');
        $this->ln();
        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Nº documento'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->i25(55, $this->GetY() +3, $this->boleto[$i]->getCodigoBarras(), 0.7, 9);
        $this->SetFont('', '', 6);
        $this->Cell(40, 3, utf8_decode('Sacador / Avalista'), '', 0, 'L');
        $this->SetFont('', 'B', 6);
        $this->Cell(0, 3, utf8_decode('Autenticação mecânica - Ficha de compensação'), '', 0, 'R');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getNumeroDocumento()), 'LBR', 0, 'R');
        $this->Cell(4, 3);//espaço
        $this->ln();
        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Pagador'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, substr($this->_($this->boleto[$i]->getPagador()->getNome()),0,30), 'LBR', 0, 'R');
        $this->Cell(4, 3);//espaço
        $this->traco('', 5, 5);
      
        return $this;
    }

    /**
     * @param integer $i
     *
     * @return $this
     */
    protected function boletoCarnePIX($i)
    {
        $referencias = $this->boleto[$i]->getDescricaoDemonstrativo();
        $this->Ln(2);
        $this->SetFont($this->PadraoFont, '', $this->fdes);
        $this->Image($this->boleto[$i]->getLogoBanco(), 10, ($this->GetY() - 2), 23);
        $this->Cell(23, 4,'','B');//abaix logo
        $this->SetFont('', 'B', 12);
        $this->Cell(18, 4, $this->boleto[$i]->getCodigoBancoComDv(), 'LB', 0, '');
        $this->Cell(4, 4);//espaço
        $this->Cell(27, 4, '','LB');//logo
        $this->Image($this->boleto[$i]->getLogoBanco(), 55, ($this->GetY() - 3), 27);
        $this->Cell(13, 4, $this->boleto[$i]->getCodigoBancoComDv(), 'LBR', 0, 'L');
        $this->SetFont('', 'B', 10);
        $this->Cell(0, 4, $this->boleto[$i]->getLinhaDigitavel(), 'B', 1, 'R');
       



        $this->SetFont('', '', 6);
        $this->Cell(18, 3, 'Parcela/Plano', 'LT', 0, 'L');
        $this->Cell(23, 3, 'Vencimento', 'LTR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, 'Local de pagamento', 'LT', 0, 'L');
        $this->Cell(0, 3, 'Vencimento', 'LTR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(18, 3, '', 'LB', 0, 'L');
        $this->Cell(23, 3, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode('Pagável em qualquer agência bancaria mesmo após vencimento.'), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getDataVencimento()->format('d/m/Y')), 'LBR', 0, 'R');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Ag/Cód. Beneficiário'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, utf8_decode('Beneficiário'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('Ag/Cód. Beneficiário'), 'LR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, $this->_($this->boleto[$i]->getBeneficiario()->getNome()), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getAgenciaCodigoBeneficiario()), 'LBR', 0, 'R');
        $this->ln();





        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Espécie'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(30, 3, utf8_decode('Data do documento'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Nº documento'), 'L', 0, 'L');
        $this->Cell(15, 3, utf8_decode('Espécie doc.'), 'L', 0, 'L');
        $this->Cell(9, 3, utf8_decode('Aceite'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Data processamento'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('Nosso número'), 'LR', 0, 'L');
        $this->ln();


        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, 'R$', 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(30, 3, $this->_($this->boleto[$i]->getDataDocumento()->format('d/m/Y')), 'LB', 0, 'L');
        $this->Cell(25, 3, $this->_($this->boleto[$i]->getNumeroDocumento()), 'LB', 0, 'L');
        $this->Cell(15, 3, $this->_($this->boleto[$i]->getEspecieDoc()), 'LB', 0, 'L');
        $this->Cell(9, 3, $this->_($this->boleto[$i]->getAceite()), 'LBR', 0, 'R');
        $this->Cell(25, 3, $this->_($this->boleto[$i]->getDataProcessamento()->format('d/m/Y')), 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Quantidade'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(30, 3, utf8_decode('Uso do banco'), 'L', 0, 'L');
        $this->Cell(15, 3, utf8_decode('Carteira'), 'L', 0, 'L');
        $this->Cell(10, 3, utf8_decode('Espécie'), 'L', 0, 'L');
        $this->Cell(24, 3, utf8_decode('Quantidade'), 'L', 0, 'L');
        $this->Cell(25, 3, utf8_decode('Valor'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('(=) Valor do Documento'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(30, 3, ' ', 'LB', 0, 'L');
        $this->Cell(15, 3, $this->_(strtoupper($this->boleto[$i]->getCarteiraNome())), 'LB', 0, 'L');
        $this->Cell(10, 3, 'R$', 'LB', 0, 'L');
        $this->Cell(24, 3, ' ', 'LB', 0, 'L');
        $this->Cell(25, 3, ' ', 'LB', 0, 'L');
        $this->Cell(0, 3, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(=) Valor do Documento'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(104, 3, utf8_decode('Instruções de responsabilidade do beneficiário. Qualquer dúvida sobre este boleto, contate o beneficiário.'), 'L', 0, 'L');
        $this->Cell(0, 3, utf8_decode('Para pagamento via PIX:'), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_(Util::nReal($this->boleto[$i]->getValor())), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[0]), 'L', 0, 'L');
        $this->Image($this->boleto[$i]->getPixQrCodeBase64(), $this->GetX() +8, $this->GetY() +1, 25, 25, 'png');
        $this->Cell(0, 3, ' ', 'LR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(-) Descontos / Abatimentos'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode($referencias[1]), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, '', 'LR', 0, 'L');//QRCODE
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[2]), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LR', 0, 'R');//QRCODE
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(-) Outras deduções'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode($referencias[3]), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, '', 'LR', 0, 'L');//QRcode
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode($referencias[4]), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LR', 0, 'R');//QRcode
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(+) Mora / multa'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3,  '', 'L', 0, 'L'); 
        $this->Cell(0, 3, '', 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
    
        $this->Cell(104, 3, utf8_decode(' '), 'L', 0, 'L');
        $this->Cell(0, 3, ' ', 'LR', 0, 'R');//QRcode
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(+) Outros acréscimos'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(104, 3, utf8_decode(''), 'L', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3, '', 'LR', 0, 'L');//qrcode
        $this->ln();

        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(104, 3, utf8_decode('Sr. Caixa, cobrar multa de 2% após o vencimento e 1% ao mês de atraso.'), 'LB', 0, 'L');
        $this->Cell(0, 3, ' ', 'LBR', 0, 'R');
        $this->ln();


        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('(=) Valor cobrado'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->Cell(0, 3, utf8_decode('Pagador'), 'LR', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, ' ', 'LBR', 0, 'L');
        $this->Cell(4, 2);//espaço
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getPagador()->getNomeDocumento()), 'LR', 0, 'L');
        $this->ln();

        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Nosso Número'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->SetFont('', 'B', 7);
        $this->Cell(0, 3, $this->_(trim($this->boleto[$i]->getPagador()->getEndereco() . ' - ' . $this->boleto[$i]->getPagador()->getBairro()),' -'), 'LR', 0, 'L');
        $this->ln();
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getNossoNumeroBoleto()), 'LBR', 0, 'R');
        $this->Cell(4, 2);//espaço
        $this->Cell(0, 3, $this->_($this->boleto[$i]->getPagador()->getCepCidadeUf()), 'LBR', 0, 'L');
        $this->ln();
        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Nº documento'), 'LR', 0, 'L');
        $this->Cell(4, 3);//espaço
        $this->i25(55, $this->GetY() +2, $this->boleto[$i]->getCodigoBarras(), 0.7, 10);
        $this->SetFont('', '', 6);
        //$this->Cell(40, 3, utf8_decode('Sacador / Avalista'), '', 0, 'L');
        $this->SetFont('', '', 6);
        $this->Cell(0, 3);
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, $this->_($this->boleto[$i]->getNumeroDocumento()), 'LBR', 0, 'R');
        $this->Cell(4, 3);//espaço
        $this->ln();
        $this->SetFont('', '', 6);
        $this->Cell(41, 3, utf8_decode('Pagador'), 'LR', 0, 'L');
        $this->Cell(100, 3);//espaço
        $this->SetFont('', '', 8);
        $this->Cell(4, 3, utf8_decode('Código de Barras'), '', 0, 'L');
        $this->ln();
        $this->SetFont('', 'B', 7);
        $this->Cell(41, 3, substr($this->_($this->boleto[$i]->getPagador()->getNome()),0,30), 'LBR', 0, 'R');
        $this->Cell(100, 3);//espaço
        $this->SetFont('', '', 6);
        $this->Cell(4, 3, utf8_decode('Autenticação mecânica - Ficha de compensação'), '', 0, 'L');
        $this->traco('', 5, 5);
      
        return $this;
    }



    /**
     * Função para gerar o carne
     *
     * @param string $dest tipo de destino const BOLETOPDF_DEST_STANDARD | BOLETOPDF_DEST_DOWNLOAD | BOLETOPDF_DEST_SAVE | BOLETOPDF_DEST_STRING
     * @param null $save_path
     * @param null $nameFile
     *
     * @return string
     * @throws ValidationException
     */
    public function gerarCarne($dest = self::OUTPUT_STANDARD, $save_path = null, $nameFile = null)
    {
        if ($this->totalBoletos == 0) {
            throw new \Exception('Nenhum Boleto adicionado');
        }
        $this->SetDrawColor('0', '0', '0');
        $this->setMargins(10,10);

        for ($i = 0; $i < $this->totalBoletos; $i++) {  
            if($i>0 && $this->boleto[$i]->getPagador()->getNomeDocumento() === $this->boleto[$i-1]->getPagador()->getNomeDocumento()){
                if($this->boleto[$i]->pixQrCode)
                    $this->boletoCarnePIX($i);
                else
                    $this->boletoCarne($i);
                $contador++;
            }
            else{
                $this->AddPage();
                $this->capaCarne($i);
                $this->AddPage();
                if($this->boleto[$i]->pixQrCode)
                    $this->boletoCarnePIX($i);
                else
                    $this->boletoCarne($i);
                $contador=1;

            }

            /*if(fmod($contador,3)==0 && $i>0) 
                 $this->AddPage();*/
            if($contador==3 && $i>0 && isset($this->boleto[$i+1]) && $this->boleto[$i]->getPagador()->getNomeDocumento() === $this->boleto[$i+1]->getPagador()->getNomeDocumento()){
                $this->AddPage();
                $contador=0;

            }

        }
        if ($dest == self::OUTPUT_SAVE) {
            $this->Output($save_path, $dest, $this->print);
            return $save_path;
        }
        if ($nameFile == null) {
            $nameFile = Str::random(32);
        }
        
        return $this->Output($nameFile . '.pdf', $dest, $this->print);
    }

    /**
     * @param $lista
     * @param int $pulaLinha
     *
     * @return int
     */
    protected function listaLinhas($lista, $pulaLinha)
    {
        foreach ($lista as $d) {
            $pulaLinha -= 2;
            $this->MultiCell(0, $this->cell - 0.2, $this->_(preg_replace('/(%)/', '%$1', $d ?? '')), 0, 1);
        }

        return $pulaLinha;
    }
}
