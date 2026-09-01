<?php

namespace NFePHP\DA\NFSe;

use Com\Tecnick\Barcode\Barcode;
use NFePHP\DA\Legacy\Common;
use NFePHP\DA\Legacy\Pdf;

/**
 * Esta classe gera o PDF do DANFSe (Documento Auxiliar da NFS-e), conforme
 * o layout nacional (padrão do Ambiente de Dados Nacional da NFS-e).
 *
 * @category  Library
 * @package   accellog/sped-da
 * @name      Danfse.php
 * @author    ValterFC
 */
class Danfse extends Common
{
    protected $pdf = '';
    protected $xml;
    protected $errMsg = '';
    protected $errStatus = false;
    protected $orientacao = 'P';
    protected $papel = 'A4';
    protected $destino = 'I';
    protected $fontePadrao = 'Helvetica';
    protected $wPrint;
    protected $hPrint;
    protected $linkAutenticidade = '';
    protected $logoNfse = '';
    protected $TextoRodape = '';

    /** @var \SimpleXMLElement */
    private $infNFSe;
    /** @var \SimpleXMLElement */
    private $infDPS;

    public function __construct($docXML = '', $linkAutenticidade = '', $logoNfse = '', $orientacao = 'P', $papel = 'A4', $destino = 'I')
    {
        $this->xml = $docXML;
        $this->linkAutenticidade = $linkAutenticidade;
        $this->logoNfse = $logoNfse;
        $this->orientacao = $orientacao;
        $this->papel = $papel;
        $this->destino = $destino;

        if (!empty($this->xml)) {
            $sxml = simplexml_load_string($this->xml);
            $this->infNFSe = $sxml->infNFSe;
            $this->infDPS = $sxml->infNFSe->DPS->infDPS;

            if (!isset($this->infNFSe->nNFSe)) {
                throw new \InvalidArgumentException('O xml informado não é uma NFS-e nacional válida.');
            }
        }
    }

    public function montaDANFSE()
    {
        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);

        $margem = 5;
        $maxW = 210;
        $maxH = 297;
        $this->wPrint = $maxW - (2 * $margem);
        $this->hPrint = $maxH - (2 * $margem);

        $this->pdf->SetMargins($margem, $margem, $margem);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->AliasNbPages();
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Open();
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->SetTextColor(0, 0, 0);

        $x = $margem;
        $y = $margem;

        $y = $this->pCabecalho($x, $y);
        $y = $this->pChaveEQrCode($x, $y);
        $y = $this->pIdentificacao($x, $y);
        $y = $this->pPrestador($x, $y);
        $y = $this->pTomador($x, $y);
        $y = $this->pDestinatarioIntermediario($x, $y);
        $y = $this->pServico($x, $y);
        $y = $this->pTributacaoMunicipal($x, $y);
        $y = $this->pTributacaoFederal($x, $y);
        $y = $this->pTributacaoIbsCbs($x, $y);
        $y = $this->pValorTotal($x, $y);
        $y = $this->pInformacoesComplementares($x, $y);
        $this->pRodape($x);

        return $this->pdf;
    }

    public function printDANFSE($nome = '', $destino = 'I')
    {
        return $this->pdf->Output($nome, $destino);
    }

    public function setTextoRodape($newTextoRodape)
    {
        $this->TextoRodape = $newTextoRodape;
    }

    /**
     * Desenha uma caixa com cantos retos (pTextBox/RoundedRect sempre arredonda
     * os cantos, o que deixa "furos" visíveis onde células vizinhas se encostam
     * numa grade densa como esta).
     */
    protected function caixa($x, $y, $w, $h)
    {
        $this->pdf->Rect($x, $y, $w, $h);
    }

    /**
     * Escreve um campo com um rótulo pequeno em negrito na parte superior
     * e o valor logo abaixo, dentro de uma única caixa com borda.
     */
    protected function campo($x, $y, $w, $h, $label, $valor, $valorSize = 7, $valorStyle = '', $offsetValor = 2.6)
    {
        $this->caixa($x, $y, $w, $h);
        $aFontLabel = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'B');
        $aFontValor = array('font' => $this->fontePadrao, 'size' => $valorSize, 'style' => $valorStyle);

        // largura de texto 1mm menor que a caixa: pTextBox desenha a partir de x+0.5
        // mas o WordWrap calcula a quebra usando a largura cheia, então um texto que
        // preenche a caixa até o limite acaba estourando a borda por ~0.5mm
        $wTexto = $w - 1;
        $this->pTextBox($x, $y, $wTexto, $h, $label, $aFontLabel, 'T', 'L', 0, '', false);
        $this->pTextBox($x, $y + $offsetValor, $wTexto, $h - $offsetValor, $valor, $aFontValor, 'T', 'L', 0, '', false);
    }

    /**
     * Barra cinza de título de seção. Alinhada ao topo, na mesma linha de base
     * dos rótulos dos campos vizinhos (campo()), para não "descolar" da grade.
     */
    protected function tituloSecao($x, $y, $w, $h, $texto)
    {
        $this->pdf->SetFillColor(210, 210, 210);
        $this->pdf->Rect($x, $y, $w, $h, 'F');
        $this->pdf->SetFillColor(255, 255, 255);
        $this->caixa($x, $y, $w, $h);
        $aFont = array('font' => $this->fontePadrao, 'size' => 7, 'style' => 'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '', false);
    }

    protected function v($node, $default = '-')
    {
        if ($node === null) {
            return $default;
        }
        $texto = trim((string) $node);
        return $texto === '' ? $default : $texto;
    }

    protected function vMoeda($node)
    {
        if ($node === null || trim((string) $node) === '') {
            return '-';
        }
        return 'R$ ' . number_format((float) $node, 2, ',', '.');
    }

    protected function vData($node, $comHora = false)
    {
        if ($node === null || trim((string) $node) === '') {
            return '-';
        }
        $texto = (string) $node;
        $formato = $comHora ? 'd/m/Y H:i:s' : 'd/m/Y';
        try {
            // usa o horário/offset literal do XML, sem converter para o fuso do servidor
            return (new \DateTime($texto))->format($formato);
        } catch (\Exception $e) {
            return $texto;
        }
    }

    protected function vPercent($node)
    {
        if ($node === null || trim((string) $node) === '') {
            return '-';
        }
        return number_format((float) $node, 2, ',', '.') . ' %';
    }

    protected function vTelefone($node)
    {
        if ($node === null || trim((string) $node) === '') {
            return '-';
        }
        $numero = preg_replace('/\D/', '', (string) $node);
        if (strlen($numero) === 11) {
            return $this->pFormat($numero, '(##) #####-####');
        }
        return $this->pFormat($numero, '(##) ####-####');
    }

    protected function vCodTribNac($node)
    {
        if ($node === null || trim((string) $node) === '') {
            return '-';
        }
        return $this->pFormat((string) $node, '##.##.##');
    }

    protected function vCnpjCpf($cnpj, $cpf)
    {
        if ($cnpj !== null && trim((string) $cnpj) !== '') {
            return $this->pFormat((string) $cnpj, '##.###.###/####-##');
        }
        if ($cpf !== null && trim((string) $cpf) !== '') {
            return $this->pFormat((string) $cpf, '###.###.###-##');
        }
        return '-';
    }

    protected function vCep($cep)
    {
        if ($cep === null || trim((string) $cep) === '') {
            return '-';
        }
        return $this->pFormat((string) $cep, '##.###-###');
    }

    protected function vIbge($cMun)
    {
        if ($cMun === null || trim((string) $cMun) === '') {
            return '-';
        }
        $codigo = (string) $cMun;
        return substr($codigo, 0, 2) . '.' . substr($codigo, 2);
    }

    protected function pCabecalho($x, $y)
    {
        $maxW = $this->wPrint;
        $h = 16;

        $wLogoBox = $maxW * 0.55;
        if (!empty($this->logoNfse) && is_file($this->logoNfse)) {
            $this->pLogoNfse($x + 2, $y, $wLogoBox - 4, $h);
        } else {
            $aFont = array('font' => $this->fontePadrao, 'size' => 14, 'style' => 'B');
            $this->pTextBox($x + 2, $y + 2, $wLogoBox - 4, 6, 'NFSe', $aFont, 'T', 'L', 0);
            $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => '');
            $this->pTextBox($x + 2, $y + 8, $wLogoBox - 4, 4, 'Nota Fiscal de Serviço eletrônica', $aFont, 'T', 'L', 0);
        }

        $aFont = array('font' => $this->fontePadrao, 'size' => 11, 'style' => 'B');
        $this->pTextBox($x, $y + 1, $maxW, 6, 'DANFSe v2.0', $aFont, 'T', 'C', 0);
        $aFont = array('font' => $this->fontePadrao, 'size' => 8, 'style' => '');
        $this->pTextBox($x, $y + 6, $maxW, 5, 'Documento Auxiliar da NFS-e', $aFont, 'T', 'C', 0);

        // tpAmb (infDPS): 1-Produção, 2-Homologação. ambGer (infNFSe) indica apenas o sistema
        // gerador (Municipal x Sefin Nacional), não o ambiente de produção/homologação.
        $tpAmb = $this->v($this->infDPS->tpAmb);
        if ($tpAmb != '1') {
            $this->pdf->SetTextColor(200, 0, 0);
            $aFont = array('font' => $this->fontePadrao, 'size' => 8, 'style' => 'B');
            $this->pTextBox($x, $y + 11, $maxW, 5, 'NFS-e SEM VALIDADE JURÍDICA', $aFont, 'T', 'C', 0);
            $this->pdf->SetTextColor(0, 0, 0);
        }

        $wDir = $maxW * 0.30;
        $xDir = $x + $maxW - $wDir;
        $municipio = $this->v($this->infNFSe->xLocEmi);
        $uf = $this->v($this->infNFSe->emit->enderNac->UF);
        $ambGer = $this->v($this->infNFSe->ambGer);
        $aFont = array('font' => $this->fontePadrao, 'size' => 7, 'style' => '');
        $texto = "Município: {$municipio} - {$uf}\nAmbiente Gerador: {$ambGer}\nTipo de Ambiente: {$tpAmb}";
        $this->pTextBox($xDir, $y + 2, $wDir - 1, $h - 2, $texto, $aFont, 'T', 'R', 0, '', false);

        return $y + $h;
    }

    /**
     * Desenha a logomarca oficial da NFS-e (NFSe.gov.br) centralizada verticalmente
     * dentro da área disponível, preservando a proporção original da imagem.
     */
    protected function pLogoNfse($x, $y, $wMax, $hMax)
    {
        $info = getimagesize($this->logoNfse);
        $wPx = $info[0];
        $hPx = $info[1];

        // ajusta pela altura (com uma margem interna), pois a logo é um lockup
        // horizontal largo; a largura só entra como limite de segurança
        $hImg = $hMax - 4;
        $wImg = $hImg * ($wPx / $hPx);
        if ($wImg > $wMax) {
            $wImg = $wMax;
            $hImg = $wImg * ($hPx / $wPx);
        }

        $xImg = $x;
        $yImg = $y + (($hMax - $hImg) / 2);
        $tipo = strtolower(pathinfo($this->logoNfse, PATHINFO_EXTENSION)) === 'png' ? 'PNG' : 'JPEG';

        $this->pdf->Image($this->logoNfse, $xImg, $yImg, $wImg, $hImg, $tipo);
    }

    protected function pChaveEQrCode($x, $y)
    {
        $maxW = $this->wPrint;
        $wQr = 22;
        $wChave = $maxW - $wQr - 2;
        $h = $wQr; // caixa do QR é quadrada: a altura da linha acompanha a largura reservada ao QR

        $chave = $this->pIdChave();

        $this->caixa($x, $y, $wChave, $h);
        $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'B');
        $this->pTextBox($x, $y, $wChave, $h, 'CHAVE DE ACESSO DA NFS-e', $aFont, 'T', 'L', 0, '', false);
        $aFont = array('font' => $this->fontePadrao, 'size' => 11, 'style' => '');
        $this->pTextBox($x, $y + 4, $wChave, 6, $chave, $aFont, 'T', 'L', 0, '', false);

        $xQr = $x + $wChave + 2;
        $this->caixa($xQr, $y, $wQr, $h);
        if (!empty($this->linkAutenticidade)) {
            $this->pQrCode($xQr + 1, $y + 1, $wQr - 2);
        }

        $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => '');
        $texto = 'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR '
            . 'ou pela consulta da chave de acesso no portal nacional da NFS-e';
        $this->pTextBox($x, $y + $h, $maxW, 5, $texto, $aFont, 'T', 'L', 0, '', false);

        return $y + $h + 5;
    }

    protected function pIdChave()
    {
        $id = (string) $this->infNFSe['Id'];
        return preg_replace('/^NFS/', '', $id);
    }

    protected function pQrCode($x, $y, $w)
    {
        $barcode = new Barcode();
        $bobj = $barcode->getBarcodeObj(
            'QRCODE,M',
            $this->linkAutenticidade,
            -4,
            -4,
            'black',
            array(-2, -2, -2, -2)
        )->setBackgroundColor('white');
        $qrcode = $bobj->getPngData();
        $pic = 'data://text/plain;base64,' . base64_encode($qrcode);
        $this->pdf->Image($pic, $x, $y, $w, $w, 'PNG', $this->linkAutenticidade);
    }

    protected function pIdentificacao($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;

        $this->campo($x, $y, $w3, $h, 'NÚMERO DA NFS-e', $this->v($this->infNFSe->nNFSe));
        $this->campo($x + $w3, $y, $w3, $h, 'COMPETÊNCIA DA NFS-e', $this->vData($this->infDPS->dCompet));
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'DATA E HORA DA EMISSÃO DA NFS-e', $this->vData($this->infNFSe->dhProc, true), 7);
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'NÚMERO DA DPS', $this->v($this->infDPS->nDPS));
        $this->campo($x + $w3, $y, $w3, $h, 'SÉRIE DA DPS', $this->v($this->infDPS->serie));
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'DATA E HORA DA EMISSÃO DA DPS', $this->vData($this->infDPS->dhEmi, true), 7);
        $y += $h;

        $tpEmit = (string) $this->infDPS->tpEmit;
        $emitente = $tpEmit == '1' ? 'Prestador' : ($tpEmit == '2' ? 'Tomador' : 'Intermediário');
        $this->campo($x, $y, $w3, $h, 'EMITENTE DA NFS-e', $emitente);
        $this->campo($x + $w3, $y, $w3, $h, 'SITUAÇÃO DA NFS-e', $this->pSituacao());
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'FINALIDADE', '-');
        $y += $h;

        return $y;
    }

    protected function pSituacao()
    {
        $cStat = (string) $this->infNFSe->cStat;
        return $cStat == '100' ? 'NFS-e Gerada' : 'NFS-e Cancelada';
    }

    protected function pPrestador($x, $y)
    {
        $emit = $this->infNFSe->emit;
        $prest = $this->infDPS->prest;
        $ender = $emit->enderNac;

        $y = $this->pBlocoParte(
            $x,
            $y,
            'PRESTADOR / FORNECEDOR',
            $this->vCnpjCpf($emit->CNPJ, $emit->CPF),
            '-',
            $this->vTelefone($prest->fone ?: $emit->fone),
            $this->v($emit->xNome),
            $this->v($this->infNFSe->xLocEmi) . ' / ' . $this->v($ender->UF),
            $this->vIbge($ender->cMun) . ' / ' . $this->vCep($ender->CEP),
            $this->v($ender->xLgr) . ', ' . $this->v($ender->nro) . ', ' . $this->v($ender->xBairro),
            $this->v($prest->email, $this->v($emit->email))
        );

        $maxW = $this->wPrint;
        $w2 = $maxW / 2;
        $h = 6;
        $opSimpNac = (string) $prest->regTrib->opSimpNac;
        $simplesTexto = $opSimpNac == '1' ? 'Optante' : ($opSimpNac == '2' ? 'Optante - Excesso de sublimite de receita bruta' : 'Não optante');
        $this->campo($x, $y, $w2, $h, 'Simples Nacional na Data de Competência', $simplesTexto);
        $regEsp = $this->v($prest->regTrib->regEspTrib, '');
        $regimeTexto = ($regEsp === '' || $regEsp === '0') ? '-' : $regEsp;
        $this->campo($x + $w2, $y, $w2, $h, 'Regime de Apuração Tributária pelo SN', $regimeTexto);
        $y += $h;

        return $y;
    }

    protected function pTomador($x, $y)
    {
        $toma = $this->infDPS->toma;
        if (!isset($toma) || count($toma->children()) == 0) {
            $this->caixa($x, $y, $this->wPrint, 5);
            $aFont = array('font' => $this->fontePadrao, 'size' => 7, 'style' => 'B');
            $this->pTextBox($x, $y, $this->wPrint, 5, 'TOMADOR / ADQUIRENTE NÃO IDENTIFICADO NA NFS-e', $aFont, 'C', 'L', 0, '', false);
            return $y + 5;
        }

        $end = $toma->end;
        $endNac = $end->endNac;

        return $this->pBlocoParte(
            $x,
            $y,
            'TOMADOR / ADQUIRENTE',
            $this->vCnpjCpf($toma->CNPJ, $toma->CPF),
            '-',
            $this->vTelefone($toma->fone),
            $this->v($toma->xNome),
            $this->v($this->infNFSe->xLocEmi) . ' / ' . $this->v($this->infNFSe->emit->enderNac->UF),
            $this->vIbge($endNac->cMun) . ' / ' . $this->vCep($endNac->CEP),
            $this->v($end->xLgr) . ', ' . $this->v($end->nro) . ', ' . $this->v($end->xBairro),
            $this->v($toma->email)
        );
    }

    /**
     * Bloco padrão usado pelo Prestador e pelo Tomador: título, CNPJ/Indicador/Telefone,
     * Nome, Município/Código IBGE, Endereço/E-mail.
     */
    protected function pBlocoParte($x, $y, $titulo, $cnpjCpf, $indicador, $telefone, $nome, $municipioUf, $ibgeCep, $endereco, $email)
    {
        $maxW = $this->wPrint;
        $w2 = $maxW / 2;
        $w4 = $maxW / 4;
        $h = 6;

        $this->tituloSecao($x, $y, $w4, $h, $titulo);
        $this->campo($x + $w4, $y, $w4, $h, 'CNPJ / CPF / NIF', $cnpjCpf);
        $this->campo($x + 2 * $w4, $y, $w4, $h, 'Indicador Municipal (Inscrição)', $indicador, 7);
        $this->campo($x + 3 * $w4, $y, $w4, $h, 'Telefone', $telefone);
        $y += $h;

        $this->campo($x, $y, $maxW, $h, 'Nome / Nome Empresarial', $nome);
        $y += $h;

        $this->campo($x, $y, $w2, $h, 'Município / Sigla UF', $municipioUf);
        $this->campo($x + $w2, $y, $w2, $h, 'Código IBGE / CEP', $ibgeCep);
        $y += $h;

        $this->campo($x, $y, $w2, $h, 'Endereço', $endereco);
        $this->campo($x + $w2, $y, $w2, $h, 'E-mail', $email);
        $y += $h;

        return $y;
    }

    protected function pDestinatarioIntermediario($x, $y)
    {
        $aFont = array('font' => $this->fontePadrao, 'size' => 7, 'style' => 'B');
        $h = 5;
        $this->caixa($x, $y, $this->wPrint, $h);
        $this->pTextBox($x, $y, $this->wPrint, $h, 'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $aFont, 'C', 'L', 0, '', false);
        $y += $h;
        $this->caixa($x, $y, $this->wPrint, $h);
        $this->pTextBox($x, $y, $this->wPrint, $h, 'INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $aFont, 'C', 'L', 0, '', false);
        $y += $h;

        return $y;
    }

    protected function pServico($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;

        $serv = $this->infDPS->serv;
        $cServ = $serv->cServ;
        $locPrest = $serv->locPrest;

        $this->tituloSecao($x, $y, $w3, $h, 'SERVIÇO PRESTADO');
        $codigos = $this->vCodTribNac($cServ->cTribNac) . ' / ' . $this->v($cServ->cTribMun);
        $this->campo($x + $w3, $y, $w3, $h, 'Código de Tributação Nacional/Municipal', $codigos);
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Código da NBS', '-');
        $y += $h;

        $municipioPrest = $this->v($this->infNFSe->xLocPrestacao);
        $ufPrest = $this->v($this->infNFSe->emit->enderNac->UF);
        $localTexto = $municipioPrest . ' / ' . $ufPrest . ' / -';
        $this->campo($x, $y, $maxW, $h, 'Local da Prestação / Sigla UF / País', $localTexto);
        $y += $h;

        $descTribNac = $this->v($this->infNFSe->xTribNac);
        $hDesc = 6;
        $this->caixa($x, $y, $maxW, $hDesc);
        $this->pTextBox($x, $y, $maxW, $hDesc, $descTribNac, array('font' => $this->fontePadrao, 'size' => 8, 'style' => ''), 'T', 'L', 0, '', false);
        $y += $hDesc;

        $descServ = $this->v($cServ->xDescServ, '');
        $hDescServ = 10;
        $this->caixa($x, $y, $maxW, $hDescServ);
        $aFontLabel = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'B');
        $this->pTextBox($x, $y, $maxW, $hDescServ, 'Descrição do Serviço', $aFontLabel, 'T', 'L', 0, '', false);
        $aFontValor = array('font' => $this->fontePadrao, 'size' => 7, 'style' => '');
        $this->pTextBox($x, $y + 3.2, $maxW, $hDescServ - 3.2, $descServ, $aFontValor, 'T', 'L', 0, '', false, 0, 0);
        $y += $hDescServ;

        return $y;
    }

    protected function pTributacaoMunicipal($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;

        $tribMun = $this->infDPS->valores->trib->tribMun;
        $tribISSQN = (string) $tribMun->tribISSQN;
        $tipoTributacao = $tribISSQN == '1' ? 'Operação Tributável' : ($tribISSQN == '' ? '-' : 'Não Tributável / Isento');

        $this->tituloSecao($x, $y, $w3, $h, 'TRIBUTAÇÃO MUNICIPAL (ISSQN)');
        $this->campo($x + $w3, $y, $w3, $h, 'Tipo de Tributação do ISSQN', $tipoTributacao, 7);
        $municipioIncid = $this->v($this->infNFSe->xLocIncid) . ' / ' . $this->v($this->infNFSe->emit->enderNac->UF) . ' / -';
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Município / Sigla UF / País de Incidência do ISSQN', $municipioIncid, 7);
        $y += $h;

        $valores = $this->infNFSe->valores;
        $w4 = $maxW / 4;
        $tpRet = (string) $tribMun->tpRetISSQN;
        $retencaoTexto = $tpRet == '1' ? 'Não Retido' : ($tpRet == '2' ? 'Retido' : '-');
        $this->campo($x, $y, $w4, $h, 'BC ISSQN', $this->vMoeda($valores->vBC));
        $this->campo($x + $w4, $y, $w4, $h, 'Alíquota Aplicada', $this->vPercent($valores->pAliqAplic));
        $this->campo($x + 2 * $w4, $y, $w4, $h, 'Retenção do ISSQN', $retencaoTexto);
        $this->campo($x + 3 * $w4, $y, $w4, $h, 'ISSQN Apurado', $this->vMoeda($valores->vISSQN));
        $y += $h;

        return $y;
    }

    protected function pTributacaoFederal($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;

        $this->tituloSecao($x, $y, $w3, $h, 'TRIBUTAÇÃO FEDERAL (EXCETO CBS)');
        $this->campo($x + $w3, $y, $w3, $h, 'IRRF', '-');
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Contribuição Previdenciária - Retida', '-', 7);
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Contribuições Sociais - Retidas', '-', 7);
        $this->campo($x + $w3, $y, $w3, $h, 'PIS - Débito Apuração Própria', '-', 7);
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'COFINS - Débito Apuração Própria', '-', 7);
        $y += $h;

        $this->campo($x, $y, $maxW, $h, 'Descrição Contrib. Sociais - Retidas', '-');
        $y += $h;

        return $y;
    }

    protected function pTributacaoIbsCbs($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;
        $hLabelLonga = 9;

        $this->tituloSecao($x, $y, $w3, $hLabelLonga, 'TRIBUTAÇÃO IBS/CBS');
        $this->campo($x + $w3, $y, $w3, $hLabelLonga, 'CST / cClassTrib', '- / -');
        $this->campo($x + 2 * $w3, $y, $w3, $hLabelLonga, 'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF', '- / - / - / -', 6, '', 4.4);
        $y += $hLabelLonga;

        $this->campo($x, $y, $w3, $h, 'Exclusões e Reduções da Base de Cálculo', $this->vMoeda($this->infNFSe->valores->vISSQN), 7);
        $this->campo($x + $w3, $y, $w3, $h, 'Base de Cálculo Após Exclusões e Reduções', '-', 7);
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Red. Alíquota IBS / Red. Alíquota CBS', '- / - / -', 7);
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Alíquota - IBS UF / IBS Mun', '- / -', 7);
        $this->campo($x + $w3, $y, $w3, $h, 'Alíq. Efetiva Municipal - IBS', '-');
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Valor Apurado Municipal - IBS', '-');
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Alíq. Efetiva Estadual - IBS', '-');
        $this->campo($x + $w3, $y, $w3, $h, 'Valor Apurado Estadual - IBS', '-');
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Valor Total Apurado - IBS', '-');
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Alíquota - CBS', '-');
        $this->campo($x + $w3, $y, $w3, $h, 'Alíquota Efetiva - CBS', '-');
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Valor Total Apurado - CBS', '-');
        $y += $h;

        return $y;
    }

    protected function pValorTotal($x, $y)
    {
        $maxW = $this->wPrint;
        $w3 = $maxW / 3;
        $h = 6;

        $vServ = $this->infDPS->valores->vServPrest->vServ;
        $vLiq = $this->infNFSe->valores->vLiq;

        $this->tituloSecao($x, $y, $w3, $h, 'VALOR TOTAL DA NFS-e');
        $this->campo($x + $w3, $y, $w3, $h, 'VALOR DA OPERAÇÃO / SERVIÇO', $this->vMoeda($vServ));
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'Desconto Incondicionado', '-');
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Desconto Condicionado', '-');
        $this->campo($x + $w3, $y, $w3, $h, 'Total das Retenções (ISSQN / Federais)', '-', 7);
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'VALOR LÍQUIDO DA NFS-e', $this->vMoeda($vLiq));
        $y += $h;

        $this->campo($x, $y, $w3, $h, 'Total do IBS/CBS', 'R$ 0,00');
        $this->campo($x + $w3, $y, $w3, $h, 'VALOR LÍQUIDO DA NFS-e + IBS/CBS', 'R$ 0,00', 7);
        $y += $h;

        return $y;
    }

    protected function pInformacoesComplementares($x, $y)
    {
        $maxW = $this->wPrint;
        $h = 6;
        $this->caixa($x, $y, $maxW, $h);
        $aFont = array('font' => $this->fontePadrao, 'size' => 7, 'style' => 'B');
        $this->pTextBox($x, $y, $maxW, $h, 'INFORMAÇÕES COMPLEMENTARES', $aFont, 'T', 'L', 0, '', false);
        $y += $h;

        $totTrib = $this->infDPS->valores->trib->totTrib->vTotTrib;
        $texto = 'Totais aproximados dos Tributos cfe. Lei n° 12.741/2012: '
            . 'Federais: ' . $this->vMoeda($totTrib->vTotTribFed) . '; '
            . 'Estaduais: ' . $this->vMoeda($totTrib->vTotTribEst) . '; '
            . 'Municipais: ' . $this->vMoeda($totTrib->vTotTribMun) . ';';
        $hTexto = 10;
        $this->caixa($x, $y, $maxW, $hTexto);
        $this->pTextBox($x, $y, $maxW, $hTexto, $texto, array('font' => $this->fontePadrao, 'size' => 7, 'style' => ''), 'T', 'L', 0, '', false);
        $y += $hTexto;

        return $y;
    }

    protected function pRodape($x)
    {
        $maxW = $this->wPrint;
        $y = 297 - 5 - 14;
        $h = 14;
        $w3 = $maxW / 3;

        $this->campo($x, $y, $w3, $h, 'DATA CIENTIFICAÇÃO:', '');
        $this->campo($x + $w3, $y, $w3, $h, 'IDENTIFICAÇÃO E ASSINATURA', '');
        $chaveTexto = $this->v($this->infNFSe->nNFSe) . ' / ' . $this->pIdChave();
        $this->campo($x + 2 * $w3, $y, $w3, $h, 'N° NFS-e / CHAVE NFS-e', $chaveTexto, 7);

        if (!empty($this->TextoRodape)) {
            $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'I');
            $this->pTextBox($x, $y + $h, $maxW, 4, $this->TextoRodape, $aFont, 'T', 'C', 0, '', false);
        }
    }
}
