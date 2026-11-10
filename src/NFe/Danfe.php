<?php

namespace NFePHP\DA\NFe;

use InvalidArgumentException;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\PdfTransparencia;
use NFePHP\DA\Legacy\Common;
use Com\Tecnick\Barcode\Barcode;

/**
 * DANFE (NF-e modelo 55) no leiaute da NT 2026.010 v1.00 - DANFE Reforma Tributária
 * (IBS, CBS e IS). Retrato (referência principal) e paisagem (alternativo).
 *
 * O leiaute anterior está em DanfeOld.php, cópia independente (sem herança) para que mudanças
 * em um não afetem o outro; para voltar a ele basta usar a classe DanfeOld.
 * Campos e quadros só aparecem preenchidos quando a informação existe no XML.
 */
class Danfe extends Common
{

    const FPDF_FONTPATH = 'font/';
    const SIT_CANCELADA = 1;
    const SIT_DENEGADA = 2;
    const SIT_DPEC = 3;
    const SIT_NONE = 0;
    
    /**
     * alinhamento padrão do logo (C-Center)
     *
     * @var string
     */
    public $logoAlign = 'C';
    /**
     * Posição
     * @var float
     */
    public $yDados = 0;
    /**
     * Situação
     * @var integer
     */
    public $situacaoExterna = 0;
    /**
     * Numero DPEC
     *
     * @var string
     */
    public $numero_registro_dpec = '';
    /**
     * quantidade de canhotos a serem montados, geralmente 1 ou 2
     *
     * @var integer
     */
    public $qCanhoto = 1;

    //###########################################################
    // INÍCIO ATRIBUTOS DE PARÂMETROS DE EXIBIÇÃO
    //###########################################################
    
    /**
     * Parâmetro para exibir ou ocultar os valores do PIS/COFINS.
     * @var boolean
     */
    public $exibirPIS = true;
    /**
     * Parâmetro para exibir ou ocultar os valores do ICMS Interestadual e Valor Total dos Impostos.
     * @var boolean
     */
    public $exibirIcmsInterestadual = true;
    /**
     * Parâmetro para exibir ou ocultar o texto sobre valor aproximado dos tributos.
     * @var boolean
     */
    public $exibirValorTributos = true;
    /**
     * Parâmetro para exibir ou ocultar o texto adicional sobre a forma de pagamento
     * e as informações de fatura/duplicata.
     * @var boolean
     */
    public $exibirTextoFatura = false;
    /**
     * Parâmetro do controle se deve concatenar automaticamente informações complementares
     * na descrição do produto, como por exemplo, informações sobre impostos.
     * @var boolean
     */
    public $descProdInfoComplemento = true;
    /**
     * Parâmetro do controle se deve gerar quebras de linha com "\n" a partir de ";" na descrição do produto.
     * @var boolean
     */
    public $descProdQuebraLinha = true;
    
    //###########################################################
    //PROPRIEDADES DA CLASSE
    //###########################################################
    
    /**
     * objeto fpdf()
     * @var object
     */
    protected $pdf;
    /**
     * XML NFe
     * @var string
     */
    protected $xml;
    /**
     * path para logomarca em jpg
     * @var string
     */
    protected $logomarca = '';
    /**
     * mesagens de erro
     * @var string
     */
    protected $errMsg = '';
    /**
     * status de erro true um erro ocorreu false sem erros
     * @var boolean
     */
    protected $errStatus = false;
    /**
     * orientação da DANFE
     * P-Retrato ou L-Paisagem
     * @var string
     */
    protected $orientacao = 'P';
    /**
     * formato do papel
     * @var string
     */
    protected $papel = 'A4';
    /**
     * destino do arquivo pdf
     * I-borwser, S-retorna o arquivo, D-força download, F-salva em arquivo local
     * @var string
     */
    protected $destino = 'I';
    /**
     * diretorio para salvar o pdf com a opção de destino = F
     * @var string
     */
    protected $pdfDir = '';
    /**
     * Nome da Fonte para gerar o DANFE
     * @var string
     */
    protected $fontePadrao = 'Times';
    /**
     * versão
     * @var string
     */
    protected $version = '2.2.8';
    /**
     * Texto
     * @var string
     */
    protected $textoAdic = '';
    /**
     * Largura
     * @var float
     */
    protected $wAdic = 0;
    /**
     * largura imprimivel, em milímetros
     * @var float
     */
    protected $wPrint;
    /**
     * Comprimento (altura) imprimivel, em milímetros
     * @var float
     */
    protected $hPrint;
    /**
     * largura do canhoto (25mm) apenas para a formatação paisagem
     * @var float
     */
    protected $wCanhoto = 25;
    /**
     * Formato chave
     * @var string
     */
    protected $formatoChave = "#### #### #### #### #### #### #### #### #### #### ####";
    /**
     * quantidade de itens já processados na montagem do DANFE
     * @var integer
     */
    protected $qtdeItensProc;

    /**
     * Document
     * @var DOMDocument
     */
    protected $dom;
    /**
     * Node
     * @var DOMNode
     */
    protected $infNFe;
    /**
     * Node
     * @var DOMNode
     */
    protected $ide;
    /**
     * Node
     * @var DOMNode
     */
    protected $entrega;
    /**
     * Node
     * @var DOMNode
     */
    protected $retirada;
    /**
     * Node
     * @var DOMNode
     */
    protected $emit;
    /**
     * Node
     * @var DOMNode
     */
    protected $dest;
    /**
     * Node
     * @var DOMNode
     */
    protected $enderEmit;
    /**
     * Node
     * @var DOMNode
     */
    protected $enderDest;
    /**
     * Node
     * @var DOMNode
     */
    protected $det;
    /**
     * Node
     * @var DOMNode
     */
    protected $cobr;
    /**
     * Node
     * @var DOMNode
     */
    protected $dup;
    /**
     * Node
     * @var DOMNode
     */
    protected $ICMSTot;
    /**
     * Node
     * @var DOMNode
     */
    protected $ISSQNtot;
    /**
     * Node
     * @var DOMNode
     */
    protected $transp;
    /**
     * Node
     * @var DOMNode
     */
    protected $transporta;
    /**
     * Node
     * @var DOMNode
     */
    protected $veicTransp;
    /**
     * Node reboque
     * @var DOMNode
     */
    protected $reboque;
    /**
     * Node infAdic
     * @var DOMNode
     */
    protected $infAdic;
    /**
     * Tipo de emissão
     * @var integer
     */
    protected $tpEmis;
    /**
     * Node infProt
     * @var DOMNode
     */
    protected $infProt;
    /**
     * 1-Retrato/ 2-Paisagem
     * @var integer
     */
    protected $tpImp;
    /**
     * Node compra
     * @var DOMNode
     */
    protected $compra;
    /**
     * ativa ou desativa o modo de debug
     * @var integer
     */
    protected $debugMode=2;

    protected $TextoRodape = ''; //VALTER
    protected $nfeProc;
    protected $situacao_externa;

    /**
     * Node IBSCBSTot (totais do IBS/CBS - NT 2025.002)
     * @var DOMNode
     */
    protected $IBSCBSTot;
    /**
     * Node ISTot (total do Imposto Seletivo)
     * @var DOMNode
     */
    protected $ISTot;
    /**
     * Conteúdo do infNFeSupl/qrCode ('' quando a NF-e não tem)
     * @var string
     */
    protected $qrCode = '';
    /**
     * Origem do conteúdo na folha (margens)
     * @var float
     */
    protected $xIni = 2;
    protected $yIni = 2;
    /**
     * Medidas do leiaute (mm)
     * @var float
     */
    protected $hCabecalho = 53;
    protected $hTopoCab = 32;
    protected $hCampo = 7;
    protected $hTitulo = 4.5;
    protected $hCabecalhoItens = 6;
    protected $gap = 1.2;
    protected $lhItem = 2.45;
    protected $tamItem = 6;
    protected $tamRotulo = 5.5;
    protected $tamValor = 8;
    protected $tamTitulo = 7.5;
    /**
     * fator das fontes do cabeçalho (paisagem menor)
     * @var float
     */
    protected $kFonte = 1;

    /**
     * __construct
     *
     * @name  __construct
     * @param string  $docXML      Conteúdo XML da NF-e (com ou sem a tag nfeProc)
     * @param string  $sOrientacao (Opcional) Orientação da impressão P-retrato L-Paisagem
     * @param string  $sPapel      Tamanho do papel (Ex. A4)
     * @param string  $sPathLogo   Caminho para o arquivo do logo
     * @param string  $sDestino    Estabelece a direção do envio do documento PDF I-browser D-browser com download S-
     * @param string  $sDirPDF     Caminho para o diretorio de armazenamento dos arquivos PDF
     * @param string  $fonteDANFE  Nome da fonte alternativa do DAnfe
     * @param integer $mododebug   0-Não 1-Sim e 2-nada (2 default)
     */
    public function __construct(
        $docXML = '',
        $sOrientacao = '',
        $sPapel = '',
        $sPathLogo = '',
        $sDestino = 'I',
        $sDirPDF = '',
        $fonteDANFE = '',
        $mododebug = 2
    ) {
        //set_time_limit(1800);
        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($mododebug == 1) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        }
        if ($mododebug == 0) {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        $this->orientacao   = $sOrientacao;
        $this->papel        = $sPapel;
        $this->pdf          = '';
        $this->xml          = $docXML;
        $this->logomarca    = $sPathLogo;
        $this->destino      = $sDestino;
        $this->pdfDir       = $sDirPDF;
        // verifica se foi passa a fonte a ser usada
        if (empty($fonteDANFE)) {
            //o modelo da NT 2026.010 usa fonte sem serifa
            $this->fontePadrao = 'Helvetica';
        } else {
            $this->fontePadrao = $fonteDANFE;
        }
        //se for passado o xml
        if (! empty($this->xml)) {
            $this->dom = new Dom();
            $this->dom->loadXML($this->xml);
            $this->nfeProc    = $this->dom->getElementsByTagName("nfeProc")->item(0);
            $this->infNFe     = $this->dom->getElementsByTagName("infNFe")->item(0);
            $this->ide        = $this->dom->getElementsByTagName("ide")->item(0);
            $this->entrega    = $this->dom->getElementsByTagName("entrega")->item(0);
            $this->retirada   = $this->dom->getElementsByTagName("retirada")->item(0);
            $this->emit       = $this->dom->getElementsByTagName("emit")->item(0);
            $this->dest       = $this->dom->getElementsByTagName("dest")->item(0);
            $this->enderEmit  = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->enderDest  = $this->dom->getElementsByTagName("enderDest")->item(0);
            $this->det        = $this->dom->getElementsByTagName("det");
            $this->cobr       = $this->dom->getElementsByTagName("cobr")->item(0);
            $this->dup        = $this->dom->getElementsByTagName('dup');
            $this->ICMSTot    = $this->dom->getElementsByTagName("ICMSTot")->item(0);
            $this->ISSQNtot   = $this->dom->getElementsByTagName("ISSQNtot")->item(0);
            $this->transp     = $this->dom->getElementsByTagName("transp")->item(0);
            $this->transporta = $this->dom->getElementsByTagName("transporta")->item(0);
            $this->veicTransp = $this->dom->getElementsByTagName("veicTransp")->item(0);
            $this->reboque    = $this->dom->getElementsByTagName("reboque")->item(0);
            $this->infAdic    = $this->dom->getElementsByTagName("infAdic")->item(0);
            $this->compra     = $this->dom->getElementsByTagName("compra")->item(0);
            $this->tpEmis     = $this->ide->getElementsByTagName("tpEmis")->item(0)->nodeValue;
            $this->tpImp      = $this->ide->getElementsByTagName("tpImp")->item(0)->nodeValue;
            $this->infProt    = $this->dom->getElementsByTagName("infProt")->item(0);
            $this->IBSCBSTot  = $this->dom->getElementsByTagName("IBSCBSTot")->item(0);
            $this->ISTot      = $this->dom->getElementsByTagName("ISTot")->item(0);
            $qrCode = $this->dom->getElementsByTagName("qrCode")->item(0);
            $this->qrCode     = $qrCode ? trim($qrCode->nodeValue) : '';
            //valida se o XML é uma NF-e modelo 55, pois não pode ser 65 (NFC-e)
            if ($this->pSimpleGetValue($this->ide, "mod") != '55') {
                throw new InvalidArgumentException("O xml do DANFE deve ser uma NF-e modelo 55");
            }
        }
    }

    /**
     * monta
     *
     * @name   monta
     * @param  string $orientacao
     * @param  string $papel
     * @param  string $logoAlign
     * @return string
     */
    public function monta(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $situacaoExterna = self::SIT_NONE,
        $classPdf = false,
        $dpecNumReg = '',
        $margSup = 2,
        $margEsq = 2,
        $margInf = 2
    ) {
        return $this->montaDANFE(
            $orientacao,
            $papel,
            $logoAlign,
            $situacaoExterna,
            $classPdf,
            $dpecNumReg,
            $margSup,
            $margEsq,
            $margInf
        );
    }

    /**
     * printDocument
     *
     * @param  string $nome
     * @param  string $destino
     * @param  string $printer
     * @return object pdf
     */
    public function printDocument($nome = '', $destino = 'I', $printer = '')
    {
        return $this->printDANFE($nome, $destino, $printer);
    }

    /**
     * printDANFE
     * Esta função envia a DANFE em PDF criada para o dispositivo informado.
     * O destino da impressão pode ser :
     * I-browser
     * D-browser com download
     * F-salva em um arquivo local com o nome informado
     * S-retorna o documento como uma string e o nome é ignorado.
     * Para enviar o pdf diretamente para uma impressora indique o
     * nome da impressora e o destino deve ser 'S'.
     * Author: Valter
     *
     * @param  string $nome Path completo com o nome do arquivo pdf
     * @param  string $destino Direção do envio do PDF
     * @param  string $printer Identificação da impressora no sistema
     * @return string Caso o destino seja S o pdf é retornado como uma string
     * @todo Rotina de impressão direta do arquivo pdf criado
     */
    public function printDANFE($nome = '', $destino = 'I', $printer = '')
    {
        $arq = $this->pdf->Output($nome, $destino);
        if ($destino == 'S') {
            //aqui pode entrar a rotina de impressão direta
        }
        return $arq;
    } //fim função printDANFE

    /**
     * montaDANFE
     * Monta o DANFE no leiaute da NT 2026.010 (retrato ou paisagem).
     * Folha 1: canhoto, cabeçalho, destinatário, fatura, totais, ISSQN, transporte, itens e dados
     * adicionais. Folhas seguintes: cabeçalho e itens.
     *
     * @param  string $orientacao (Opcional) P-retrato ou L-paisagem; vazio usa o tpImp da NF-e
     * @param  string $papel      (Opcional) Tamanho do papel (A4)
     * @return string O ID da NFe numero de 44 digitos extraido do arquivo XML
     */
    public function montaDANFE(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $situacaoExterna = self::SIT_NONE,
        $classPdf = false,
        $depecNumReg = '',
        $margSup = 2,
        $margEsq = 2,
        $margInf = 2
    ) {
        //se a orientação estiver em branco utilizar o padrão estabelecido na NF
        if ($orientacao == '') {
            $orientacao = ($this->tpImp == '1') ? 'P' : 'L';
        }
        $this->orientacao = $orientacao;
        $this->pDefineMedidas();
        $this->pAdicionaLogoPeloCnpj();
        $this->papel = $papel;
        $this->logoAlign = $logoAlign;
        $this->situacao_externa = $situacaoExterna;
        $this->numero_registro_dpec = $depecNumReg;
        if ($classPdf) {
            $this->pdf = $classPdf;
        } else {
            $this->pdf = new PdfTransparencia($this->orientacao, 'mm', $this->papel);
        }
        if ($this->orientacao == 'P') {
            $maxW = 210;
            $maxH = 297;
            $this->wCanhoto = 0;
        } else {
            $maxW = 297;
            $maxH = 210;
            //canhoto na faixa direita da folha 1
            $this->wCanhoto = 22 * max(0, (int) $this->qCanhoto);
        }
        $this->xIni = $margEsq;
        $this->yIni = $margSup;
        $this->wPrint = $maxW - ($margEsq * 2);
        $this->hPrint = $maxH - $margSup - $margInf;
        $yLimite = $margSup + $this->hPrint;
        $this->pdf->aliasNbPages();
        $this->pdf->setMargins($margEsq, $margSup);
        $this->pdf->setDrawColor(0, 0, 0);
        $this->pdf->setFillColor(255, 255, 255);
        $this->pdf->open();
        $this->pdf->addPage($this->orientacao, $this->papel);
        $this->pdf->setLineWidth(0.2);
        $this->pdf->setTextColor(0, 0, 0);

        //largura útil da folha 1 (na paisagem desconta a faixa do canhoto)
        $wPag1 = $this->wPrint - $this->wCanhoto;
        $this->pMarcasDaguaFolha($wPag1, true);
        $this->pMontaTextoAdic();
        $hDadosAdic = $this->pAlturaDadosAdicionais($wPag1);
        $hRodape = 3;
        $g = $this->gap;

        //##################################################################
        // FOLHA 1 - quadros antes dos itens (o cabeçalho é desenhado depois, quando
        // já se sabe o total de folhas)
        //##################################################################
        $x = $margEsq;
        $y = $margSup;
        if ($this->orientacao == 'P') {
            for ($i = 1; $i <= $this->qCanhoto; $i++) {
                $y = $this->pCanhotoRetrato($x, $y);
            }
        } else {
            for ($i = 0; $i < $this->qCanhoto; $i++) {
                $this->pCanhotoPaisagem($x + $wPag1 + ($i * 22) + 1, $margSup, 21, $this->hPrint);
            }
        }
        $yCabecalho = $y;
        $y += $this->hCabecalho + $g;
        $y = $this->pDestinatarioDANFE($x, $y, $wPag1) + $g;
        $y = $this->pFaturaDANFE($x, $y, $wPag1);
        $y = $this->pTotaisProdutosDANFE($x, $y, $wPag1) + $g;
        $y = $this->pTotaisIcmsDANFE($x, $y, $wPag1) + $g;
        $y = $this->pTotaisIbsCbsDANFE($x, $y, $wPag1) + $g;
        $y = $this->pIssqnDANFE($x, $y, $wPag1);
        $y = $this->pTransporteDANFE($x, $y, $wPag1);
        $yItens = $y;

        //##################################################################
        // PAGINAÇÃO DOS ITENS (altura real de cada item)
        //##################################################################
        $hCabItens = $this->hTitulo + $this->hCabecalhoItens;
        $totItens = $this->det->length;
        $hDispo1 = $yLimite - $hRodape - $hDadosAdic - $g - $yItens - $hCabItens;
        $hDispo2 = $yLimite - $hRodape - ($margSup + $this->hCabecalho + $g) - $hCabItens;
        //com todos os quadros opcionais (sobretudo na paisagem) pode não sobrar espaço na folha 1:
        //ela fica sem itens e, se nem os dados adicionais couberem, eles vão para o fim da última folha
        $dadosAdicFolha1 = true;
        if ($totItens > 0 && $hDispo1 < $this->pAlturaItem($this->det->item(0), $wPag1)) {
            $hDispo1 = 0;
            if ($yItens + $hDadosAdic > $yLimite - $hRodape) {
                $dadosAdicFolha1 = false;
            }
        }
        $paginas = array();
        $i = 0;
        do {
            $pag = count($paginas) + 1;
            $hDispo = ($pag == 1) ? $hDispo1 : $hDispo2;
            $wPag = ($pag == 1) ? $wPag1 : $this->wPrint;
            $ini = $i;
            $usado = 0;
            while ($i < $totItens && $hDispo > 0) {
                $hItem = $this->pAlturaItem($this->det->item($i), $wPag);
                if ($usado + $hItem > $hDispo && $i > $ini) {
                    break;
                }
                $usado += $hItem;
                $i++;
            }
            $paginas[] = array($ini, $i, $usado);
        } while ($i < $totItens);
        if (!$dadosAdicFolha1) {
            //dados adicionais depois do último item, na largura das folhas seguintes
            $hDadosAdic = $this->pAlturaDadosAdicionais($this->wPrint);
            $ultima = $paginas[count($paginas) - 1];
            $espacoItens = ($ultima[1] > $ultima[0]) ? $hCabItens + $g : 0;
            if ($ultima[2] + $espacoItens + $hDadosAdic > $hDispo2 + $hCabItens) {
                $paginas[] = array($totItens, $totItens, 0);
            }
        }
        $totPag = count($paginas);

        //cabeçalho e itens da folha 1
        $this->pCabecalhoDANFE($x, $yCabecalho, 1, $totPag, $wPag1);
        $y = $yItens;
        if ($paginas[0][1] > $paginas[0][0]) {
            $y = $this->pItensDANFE($x, $y, $wPag1, $paginas[0][0], $paginas[0][1]) + $g;
        }
        if ($dadosAdicFolha1) {
            $y = $this->pDadosAdicionaisDANFE($x, $y, $wPag1, $hDadosAdic);
        }
        $this->pRodape($x, $wPag1);
        $this->pMarcasDaguaFolha($wPag1, false);

        //folhas seguintes
        for ($n = 2; $n <= $totPag; $n++) {
            $this->pdf->setMargins($margEsq, $margSup);
            $this->pdf->addPage($this->orientacao, $this->papel);
            $this->pdf->setLineWidth(0.2);
            $this->pdf->setTextColor(0, 0, 0);
            $this->pMarcasDaguaFolha($this->wPrint, true);
            $y = $this->pCabecalhoDANFE($margEsq, $margSup, $n, $totPag, $this->wPrint) + $g;
            if ($paginas[$n - 1][1] > $paginas[$n - 1][0]) {
                $y = $this->pItensDANFE($margEsq, $y, $this->wPrint, $paginas[$n - 1][0], $paginas[$n - 1][1]) + $g;
            }
            if ($n == $totPag && !$dadosAdicFolha1) {
                $y = $this->pDadosAdicionaisDANFE($margEsq, $y, $this->wPrint, $hDadosAdic);
            }
            $this->pRodape($margEsq, $this->wPrint);
            $this->pMarcasDaguaFolha($this->wPrint, false);
        }
        $this->qtdeItensProc = $totItens;
        //retorna o ID na NFe
        if ($classPdf !== false) {
            $aR = array(
             'id' => str_replace('NFe', '', $this->infNFe->getAttribute("Id")),
             'classe_PDF' => $this->pdf);
            return $aR;
        } else {
            return str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        }
    }//fim da função montaDANFE

    /**
     * Medidas do leiaute por orientação: a paisagem é compacta como o modelo da NT (pág. 9)
     */
    protected function pDefineMedidas()
    {
        if ($this->orientacao == 'P') {
            $this->hTopoCab = 32;
            $this->hCampo = 7;
            $this->hTitulo = 4.5;
            $this->hCabecalhoItens = 6;
            $this->gap = 1.2;
            $this->tamRotulo = 5.5;
            $this->tamValor = 8;
            $this->tamTitulo = 7.5;
            $this->kFonte = 1;
        } else {
            $this->hTopoCab = 23;
            $this->hCampo = 5.2;
            $this->hTitulo = 3.6;
            $this->hCabecalhoItens = 5;
            $this->gap = 0.8;
            $this->tamRotulo = 5;
            $this->tamValor = 7;
            $this->tamTitulo = 6.5;
            $this->kFonte = 0.85;
        }
        $this->hCabecalho = $this->hTopoCab + (3 * $this->hCampo);
    }

    /**
     * pMontaTextoAdic
     * Monta o texto das INFORMAÇÕES COMPLEMENTARES (mesmo conteúdo do DANFE anterior)
     */
    protected function pMontaTextoAdic()
    {
        $this->textoAdic = '';
        if (isset($this->retirada)) {
            $txRetCNPJ = ! empty($this->retirada->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("CNPJ")->item(0)->nodeValue :
                '';
            $txRetxLgr = ! empty($this->retirada->getElementsByTagName("xLgr")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xLgr")->item(0)->nodeValue :
                '';
            $txRetnro = ! empty($this->retirada->getElementsByTagName("nro")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("nro")->item(0)->nodeValue :
                's/n';
            $txRetxCpl = $this->pSimpleGetValue($this->retirada, "xCpl", " - ");
            $txRetxBairro = ! empty($this->retirada->getElementsByTagName("xBairro")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xBairro")->item(0)->nodeValue :
                '';
            $txRetxMun = ! empty($this->retirada->getElementsByTagName("xMun")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("xMun")->item(0)->nodeValue :
                '';
            $txRetUF = ! empty($this->retirada->getElementsByTagName("UF")->item(0)->nodeValue) ?
                $this->retirada->getElementsByTagName("UF")->item(0)->nodeValue :
                '';
            $this->textoAdic .= "LOCAL DE RETIRADA : ".
                    $txRetCNPJ.
                    '-' .
                    $txRetxLgr .
                    ', ' .
                    $txRetnro .
                    ' ' .
                    $txRetxCpl .
                    ' - ' .
                    $txRetxBairro .
                    ' ' .
                    $txRetxMun .
                    ' - ' .
                    $txRetUF .
                    "\r\n";
        }
        //dados do local de entrega da mercadoria
        if (isset($this->entrega)) {
            $txRetCNPJ = ! empty($this->entrega->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("CNPJ")->item(0)->nodeValue : '';
            $txRetxLgr = ! empty($this->entrega->getElementsByTagName("xLgr")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xLgr")->item(0)->nodeValue : '';
            $txRetnro = ! empty($this->entrega->getElementsByTagName("nro")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("nro")->item(0)->nodeValue : 's/n';
            $txRetxCpl = $this->pSimpleGetValue($this->entrega, "xCpl", " - ");
            $txRetxBairro = ! empty($this->entrega->getElementsByTagName("xBairro")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xBairro")->item(0)->nodeValue : '';
            $txRetxMun = ! empty($this->entrega->getElementsByTagName("xMun")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("xMun")->item(0)->nodeValue : '';
            $txRetUF = ! empty($this->entrega->getElementsByTagName("UF")->item(0)->nodeValue) ?
                $this->entrega->getElementsByTagName("UF")->item(0)->nodeValue : '';
            if ($this->textoAdic != '') {
                $this->textoAdic .= ". \r\n";
            }
            $this->textoAdic .= "LOCAL DE ENTREGA : ".$txRetCNPJ.'-'.$txRetxLgr.', '.$txRetnro.' '.$txRetxCpl.
               ' - '.$txRetxBairro.' '.$txRetxMun.' - '.$txRetUF."\r\n";
        }
        //informações adicionais
        $this->textoAdic .= $this->pGeraInformacoesDasNotasReferenciadas();
        if (isset($this->infAdic)) {
            $i = 0;
            if ($this->textoAdic != '') {
                $this->textoAdic .= ". \r\n";
            }
            $this->textoAdic .= ! empty($this->infAdic->getElementsByTagName("infCpl")->item(0)->nodeValue) ?
                'Inf. Contribuinte: ' .
                trim($this->pAnfavea($this->infAdic->getElementsByTagName("infCpl")->item(0)->nodeValue)) : '';
            $infPedido = $this->pGeraInformacoesDaTagCompra();
            if ($infPedido != "") {
                $this->textoAdic .= $infPedido;
            }
            $this->textoAdic .= $this->pSimpleGetValue($this->dest, "email", ' Email do Destinatário: ');
            $this->textoAdic .= ! empty($this->infAdic->getElementsByTagName("infAdFisco")->item(0)->nodeValue) ?
                "\r\n Inf. fisco: " .
                trim($this->infAdic->getElementsByTagName("infAdFisco")->item(0)->nodeValue) : '';
            $obsCont = $this->infAdic->getElementsByTagName("obsCont");
            if (isset($obsCont)) {
                foreach ($obsCont as $obs) {
                    $campo =  $obsCont->item($i)->getAttribute("xCampo");
                    $xTexto = ! empty($obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue) ?
                        $obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue : '';
                    $this->textoAdic .= "\r\n" . $campo . ':  ' . trim($xTexto);
                    $i++;
                }
            }
        }
        //INCLUSO pela NT 2013.003 Lei da Transparência
        //verificar se a informação sobre o valor aproximado dos tributos
        //já se encontra no campo de informações adicionais
        if ($this->exibirValorTributos) {
            $flagVTT = strpos(strtolower(trim($this->textoAdic)), 'valor');
            $flagVTT = $flagVTT || strpos(strtolower(trim($this->textoAdic)), 'vl');
            $flagVTT = $flagVTT && strpos(strtolower(trim($this->textoAdic)), 'aprox');
            $flagVTT = $flagVTT && (strpos(strtolower(trim($this->textoAdic)), 'trib') ||
                    strpos(strtolower(trim($this->textoAdic)), 'imp'));
            $vTotTrib = $this->pSimpleGetValue($this->ICMSTot, 'vTotTrib');
            if ($vTotTrib != '' && !$flagVTT) {
                $this->textoAdic .= "\n Valor Aproximado dos Tributos : R$ " . number_format($vTotTrib, 2, ",", ".");
            }
        }
        //fim da alteração NT 2013.003 Lei da Transparência
        $this->textoAdic = str_replace(";", "\n", $this->textoAdic);
    }

    /**
     * anfavea
     * Função para transformar o campo cdata do padrão ANFAVEA para
     * texto imprimível
     *
     * @param  string $cdata campo CDATA
     * @return string conteúdo do campo CDATA como string
     */
    private function pAnfavea($cdata = '')
    {
        if ($cdata == '') {
            return '';
        }
        //remove qualquer texto antes ou depois da tag CDATA
        $cdata = str_replace('<![CDATA[', '<CDATA>', $cdata);
        $cdata = str_replace(']]>', '</CDATA>', $cdata);
        $cdata = preg_replace('/\s\s+/', ' ', $cdata);
        $cdata = str_replace("> <", "><", $cdata);
        $len = strlen($cdata);
        $startPos = strpos($cdata, '<');
        if ($startPos === false) {
            return $cdata;
        }
        for ($x=$len; $x>0; $x--) {
            if (substr($cdata, $x, 1) == '>') {
                $endPos = $x;
                break;
            }
        }
        if ($startPos > 0) {
            $parte1 = substr($cdata, 0, $startPos);
        } else {
            $parte1 = '';
        }
        $parte2 = substr($cdata, $startPos, $endPos-$startPos+1);
        if ($endPos < $len) {
            $parte3 = substr($cdata, $endPos + 1, $len - $endPos - 1);
        } else {
            $parte3 = '';
        }
        $texto = trim($parte1).' '.trim($parte3);
        if (strpos($parte2, '<CDATA>') === false) {
            $cdata = '<CDATA>'.$parte2.'</CDATA>';
        } else {
            $cdata = $parte2;
        }
        //carrega o xml CDATA em um objeto DOM
        $dom = new Dom();
        $dom->loadXML($cdata, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        //$xml = $dom->saveXML();
        //grupo CDATA infADprod
        $id = $dom->getElementsByTagName('id')->item(0);
        $div = $dom->getElementsByTagName('div')->item(0);
        $entg = $dom->getElementsByTagName('entg')->item(0);
        $dest = $dom->getElementsByTagName('dest')->item(0);
        $ctl = $dom->getElementsByTagName('ctl')->item(0);
        $ref = $dom->getElementsByTagName('ref')->item(0);
        if (isset($id)) {
            if ($id->hasAttributes()) {
                foreach ($id->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($div)) {
            if ($div->hasAttributes()) {
                foreach ($div->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($entg)) {
            if ($entg->hasAttributes()) {
                foreach ($entg->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($dest)) {
            if ($dest->hasAttributes()) {
                foreach ($dest->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($ctl)) {
            if ($ctl->hasAttributes()) {
                foreach ($ctl->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($ref)) {
            if ($ref->hasAttributes()) {
                foreach ($ref->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        //grupo CADATA infCpl
        $t = $dom->getElementsByTagName('transmissor')->item(0);
        $r = $dom->getElementsByTagName('receptor')->item(0);
        $versao = ! empty($dom->getElementsByTagName('versao')->item(0)->nodeValue) ?
            'Versao:'.$dom->getElementsByTagName('versao')->item(0)->nodeValue.' ' : '';
        $especieNF = ! empty($dom->getElementsByTagName('especieNF')->item(0)->nodeValue) ?
            'Especie:'.$dom->getElementsByTagName('especieNF')->item(0)->nodeValue.' ' : '';
        $fabEntrega = ! empty($dom->getElementsByTagName('fabEntrega')->item(0)->nodeValue) ?
            'Entrega:'.$dom->getElementsByTagName('fabEntrega')->item(0)->nodeValue.' ' : '';
        $dca = ! empty($dom->getElementsByTagName('dca')->item(0)->nodeValue) ?
            'dca:'.$dom->getElementsByTagName('dca')->item(0)->nodeValue.' ' : '';
        $texto .= "".$versao.$especieNF.$fabEntrega.$dca;
        if (isset($t)) {
            if ($t->hasAttributes()) {
                $texto .= " Transmissor ";
                foreach ($t->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($r)) {
            if ($r->hasAttributes()) {
                $texto .= " Receptor ";
                foreach ($r->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        return $texto;
    }
    
    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }

    protected function pNotaCancelada()
    {
        if (!isset($this->nfeProc)) {
            return false;
        }
        $cStat = $this->pSimpleGetValue($this->nfeProc, "cStat");
        return $cStat == '101' ||
                $cStat == '151' ||
                $cStat == '135' ||
                $cStat == '155' ||
                $this->situacao_externa == self::SIT_CANCELADA;
    }

    protected function pNotaDPEC()
    {
        return $this->situacao_externa == self::SIT_DPEC && $this->numero_registro_dpec != '';
    }

    protected function pNotaDenegada()
    {
        if (!isset($this->nfeProc)) {
            return false;
        }
        //NÃO ERA NECESSÁRIO ESSA FUNÇÃO POIS SÓ SE USA
        //1 VEZ NO ARQUIVO INTEIRO
        $cStat = $this->pSimpleGetValue($this->nfeProc, "cStat");
        return $cStat == '110' ||
               $cStat == '301' ||
               $cStat == '302' ||
               $this->situacao_externa == self::SIT_DENEGADA;
    }

    //###########################################################
    // HELPERS DE DESENHO E LEITURA (NT 2026.010)
    //###########################################################

    /**
     * Converte o texto (UTF-8) para o charset do FPDF
     */
    protected function pEnc($texto)
    {
        $texto = str_replace("\r", '', html_entity_decode(trim((string) $texto), ENT_QUOTES, 'UTF-8'));
        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    protected function pFonte($tam, $estilo = '')
    {
        $this->pdf->setFont($this->fontePadrao, $estilo, $tam);
    }

    /**
     * Escreve uma linha (texto já convertido por pEnc) na linha de base $yBase,
     * reduzindo a fonte até caber em $w
     */
    protected function pEscreve($x, $yBase, $w, $texto, $tam, $estilo = '', $alinh = 'L', $cinza = false)
    {
        if ($texto === '' || $texto === null) {
            return;
        }
        $this->pFonte($tam, $estilo);
        $comp = $this->pdf->getStringWidth($texto);
        while ($comp > ($w - 1) && $tam > 3) {
            $tam -= 0.25;
            $this->pFonte($tam, $estilo);
            $comp = $this->pdf->getStringWidth($texto);
        }
        if ($alinh == 'R') {
            $x1 = $x + $w - $comp - 0.6;
        } elseif ($alinh == 'C') {
            $x1 = $x + (($w - $comp) / 2);
        } else {
            $x1 = $x + 0.6;
        }
        if ($cinza) {
            $this->pdf->setTextColor(100, 100, 100);
        }
        $this->pdf->text($x1, $yBase, $texto);
        if ($cinza) {
            $this->pdf->setTextColor(0, 0, 0);
        }
    }

    /**
     * Quebra o texto (UTF-8) em linhas que cabem em $w; devolve as linhas já convertidas
     */
    protected function pQuebra($texto, $w, $tam, $estilo = '')
    {
        $t = $this->pEnc($texto);
        if ($t === '') {
            return array();
        }
        $this->pFonte($tam, $estilo);
        $this->pdf->wordWrap($t, $w - 1.2);
        return explode("\n", $t);
    }

    /**
     * Campo com rótulo (cinza, no topo) e valor (preto, na base), com borda
     */
    protected function pCampo($x, $y, $w, $h, $rotulo, $valor, $alinh = 'L')
    {
        $this->pdf->rect($x, $y, $w, $h);
        $this->pEscreve($x, $y + ($this->tamRotulo * 0.3528) + 0.4, $w, $this->pEnc($rotulo), $this->tamRotulo, '', 'L', true);
        $this->pEscreve($x, $y + $h - ($this->orientacao == 'P' ? 1.2 : 0.8), $w, $this->pEnc($valor), $this->tamValor, '', $alinh);
        return $x + $w;
    }

    /**
     * Faixa de título de quadro (cinza-clara, negrito); devolve o y abaixo dela
     */
    protected function pTitulo($x, $y, $w, $texto, $alinh = 'L')
    {
        $this->pdf->setFillColor(232, 232, 232);
        $this->pdf->rect($x, $y, $w, $this->hTitulo, 'DF');
        $this->pdf->setFillColor(255, 255, 255);
        $this->pEscreve($x, $y + $this->hTitulo - 1.2, $w, $this->pEnc($texto), $this->tamTitulo, 'B', $alinh);
        return $y + $this->hTitulo;
    }

    /**
     * Linha de campos: cada campo é array(rotulo, valor, fração da largura, alinhamento);
     * o último ocupa o restante da largura
     */
    protected function pLinhaCampos($x, $y, $w, $campos, $h = null)
    {
        if ($h === null) {
            $h = $this->hCampo;
        }
        $xi = $x;
        $n = count($campos);
        foreach ($campos as $k => $c) {
            $wc = ($k == $n - 1) ? ($x + $w - $xi) : round($w * $c[2], 2);
            $this->pCampo($xi, $y, $wc, $h, $c[0], $c[1], isset($c[3]) ? $c[3] : 'L');
            $xi += $wc;
        }
        return $y + $h;
    }

    /**
     * Texto com pontilhado entre o rótulo e o valor ("ICMS........1.234,56")
     */
    protected function pLider($x, $yBase, $w, $rotulo, $valor, $tam, $rotuloCinza = true)
    {
        $rot = $this->pEnc($rotulo);
        $val = $this->pEnc($valor);
        $this->pFonte($tam);
        while (($this->pdf->getStringWidth($rot . '..' . $val) > $w - 0.8) && $tam > 3) {
            $tam -= 0.25;
            $this->pFonte($tam);
        }
        $wRot = $this->pdf->getStringWidth($rot);
        $wVal = $this->pdf->getStringWidth($val);
        $wPonto = $this->pdf->getStringWidth('.');
        $nPontos = max(2, (int) floor(($w - 0.8 - $wRot - $wVal) / $wPonto));
        if ($rotuloCinza) {
            $this->pdf->setTextColor(100, 100, 100);
        }
        $this->pdf->text($x + 0.4, $yBase, $rot . str_repeat('.', $nPontos));
        $this->pdf->setTextColor(0, 0, 0);
        $this->pdf->text($x + $w - 0.4 - $wVal, $yBase, $val);
    }

    /**
     * Desenha no sistema girado 90° (texto de baixo para cima). A área na folha começa em
     * ($x, $y) e tem altura $comprimento; $desenho recebe a origem ($ox, $oy) do sistema girado,
     * onde a largura é o $comprimento e a altura é a espessura da área.
     */
    protected function pGirado($x, $y, $comprimento, $desenho)
    {
        $this->pdf->rotate(90, $x, $y + $comprimento);
        $desenho($x, $y + $comprimento);
        $this->pdf->rotate(0);
        //o "Q" do rotate(0) restaura a fonte do PDF, mas não o cache do FPDF: força o próximo setFont
        $this->pdf->setFont($this->fontePadrao, '', 0.01);
    }

    protected function pNum($valor, $decimais = 2)
    {
        return number_format((float) $valor, $decimais, ',', '.');
    }

    /**
     * Primeiro nó $tag dentro de $no (ou null)
     */
    protected function pNo($no, $tag)
    {
        if (empty($no)) {
            return null;
        }
        $n = $no->getElementsByTagName($tag)->item(0);
        return $n ? $n : null;
    }

    /**
     * Valor da tag dentro do nó, ou null quando o nó ou a tag não existem
     */
    protected function pTag($no, $tag)
    {
        $n = $this->pNo($no, $tag);
        return $n ? trim($n->nodeValue) : null;
    }

    /**
     * Valor numérico formatado da tag, ou '' quando ela não existe no XML
     */
    protected function pTotalValor($no, $tag, $decimais = 2)
    {
        $v = $this->pTag($no, $tag);
        if ($v === null || $v === '' || !is_numeric($v)) {
            return '';
        }
        return $this->pNum($v, $decimais);
    }

    /**
     * true quando ao menos uma das tags existe com valor maior que zero
     */
    protected function pTemValorPositivo($no, array $tags)
    {
        foreach ($tags as $tag) {
            if ((float) $this->pTag($no, $tag) > 0) {
                return true;
            }
        }
        return false;
    }

    protected function pCpfCnpj($no)
    {
        $cnpj = $this->pTag($no, 'CNPJ');
        if (!empty($cnpj)) {
            return $this->pFormat($cnpj, "##.###.###/####-##");
        }
        $cpf = $this->pTag($no, 'CPF');
        if (!empty($cpf)) {
            return $this->pFormat($cpf, "###.###.###-##");
        }
        return '';
    }

    /**
     * array(número formatado, série) da NF-e
     */
    protected function pNumeroSerie()
    {
        $numNF = str_pad($this->pSimpleGetValue($this->ide, 'nNF'), 9, "0", STR_PAD_LEFT);
        $serie = str_pad($this->pSimpleGetValue($this->ide, 'serie'), 3, "0", STR_PAD_LEFT);
        return array($this->pFormat($numNF, "###.###.###"), $serie);
    }

    //###########################################################
    // CANHOTO
    //###########################################################

    protected function pCanhotoTexto()
    {
        $tpNF = $this->pSimpleGetValue($this->ide, 'tpNF');
        $nome = ($tpNF == '0')
            ? $this->pSimpleGetValue($this->dest, 'xNome')
            : $this->pSimpleGetValue($this->emit, 'xNome');
        return 'RECEBEMOS DE ' . $nome
            . ' OS PRODUTOS/SERVIÇOS CONSTANTES DA NOTA FISCAL ELETRÔNICA INDICADA AO LADO';
    }

    /**
     * Quebra o texto em no máximo $maxLinhas linhas, reduzindo a fonte se preciso;
     * devolve array(linhas, tamanho da fonte)
     */
    protected function pQuebraLimitada($texto, $w, $tam, $estilo, $maxLinhas)
    {
        do {
            $linhas = $this->pQuebra($texto, $w, $tam, $estilo);
            if (count($linhas) <= $maxLinhas || $tam <= 4) {
                break;
            }
            $tam -= 0.25;
        } while (true);
        return array(array_slice($linhas, 0, $maxLinhas), $tam);
    }

    /**
     * Canhoto no topo da folha (retrato)
     */
    protected function pCanhotoRetrato($x, $y)
    {
        $w = $this->wPrint;
        $wNF = round($w * 0.19, 1);
        $wE = $w - $wNF;
        $h1 = 9;
        $h2 = 8;
        $this->pdf->rect($x, $y, $wE, $h1);
        list($linhas, $tam) = $this->pQuebraLimitada($this->pCanhotoTexto(), $wE, 6.5, 'B', 2);
        $lh = $tam * 0.42;
        $yb = $y + (($h1 - (count($linhas) * $lh)) / 2) + $lh - 0.4;
        foreach ($linhas as $l) {
            $this->pEscreve($x, $yb, $wE, $l, $tam, 'B');
            $yb += $lh;
        }
        $wData = round($wE * 0.27, 1);
        $this->pCampo($x, $y + $h1, $wData, $h2, 'DATA DE RECEBIMENTO', '');
        $this->pCampo($x + $wData, $y + $h1, $wE - $wData, $h2, 'IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR', '');
        list($num, $serie) = $this->pNumeroSerie();
        $xn = $x + $wE;
        $this->pdf->rect($xn, $y, $wNF, $h1 + $h2);
        $this->pEscreve($xn, $y + 6, $wNF, 'NF-e', 11, 'B', 'C');
        $this->pEscreve($xn, $y + 10.5, $wNF, $this->pEnc('Nº ' . $num), 8, '', 'C');
        $this->pEscreve($xn, $y + 14, $wNF, $this->pEnc('SÉRIE ' . $serie), 8, '', 'C');
        $y += $h1 + $h2 + 1.5;
        $this->pdf->dashedHLine($x, $y, $w, 0.1, 80);
        $this->pdf->setLineWidth(0.2);
        return $y + 1.5;
    }

    /**
     * Canhoto na faixa direita da folha (paisagem), texto girado 90°
     */
    protected function pCanhotoPaisagem($x, $y, $wc, $h)
    {
        $c = $wc / 2;
        $l1 = round($h * 0.70, 1);
        $l2 = $h - $l1;
        $lIdent = round($l1 * 0.58, 1);
        $lData = $l1 - $lIdent;
        $texto = $this->pCanhotoTexto();
        $this->pGirado($x, $y, $l1, function ($ox, $oy) use ($l1, $c, $texto) {
            $this->pdf->rect($ox, $oy, $l1, $c);
            list($linhas, $tam) = $this->pQuebraLimitada($texto, $l1, 7, 'B', 2);
            $lh = $tam * 0.42;
            $yb = $oy + (($c - (count($linhas) * $lh)) / 2) + $lh - 0.4;
            foreach ($linhas as $l) {
                $this->pEscreve($ox, $yb, $l1, $l, $tam, 'B');
                $yb += $lh;
            }
        });
        $this->pGirado($x + $c, $y, $lIdent, function ($ox, $oy) use ($lIdent, $c) {
            $this->pCampo($ox, $oy, $lIdent, $c, 'IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR', '');
        });
        $this->pGirado($x + $c, $y + $lIdent, $lData, function ($ox, $oy) use ($lData, $c) {
            $this->pCampo($ox, $oy, $lData, $c, 'DATA DE RECEBIMENTO', '');
        });
        list($num, $serie) = $this->pNumeroSerie();
        $this->pGirado($x, $y + $l1, $l2, function ($ox, $oy) use ($l2, $wc, $num, $serie) {
            $this->pdf->rect($ox, $oy, $l2, $wc);
            $this->pEscreve($ox, $oy + 7, $l2, 'NF-e', 12, 'B', 'C');
            $this->pEscreve($ox, $oy + 12, $l2, $this->pEnc('Nº ' . $num), 8, '', 'C');
            $this->pEscreve($ox, $oy + 16, $l2, $this->pEnc('SÉRIE ' . $serie), 8, '', 'C');
        });
        $this->pdf->dashedVLine($x - 1, $y, 0.1, $y + $h, 70);
        $this->pdf->setLineWidth(0.2);
    }

    //###########################################################
    // CABEÇALHO
    //###########################################################

    /**
     * Cabeçalho (identificação do emitente, DANFE, chave, natureza, protocolo, inscrições, CRT)
     *
     * @return number Posição vertical final
     */
    protected function pCabecalhoDANFE($x, $y, $pag, $totPag, $maxW)
    {
        $oldX = $x;
        $w1 = round($maxW * 0.32, 1);
        $w2 = round($maxW * 0.22, 1);
        $w3 = $maxW - $w1 - $w2;
        $h = $this->hTopoCab;
        $k = $h / 32;
        $kf = $this->kFonte;
        //####################################################################################
        //identificação do emitente
        $this->pdf->rect($x, $y, $w1, $h);
        $this->pEmitenteDANFE($x, $y, $w1, $h);
        //####################################################################################
        //coluna central DANFE
        $xd = $x + $w1;
        $this->pdf->rect($xd, $y, $w2, $h);
        if (!$this->pNotaCancelada()) {
            // A PRINCIPIO NÃO PRECISAVA, POIS A NFE ESTÁ AUTORIZADA,
            // SÓ SE RETIRA O DANFE PARA NOTAS NÃO AUTORIZADAS
            $this->pEscreve($xd, $y + (6 * $k), $w2, 'DANFE', 13 * $kf, 'B', 'C');
            $yb = $y + (9.2 * $k);
            foreach ($this->pQuebra('DOCUMENTO AUXILIAR DA NOTA FISCAL ELETRÔNICA', $w2, 5.5 * $kf) as $l) {
                $this->pEscreve($xd, $yb, $w2, $l, 5.5 * $kf, '', 'C', true);
                $yb += 2.4 * $kf;
            }
        }
        $tpNF = $this->pSimpleGetValue($this->ide, 'tpNF');
        $lb = 7 * $k;
        $xb = $xd + (($w2 - $lb) / 2);
        $this->pdf->rect($xb, $y + (13.5 * $k), $lb, $lb);
        $this->pEscreve($xb, $y + (18.6 * $k), $lb, $tpNF, 11 * $kf, 'B', 'C');
        $this->pEscreve($xd, $y + (24 * $k), $w2, $this->pEnc('0 - ENTRADA   1 - SAÍDA'), 6.5 * $kf, '', 'C');
        list($num, $serie) = $this->pNumeroSerie();
        $this->pEscreve($xd, $y + (27.3 * $k), $w2, $this->pEnc('Nº ' . $num), 7.5 * $kf, '', 'C');
        $this->pEscreve($xd, $y + (30.5 * $k), $w2, $this->pEnc('SÉRIE ' . $serie . '   FOLHA ' . $pag . '/' . $totPag), 7.5 * $kf, '', 'C');
        //####################################################################################
        //coluna código de barras / chave de acesso
        $xc = $xd + $w2;
        $this->pdf->rect($xc, $y, $w3, $h);
        $chave_acesso = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        $bW = min(80, $w3 - 6);
        $bH = 9 * $k;
        $this->pdf->setFillColor(0, 0, 0);
        $this->pdf->code128($xc + (($w3 - $bW) / 2), $y + $k, $chave_acesso, $bW, $bH);
        $this->pdf->setFillColor(255, 255, 255);
        $this->pdf->line($xc, $y + (11 * $k), $xc + $w3, $y + (11 * $k));
        $this->pdf->line($xc, $y + (22 * $k), $xc + $w3, $y + (22 * $k));
        $this->pEscreve($xc, $y + (11 * $k) + ($this->tamRotulo * 0.3528) + 0.4, $w3, 'CHAVE DE ACESSO', $this->tamRotulo, '', 'L', true);
        $this->pEscreve($xc, $y + (19.5 * $k), $w3, $this->pFormat($chave_acesso, $this->formatoChave), 8.5 * $kf, 'B', 'C');
        $chaveContingencia = "";
        if ($this->pNotaDPEC()) {
            $cabecalhoProtoAutorizacao = 'NÚMERO DE REGISTRO DPEC';
        } else {
            $cabecalhoProtoAutorizacao = 'PROTOCOLO DE AUTORIZAÇÃO DE USO';
        }
        if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
            $cabecalhoProtoAutorizacao = "DADOS DA NF-E";
            $chaveContingencia = $this->pGeraChaveAdicionalDeContingencia();
            $this->pdf->setFillColor(0, 0, 0);
            $this->pdf->code128($xc + (($w3 - ($bW * 0.9)) / 2), $y + (23.5 * $k), $chaveContingencia, $bW * 0.9, 7 * $k);
            $this->pdf->setFillColor(255, 255, 255);
        } else {
            $consulta = 'Consulte a autenticidade no portal nacional da NF-e www.nfe.fazenda.gov.br/portal '
                . 'ou no site da SEFAZ autorizadora';
            $yb = $y + (26 * $k);
            foreach ($this->pQuebra($consulta, $w3, 6 * $kf) as $l) {
                $this->pEscreve($xc, $yb, $w3, $l, 6 * $kf, '', 'L');
                $yb += 2.7 * $kf;
            }
        }
        //####################################################################################
        //NATUREZA DA OPERAÇÃO | PROTOCOLO
        $y += $h;
        $this->pCampo($x, $y, $w1 + $w2, $this->hCampo, 'NATUREZA DA OPERAÇÃO', $this->pSimpleGetValue($this->ide, 'natOp'));
        // NOTA : DANFE sem protocolo deve existir somente no caso de contingência
        if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
            $texto = $this->pFormat($chaveContingencia, "#### #### #### #### #### #### #### #### ####");
        } elseif ($this->pNotaDPEC()) {
            $texto = $this->numero_registro_dpec;
        } else {
            $texto = '';
            if (isset($this->nfeProc)) {
                $texto = $this->pSimpleGetValue($this->nfeProc, 'nProt');
                $dhRecbto = $this->pSimpleGetValue($this->nfeProc, 'dhRecbto');
                if ($texto != '' && $dhRecbto != '') {
                    $texto .= ' - ' . date('d/m/Y H:i:s', $this->pConvertTime($dhRecbto));
                }
            }
        }
        $this->pCampo($xc, $y, $w3, $this->hCampo, $cabecalhoProtoAutorizacao, $texto, 'R');
        //####################################################################################
        //INSCRIÇÃO ESTADUAL | IE DO SUBSTITUTO | CNPJ
        $y += $this->hCampo;
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('INSCRIÇÃO ESTADUAL', $this->pSimpleGetValue($this->emit, 'IE'), $w1 / $maxW),
            array('INSCRIÇÃO ESTADUAL DO SUBSTITUTO TRIBUTÁRIO', $this->pSimpleGetValue($this->emit, 'IEST'), 0.36),
            array('CNPJ / CPF', $this->pCpfCnpj($this->emit), 0, 'R'),
        ));
        //####################################################################################
        //CÓDIGO DO REGIME TRIBUTÁRIO | TIPO DE REGIME DE APURAÇÃO DO IBS E DA CBS
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('CÓDIGO DO REGIME TRIBUTÁRIO', $this->pTextoCRT(), 0.49),
            // reservado: a NT 2026.010 ainda não definiu a tag (não imprimir conteúdo)
            array('TIPO DE REGIME DE APURAÇÃO DO IBS E DA CBS', '', 0),
        ));
        return $y;
    } //fim cabecalhoDANFE

    /**
     * Texto do CRT do emitente (C21)
     */
    protected function pTextoCRT()
    {
        $crt = $this->pSimpleGetValue($this->emit, 'CRT');
        $textos = array(
            '1' => '1 - SIMPLES NACIONAL',
            '2' => '2 - SIMPLES NACIONAL - EXCESSO DE SUBLIMITE',
            '3' => '3 - REGIME NORMAL',
            '4' => '4 - SIMPLES NACIONAL - MEI',
        );
        return isset($textos[$crt]) ? $textos[$crt] : $crt;
    }

    /**
     * Logo e dados do emitente dentro da caixa
     */
    protected function pEmitenteDANFE($x, $y, $w, $h)
    {
        $x1 = $x;
        $tw = $w;
        $yTexto = null;
        if (is_file($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            //largura da imagem em mm
            $logoWmm = ($logoInfo[0] / 72) * 25.4;
            //altura da imagem em mm
            $logoHmm = ($logoInfo[1] / 72) * 25.4;
            if ($this->logoAlign == 'L') {
                $nImgW = round($w / 3, 0);
                $nImgH = round($logoHmm * ($nImgW / $logoWmm), 0);
                $xImg = $x + 1;
                $yImg = round(($h - $nImgH) / 2, 0) + $y;
                $x1 = round($xImg + $nImgW + 1, 0);
                $tw = round(2 * $w / 3, 0);
            } elseif ($this->logoAlign == 'R') {
                $nImgW = round($w / 3, 0);
                $nImgH = round($logoHmm * ($nImgW / $logoWmm), 0);
                $xImg = round($x + ($w - (1 + $nImgW)), 0);
                $yImg = round(($h - $nImgH) / 2, 0) + $y;
                $tw = round(2 * $w / 3, 0);
            } elseif ($this->logoAlign == 'F') {
                $nImgH = round($h - 5, 0);
                $nImgW = round($logoWmm * ($nImgH / $logoHmm), 0);
                $xImg = round(($w - $nImgW) / 2 + $x, 0);
                $yImg = $y + 3;
            } else {
                //C - centro
                $nImgH = round($h / 3, 0);
                $nImgW = round($logoWmm * ($nImgH / $logoHmm), 0);
                $xImg = round(($w - $nImgW) / 2 + $x, 0);
                $yImg = $y + 2;
                $yTexto = $yImg + $nImgH + 1;
            }
            $this->pdf->image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH);
        }
        if ($this->logoAlign === 'F' && is_file($this->logomarca)) {
            return;
        }
        $lgr = $this->pSimpleGetValue($this->enderEmit, "xLgr");
        $nro = $this->pSimpleGetValue($this->enderEmit, "nro");
        $cpl = $this->pSimpleGetValue($this->enderEmit, "xCpl", " - ");
        $bairro = $this->pSimpleGetValue($this->enderEmit, "xBairro");
        $cep = $this->pFormat($this->pSimpleGetValue($this->enderEmit, "CEP"), "#####-###");
        $mun = $this->pSimpleGetValue($this->enderEmit, "xMun");
        $uf = $this->pSimpleGetValue($this->enderEmit, "UF");
        $fone = $this->pSimpleGetValue($this->enderEmit, "fone");
        $kf = $this->kFonte;
        //na paisagem o bloco é baixo: nome em 1 linha para caber cidade/CEP/fone
        $nome = $this->pQuebraLimitada($this->pSimpleGetValue($this->emit, 'xNome'), $tw, 10 * $kf, 'B', $this->orientacao == 'P' ? 2 : 1);
        $endereco = array();
        foreach (array(
            $lgr . ', ' . $nro . $cpl . ' - ' . $bairro,
            $mun . '/' . $uf . ' - CEP ' . $cep,
            $fone != '' ? 'Fone: ' . $fone : '',
        ) as $parte) {
            foreach ($this->pQuebra($parte, $tw, 7 * $kf) as $l) {
                $endereco[] = $l;
            }
        }
        $lhNome = $nome[1] * 0.42;
        $altura = (count($nome[0]) * $lhNome) + (count($endereco) * 3 * $kf) + 1;
        if ($yTexto === null) {
            $yTexto = $y + max(1, ($h - $altura) / 2);
        }
        $yb = $yTexto + $lhNome;
        foreach ($nome[0] as $l) {
            $this->pEscreve($x1, $yb, $tw, $l, $nome[1], 'B', 'C');
            $yb += $lhNome;
        }
        $yb += 0.6;
        foreach ($endereco as $l) {
            if ($yb > $y + $h - 0.5) {
                break;
            }
            $this->pEscreve($x1, $yb, $tw, $l, 7 * $kf, '', 'C');
            $yb += 3 * $kf;
        }
    }

    /**
     * Marcas d'água da folha atual. Chamada no início da folha ($inicio = true) e no fim (false).
     * Com PdfTransparencia são desenhadas no fim, por cima de tudo, com mistura "Multiply" (no
     * fundo branco ficam iguais às de antes e as faixas cinza dos títulos não as escondem).
     * Com um Pdf comum passado de fora (sem transparência) ficam no início, por baixo do conteúdo.
     */
    protected function pMarcasDaguaFolha($maxW, $inicio)
    {
        $mistura = method_exists($this->pdf, 'setMistura');
        if ($inicio === $mistura) {
            return;
        }
        if ($mistura) {
            $this->pdf->setMistura('Multiply');
        }
        $this->pMarcasDagua($maxW);
        if ($mistura) {
            $this->pdf->setMistura('Normal');
        }
    }

    /**
     * Indicação de NF cancelada, denegada, DPEC, contingência, homologação e falta de protocolo
     */
    protected function pMarcasDagua($maxW)
    {
        $tpAmb = $this->pSimpleGetValue($this->ide, 'tpAmb');
        if ($this->pNotaCancelada()) {
            //101 Cancelamento
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = $maxW - (2 * $x);
            $this->pdf->setTextColor(90, 90, 90);
            $texto = "NFe CANCELADA";
            $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->setTextColor(0, 0, 0);
        }
        if ($this->pNotaDPEC() || $this->tpEmis == 4) {
            //DPEC
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = $maxW - (2 * $x);
            $this->pdf->setTextColor(200, 200, 200);
            $texto = "DANFE impresso em contingência -\n" .
                     "DPEC regularmente recebido pela Receita\n" .
                     "Federal do Brasil";
            $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->setTextColor(0, 0, 0);
        }
        if ($this->pNotaDenegada()) {
            //110 301 302 Denegada
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = $maxW - (2 * $x);
            $this->pdf->setTextColor(90, 90, 90);
            $texto = "NFe USO DENEGADO";
            $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $y += $h;
            $h = 5;
            $xMotivo = isset($this->infProt) ? $this->pSimpleGetValue($this->infProt, 'xMotivo') : '';
            $texto = "SEM VALOR FISCAL\n" . $xMotivo;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->setTextColor(0, 0, 0);
        }
        $x = 10;
        if ($this->orientacao == 'P') {
            $y = round($this->hPrint * 2 / 3, 0);
        } else {
            $y = round($this->hPrint / 2, 0);
        }
        $h = 5;
        $w = $maxW - (2 * $x);
        if ($tpAmb != 1) {
            //indicar sem valor
            $this->pdf->setTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array('font' => $this->fontePadrao, 'size' => 30, 'style' => 'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pTextBox($x, $y + 14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->setTextColor(0, 0, 0);
        } else {
            $this->pdf->setTextColor(90, 90, 90);
            //indicar FALTA DO PROTOCOLO se NFe não for em contingência
            if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
                //Contingência
                $texto = "DANFE Emitido em Contingência";
                $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
                $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $aFont = array('font' => $this->fontePadrao, 'size' => 30, 'style' => 'B');
                $texto = "devido à problemas técnicos";
                $this->pTextBox($x, $y + 12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            } elseif (!isset($this->nfeProc)) {
                if (!$this->pNotaDPEC()) {
                    $texto = "SEM VALOR FISCAL";
                    $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
                    $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                }
                $aFont = array('font' => $this->fontePadrao, 'size' => 30, 'style' => 'B');
                $texto = "FALTA PROTOCOLO DE APROVAÇÃO DA SEFAZ";
                if (!$this->pNotaDPEC()) {
                    $this->pTextBox($x, $y + 12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                } else {
                    $this->pTextBox($x, $y + 25, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                }
            }
            $this->pdf->setTextColor(0, 0, 0);
        }
    }

    //###########################################################
    // DESTINATÁRIO / FATURA / TOTAIS / ISSQN / TRANSPORTE
    //###########################################################

    /**
     * DESTINATÁRIO / REMETENTE
     *
     * @return number Posição vertical final
     */
    protected function pDestinatarioDANFE($x, $y, $maxW)
    {
        $y = $this->pTitulo($x, $y, $maxW, 'DESTINATÁRIO / REMETENTE');
        //datas
        $dEmi = $this->pSimpleGetValue($this->ide, 'dEmi');
        if ($dEmi == '') {
            $aDemi = explode('T', $this->pSimpleGetValue($this->ide, 'dhEmi'));
            $dEmi = $aDemi[0];
        }
        $dSaiEnt = $this->pSimpleGetValue($this->ide, 'dSaiEnt');
        if ($dSaiEnt == '') {
            $aDsaient = explode('T', $this->pSimpleGetValue($this->ide, 'dhSaiEnt'));
            $dSaiEnt = $aDsaient[0];
        }
        $hSaiEnt = $this->pSimpleGetValue($this->ide, 'hSaiEnt');
        if ($hSaiEnt == '') {
            $tsDhSaiEnt = $this->pConvertTime($this->pSimpleGetValue($this->ide, 'dhSaiEnt'));
            if ($tsDhSaiEnt != '') {
                $hSaiEnt = date('H:i:s', $tsDhSaiEnt);
            }
        }
        $endereco = $this->pSimpleGetValue($this->dest, 'xLgr');
        $nro = $this->pSimpleGetValue($this->dest, 'nro');
        if ($nro != '') {
            $endereco .= ', ' . $nro;
        }
        $endereco .= $this->pSimpleGetValue($this->dest, 'xCpl', ' - ');
        $mun = $this->pSimpleGetValue($this->dest, 'xMun');
        if (strtoupper(trim($mun)) == "EXTERIOR") {
            $mun .= $this->pSimpleGetValue($this->dest, 'xPais', ' - ');
        }
        $cep = $this->pSimpleGetValue($this->dest, 'CEP');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('NOME / RAZÃO SOCIAL', $this->pSimpleGetValue($this->dest, 'xNome'), 0.565),
            array('CNPJ / CPF', $this->pCpfCnpj($this->dest), 0.255),
            array('DATA DE EMISSÃO', $this->pYmd2dmy($dEmi), 0),
        ));
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('ENDEREÇO', $endereco, 0.465),
            array('BAIRRO / DISTRITO', $this->pSimpleGetValue($this->dest, 'xBairro'), 0.24),
            array('CEP', $cep != '' ? $this->pFormat($cep, "#####-###") : '', 0.115),
            array('DATA DE ENTRADA / SAÍDA', $this->pYmd2dmy($dSaiEnt), 0),
        ));
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('MUNICÍPIO', $mun, 0.30),
            array('FONE / FAX', $this->pSimpleGetValue($this->dest, 'fone'), 0.18),
            array('UF', $this->pSimpleGetValue($this->dest, 'UF'), 0.085),
            array('INSCRIÇÃO ESTADUAL', $this->pSimpleGetValue($this->dest, 'IE'), 0.255),
            array('HORA DE SAÍDA', $hSaiEnt, 0),
        ));
        return $y;
    } //fim da função destinatarioDANFE

     /**
     * pGetTextoFatura
     * Gera a String do Texto da Fatura
      *
     * @name   getTextoFatura
     * @return a String com o texto ou "";
     */
    protected function pGetTextoFatura()
    {
        if (isset($this->cobr)) {
            $fat = $this->cobr->getElementsByTagName("fat")->item(0);
            if (isset($fat)) {
                $textoIndPag = "";
                $indPag = $this->pSimpleGetValue($this->ide, "indPag");
                if ($indPag === '0') {
                    $textoIndPag = "Pagamento à Vista - ";
                } elseif ($indPag === '1') {
                    $textoIndPag = "Pagamento à Prazo - ";
                }
                $nFat = $this->pSimpleGetValue($fat, "nFat", "Fatura: ");
                $vOrig = $this->pSimpleGetValue($fat, "vOrig", " Valor Original: ");
                $vDesc = $this->pSimpleGetValue($fat, "vDesc", " Desconto: ");
                $vLiq = $this->pSimpleGetValue($fat, "vLiq", " Valor Líquido: ");
                return $textoIndPag . $nFat . $vOrig . $vDesc . $vLiq;
            }
        }
        return "";
    } //fim getTextoFatura

    /**
     * FATURA / DUPLICATAS (só quando há duplicatas ou o texto da fatura deve ser exibido)
     * Mostra até 21 duplicatas, 3 por linha.
     *
     * @return number Posição vertical final (já com o espaçamento)
     */
    protected function pFaturaDANFE($x, $y, $maxW)
    {
        $textoFatura = $this->exibirTextoFatura ? $this->pGetTextoFatura() : '';
        $dups = array();
        foreach ($this->dup as $k => $d) {
            if ($k >= 21) {
                // limite de impressão: as 21 primeiras parcelas
                break;
            }
            $nDup = $this->pSimpleGetValue($d, 'nDup');
            $dVenc = $this->pSimpleGetValue($d, 'dVenc');
            $vDup = $this->pSimpleGetValue($d, 'vDup');
            $dups[] = array(
                ($nDup != '' && $nDup != '0') ? $nDup : (string) ($k + 1),
                $dVenc != '' ? $this->pYmd2dmy($dVenc) : '',
                is_numeric($vDup) ? $this->pNum($vDup) : '',
            );
        }
        if (count($dups) == 0 && $textoFatura === '') {
            return $y;
        }
        $y = $this->pTitulo($x, $y, $maxW, 'FATURA / DUPLICATAS');
        if ($textoFatura !== '') {
            $this->pdf->rect($x, $y, $maxW, 6);
            $this->pEscreve($x, $y + 4, $maxW, $this->pEnc($textoFatura), 7.5);
            $y += 6;
        }
        $porLinha = 3;
        $wg = $maxW / $porLinha;
        foreach (array_chunk($dups, $porLinha) as $linha) {
            $xi = $x;
            foreach ($linha as $d) {
                $this->pLinhaCampos($xi, $y, $wg, array(
                    array('FATURA/DUPLICATA', $d[0], 0.40),
                    array('VENCIMENTO', $d[1], 0.32),
                    array('VALOR', $d[2], 0),
                ));
                $xi += $wg;
            }
            $y += $this->hCampo;
        }
        return $y + $this->gap;
    } //fim da função faturaDANFE

    /**
     * TOTAL DOS PRODUTOS E TOTAL DA NOTA
     */
    protected function pTotaisProdutosDANFE($x, $y, $maxW)
    {
        $t = $this->ICMSTot;
        $y = $this->pTitulo($x, $y, $maxW, 'TOTAL DOS PRODUTOS E TOTAL DA NOTA');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('VALOR TOTAL DOS PRODUTOS', $this->pTotalValor($t, 'vProd'), 0.2),
            array('VALOR DO FRETE', $this->pTotalValor($t, 'vFrete'), 0.2),
            array('VALOR DO SEGURO', $this->pTotalValor($t, 'vSeg'), 0.2),
            array('DESCONTO', $this->pTotalValor($t, 'vDesc'), 0.2),
            array($this->orientacao == 'P' ? 'OUTRAS DESPESAS' : 'OUTRAS DESPESAS ACESSÓRIAS', $this->pTotalValor($t, 'vOutro'), 0),
        ));
        return $this->pLinhaCampos($x, $y, $maxW, array(
            array('VALOR TOTAL DA NOTA', $this->pTotalValor($t, 'vNF'), 0),
        ));
    }

    /**
     * TOTAL DO ICMS / IPI (linhas do FCP/DIFAL e do ICMS monofásico só com valor no XML)
     */
    protected function pTotaisIcmsDANFE($x, $y, $maxW)
    {
        $t = $this->ICMSTot;
        $y = $this->pTitulo($x, $y, $maxW, 'TOTAL DO ICMS / IPI');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('BASE DE CÁLCULO DO ICMS', $this->pTotalValor($t, 'vBC'), 0.2),
            array('VALOR DO ICMS', $this->pTotalValor($t, 'vICMS'), 0.2),
            array('BASE DE CÁLCULO DO ICMS ST', $this->pTotalValor($t, 'vBCST'), 0.2),
            array('VALOR DO ICMS ST', $this->pTotalValor($t, 'vST'), 0.2),
            array('VALOR DO IPI', $this->pTotalValor($t, 'vIPI'), 0),
        ));
        if ($this->pTemValorPositivo($t, array('vFCP', 'vFCPST', 'vICMSUFDest', 'vFCPUFDest'))) {
            $y = $this->pLinhaCampos($x, $y, $maxW, array(
                array('VALOR DO FCP', $this->pTotalValor($t, 'vFCP'), 0.25),
                array('VALOR DO FCP RETIDO POR ST', $this->pTotalValor($t, 'vFCPST'), 0.25),
                array('VALOR DO DIFAL NA UF DE DESTINO', $this->pTotalValor($t, 'vICMSUFDest'), 0.25),
                array('VALOR DO FCP NA UF DE DESTINO', $this->pTotalValor($t, 'vFCPUFDest'), 0),
            ));
        }
        if ($this->pTemValorPositivo($t, array('qBCMono', 'vICMSMono', 'qBCMonoReten', 'vICMSMonoReten'))) {
            $y = $this->pLinhaCampos($x, $y, $maxW, array(
                array('BC DO ICMS MONOFÁSICO', $this->pTotalValor($t, 'qBCMono'), 0.25),
                array('VALOR DO ICMS MONOFÁSICO', $this->pTotalValor($t, 'vICMSMono'), 0.25),
                array('BC DO ICMS MONOFÁSICO POR RETENÇÃO', $this->pTotalValor($t, 'qBCMonoReten'), 0.25),
                array('VALOR DO ICMS MONOFÁSICO POR RETENÇÃO', $this->pTotalValor($t, 'vICMSMonoReten'), 0),
            ));
        }
        return $y;
    }

    /**
     * TOTAL DO IBS / CBS / IS (sempre presente; sem os grupos no XML os valores ficam em branco)
     */
    protected function pTotaisIbsCbsDANFE($x, $y, $maxW)
    {
        $tot = $this->IBSCBSTot;
        $gIBS = $this->pNo($tot, 'gIBS');
        $gMono = $this->pNo($tot, 'gMono');
        $y = $this->pTitulo($x, $y, $maxW, 'TOTAL DO IBS / CBS / IS');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('VALOR DA CBS', $this->pTotalValor($this->pNo($tot, 'gCBS'), 'vCBS'), 0.25),
            array('VALOR DO IBS UF', $this->pTotalValor($this->pNo($gIBS, 'gIBSUF'), 'vIBSUF'), 0.25),
            array('VALOR DO IBS MUNICÍPIO', $this->pTotalValor($this->pNo($gIBS, 'gIBSMun'), 'vIBSMun'), 0.25),
            array('VALOR DO IMPOSTO SELETIVO', $this->pTotalValor($this->ISTot, 'vIS'), 0),
        ));
        if ($gMono) {
            $y = $this->pLinhaCampos($x, $y, $maxW, array(
                array('VALOR DO IBS MONOFÁSICO', $this->pTotalValor($gMono, 'vIBSMono'), 0.25),
                array('VALOR DA CBS MONOFÁSICA', $this->pTotalValor($gMono, 'vCBSMono'), 0.25),
                array('VALOR DO IBS MONOFÁSICO POR RETENÇÃO', $this->pTotalValor($gMono, 'vIBSMonoReten'), 0.25),
                array('VALOR DA CBS MONOFÁSICA POR RETENÇÃO', $this->pTotalValor($gMono, 'vCBSMonoReten'), 0),
            ));
        }
        return $y;
    }

    /**
     * CÁLCULO DO ISSQN (só quando há serviços)
     *
     * @return number Posição vertical final (já com o espaçamento)
     */
    protected function pIssqnDANFE($x, $y, $maxW)
    {
        if (!isset($this->ISSQNtot) || !((float) $this->pTag($this->ISSQNtot, 'vServ') > 0)) {
            return $y;
        }
        $t = $this->ISSQNtot;
        $y = $this->pTitulo($x, $y, $maxW, 'CÁLCULO DO ISSQN');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('INSCRIÇÃO MUNICIPAL', $this->pSimpleGetValue($this->emit, 'IM'), 0.25),
            array('VALOR TOTAL DOS SERVIÇOS', $this->pTotalValor($t, 'vServ'), 0.25),
            array('BASE DE CÁLCULO DO ISSQN', $this->pTotalValor($t, 'vBC'), 0.25),
            array('VALOR DO ISSQN', $this->pTotalValor($t, 'vISS'), 0),
        ));
        return $y + $this->gap;
    }

    /**
     * Texto do FRETE POR CONTA (modFrete)
     */
    protected function pTextoModFrete($modFrete)
    {
        $textos = array(
            '0' => '0 - Remetente',
            '1' => '1 - Destinatário',
            '2' => '2 - Terceiros',
            '3' => '3 - Próprio Remetente',
            '4' => '4 - Próprio Destinatário',
            '9' => '9 - Sem Frete',
        );
        return isset($textos[$modFrete]) ? $textos[$modFrete] : $modFrete;
    }

    /**
     * TRANSPORTADOR / VOLUMES TRANSPORTADOS
     * Some quando não há transportadora, veículo, reboque nem volume e o frete é 9 (sem frete).
     *
     * @return number Posição vertical final (já com o espaçamento)
     */
    protected function pTransporteDANFE($x, $y, $maxW)
    {
        $modFrete = $this->pSimpleGetValue($this->transp, 'modFrete');
        $volumes = $this->transp ? $this->transp->getElementsByTagName('vol') : null;
        $temVolume = $volumes && $volumes->length > 0;
        if (!isset($this->transporta) && !isset($this->veicTransp) && !isset($this->reboque)
            && !$temVolume && $modFrete == '9') {
            return $y;
        }
        $veiculo = isset($this->veicTransp) ? $this->veicTransp : (isset($this->reboque) ? $this->reboque : null);
        $y = $this->pTitulo($x, $y, $maxW, 'TRANSPORTADOR / VOLUMES TRANSPORTADOS');
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('RAZÃO SOCIAL', $this->pSimpleGetValue($this->transporta, 'xNome'), 0.30),
            array('FRETE POR CONTA', $this->pTextoModFrete($modFrete), 0.14),
            array('CÓDIGO ANTT', $this->pSimpleGetValue($veiculo, 'RNTC'), 0.14),
            array('PLACA DO VEÍCULO', $this->pSimpleGetValue($veiculo, 'placa'), 0.14),
            array('UF', $this->pSimpleGetValue($veiculo, 'UF'), 0.06),
            array('CNPJ / CPF', $this->pCpfCnpj($this->transporta), 0),
        ));
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('ENDEREÇO', $this->pSimpleGetValue($this->transporta, 'xEnder'), 0.40),
            array('MUNICÍPIO', $this->pSimpleGetValue($this->transporta, 'xMun'), 0.26),
            array('UF', $this->pSimpleGetValue($this->transporta, 'UF'), 0.06),
            array('INSCRIÇÃO ESTADUAL', $this->pSimpleGetValue($this->transporta, 'IE'), 0),
        ));
        //vários volumes: soma quantidade/pesos; espécie/marca/numeração diferentes viram VARIAS/VARIOS
        $quantidade = 0;
        $pesoBruto = 0;
        $pesoLiquido = 0;
        $especie = null;
        $marca = null;
        $numero = null;
        if ($temVolume) {
            foreach ($volumes as $volume) {
                $quantidade += (float) $this->pTag($volume, 'qVol');
                $pesoBruto += (float) $this->pTag($volume, 'pesoB');
                $pesoLiquido += (float) $this->pTag($volume, 'pesoL');
                $esp = (string) $this->pTag($volume, 'esp');
                $especie = ($especie !== null && $esp != $especie) ? 'VARIAS' : $esp;
                $mar = (string) $this->pTag($volume, 'marca');
                $marca = ($marca !== null && $mar != $marca) ? 'VARIAS' : $mar;
                $num = (string) $this->pTag($volume, 'nVol');
                $numero = ($numero !== null && $num != $numero) ? 'VARIOS' : $num;
            }
        }
        $y = $this->pLinhaCampos($x, $y, $maxW, array(
            array('QUANTIDADE', $quantidade > 0 ? $this->pNum($quantidade, 0) : '', 0.14),
            array('ESPÉCIE', (string) $especie, 0.20),
            array('MARCA', (string) $marca, 0.16),
            array('NUMERAÇÃO', (string) $numero, 0.18),
            array('PESO BRUTO', $pesoBruto > 0 ? $this->pNum($pesoBruto, 3) . ' kg' : '', 0.16),
            array('PESO LÍQUIDO', $pesoLiquido > 0 ? $this->pNum($pesoLiquido, 3) . ' kg' : '', 0),
        ));
        return $y + $this->gap;
    } //fim transporteDANFE

    //###########################################################
    // DADOS DOS PRODUTOS / SERVIÇOS
    //###########################################################

    protected function pDescricaoProdutoHelper($origem, $campo, $formato)
    {
        $valor_original = $origem->getElementsByTagName($campo)->item(0);
        if (!isset($valor_original)) {
            return "";
        }
        $valor_original = $valor_original->nodeValue;
        $valor = ! empty($valor_original) ? number_format($valor_original, 2, ",", ".") : '';

        if ($valor != "") {
            return sprintf($formato, $valor);
        }
        return "";
    }

    /**
     * Informações complementares do item (infAdProd, medicamentos, ICMS ST/DIFAL, FCI),
     * impressas em cinza abaixo do xProd
     */
    protected function pComplementoProduto($itemProd)
    {
        $prod = $itemProd->getElementsByTagName('prod')->item(0);
        $ICMS = $itemProd->getElementsByTagName("ICMS")->item(0);
        $ICMSUFDest = $itemProd->getElementsByTagName("ICMSUFDest")->item(0);
        $impostos = '';
        if (!empty($ICMS)) {
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pRedBC", " pRedBC=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pMVAST", " IVA=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pICMSST", " pIcmsSt=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vBCST", " BcIcmsSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vICMSST", " vIcmsSt=%s");
        }
        if (!empty($ICMSUFDest)) {
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pFCPUFDest", " pFCPUFDest=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pICMSUFDest", " pICMSUFDest=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pICMSInterPart", " pICMSInterPart=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vFCPUFDest", " vFCPUFDest=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vICMSUFDest", " vICMSUFDest=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vICMSUFRemet", " vICMSUFRemet=%s");
        }
        $infAdProd = $this->pSimpleGetValue($itemProd, 'infAdProd');
        $infAdProd = $infAdProd != '' ? trim(substr($this->pAnfavea($infAdProd), 0, 500)) . ' ' : '';
        $medTxt = '';
        $med = $prod ? $prod->getElementsByTagName("med") : null;
        if ($med) {
            $i = 0;
            while ($i < $med->length) {
                $medTxt .= $this->pSimpleGetValue($med->item($i), 'nLote', ' Lote: ');
                $medTxt .= $this->pSimpleGetValue($med->item($i), 'qLote', ' Quant: ');
                $medTxt .= $this->pSimpleGetDate($med->item($i), 'dFab', ' Fab: ');
                $medTxt .= $this->pSimpleGetDate($med->item($i), 'dVal', ' Val: ');
                $medTxt .= $this->pSimpleGetValue($med->item($i), 'vPMC', ' PMC: ');
                $i++;
            }
            if ($medTxt != '') {
                $medTxt .= ' ';
            }
        }
        //NT2013.006 FCI
        $nFCI = $this->pSimpleGetValue($itemProd, 'nFCI', ' FCI:');
        return trim($infAdProd . ($this->descProdInfoComplemento ? $medTxt . $impostos . $nFCI : ''));
    }

    /**
     * Linhas da coluna DESCRIÇÃO: array(array(array(linha, cinza), ...), rodapé "[NCM] [cClassTrib]" ou null)
     */
    protected function pDescricaoItem($det, $wDesc)
    {
        $prod = $this->pNo($det, 'prod');
        $xProd = (string) $this->pTag($prod, 'xProd');
        $comp = $this->pComplementoProduto($det);
        if ($this->descProdQuebraLinha) {
            $xProd = str_replace(";", "\n", $xProd);
            $comp = str_replace(";", "\n", $comp);
        }
        $linhas = array();
        foreach ($this->pQuebra($xProd, $wDesc, $this->tamItem) as $l) {
            $linhas[] = array($l, false);
        }
        foreach ($this->pQuebra($comp, $wDesc, $this->tamItem) as $l) {
            $linhas[] = array($l, true);
        }
        $ncm = (string) $this->pTag($prod, 'NCM');
        $cClassTrib = (string) $this->pTag($this->pNo($det, 'IBSCBS'), 'cClassTrib');
        $rodape = trim(($ncm !== '' ? '[NCM ' . $ncm . '] ' : '') . ($cClassTrib !== '' ? '[cClassTrib ' . $cClassTrib . ']' : ''));
        return array($linhas, $rodape === '' ? null : $this->pEnc($rodape));
    }

    protected function pAliq($valor)
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return '';
        }
        return $this->pNum($valor, 2) . '%';
    }

    /**
     * Alíquota do IBS UF / IBS Mun / CBS: com gRed, a alíquota efetiva (pAliqEfet); senão a vigente
     */
    protected function pAliqIbsCbs($grupo, $tag)
    {
        $red = $this->pNo($grupo, 'gRed');
        if ($red && $this->pTag($red, 'pAliqEfet') !== null) {
            return $this->pAliq($this->pTag($red, 'pAliqEfet'));
        }
        return $this->pAliq($this->pTag($grupo, $tag));
    }

    /**
     * Tributos do item que existem no XML:
     * 'bases' => array(array(rótulo, valor), ...) e
     * 'pares' => array(array(esquerda|null, direita|null), ...), cada lado array(rótulo, alíquota, valor)
     */
    protected function pTributosItem($det)
    {
        $imp = $this->pNo($det, 'imposto');
        $icms = $this->pNo($imp, 'ICMS');
        $ipi = $this->pNo($this->pNo($imp, 'IPI'), 'IPITrib');
        $is = $this->pNo($imp, 'IS');
        $g = $this->pNo($this->pNo($imp, 'IBSCBS'), 'gIBSCBS');
        $gUF = $this->pNo($g, 'gIBSUF');
        $gMun = $this->pNo($g, 'gIBSMun');
        $gCBS = $this->pNo($g, 'gCBS');
        $bases = array();
        foreach (array(
            array('ICMS', $icms, 'vBC'),
            array('IBS / CBS', $g, 'vBC'),
            array('IS', $is, 'vBCIS'),
            array('IPI', $ipi, 'vBC'),
        ) as $b) {
            $v = $this->pTotalValor($b[1], $b[2]);
            if ($v !== '') {
                $bases[] = array($b[0], $v);
            }
        }
        $temIcms = $this->pTag($icms, 'pICMS') !== null || $this->pTag($icms, 'vICMS') !== null;
        $icmsLado = $temIcms
            ? array('ICMS', $this->pAliq($this->pTag($icms, 'pICMS')), $this->pTotalValor($icms, 'vICMS'))
            : null;
        $cbs = $gCBS ? array('CBS', $this->pAliqIbsCbs($gCBS, 'pCBS'), $this->pTotalValor($gCBS, 'vCBS')) : null;
        $uf = $gUF ? array('IBS UF', $this->pAliqIbsCbs($gUF, 'pIBSUF'), $this->pTotalValor($gUF, 'vIBSUF')) : null;
        $mun = $gMun ? array('IBS MUN', $this->pAliqIbsCbs($gMun, 'pIBSMun'), $this->pTotalValor($gMun, 'vIBSMun')) : null;
        $ipiLado = $ipi ? array('IPI', $this->pAliq($this->pTag($ipi, 'pIPI')), $this->pTotalValor($ipi, 'vIPI')) : null;
        $isLado = $is ? array('IS', $this->pAliq($this->pTag($is, 'pIS')), $this->pTotalValor($is, 'vIS')) : null;
        $pares = array();
        foreach (array(array($icmsLado, $cbs), array($uf, $ipiLado), array($mun, $isLado)) as $par) {
            if ($par[0] !== null || $par[1] !== null) {
                $pares[] = $par;
            }
        }
        return array('bases' => $bases, 'pares' => $pares);
    }

    /**
     * Larguras das colunas da grade de itens
     */
    protected function pColunasItens($w)
    {
        $fracoes = ($this->orientacao == 'P')
            ? array(0.212, 0.062, 0.075, 0.087, 0.087, 0.124, 0.152)
            : array(0.323, 0.066, 0.076, 0.076, 0.067, 0.105, 0.124);
        $cols = array();
        $soma = 0;
        foreach ($fracoes as $f) {
            $c = round($w * $f, 2);
            $cols[] = $c;
            $soma += $c;
        }
        $cols[] = $w - $soma;
        return $cols;
    }

    /**
     * Altura do item na grade (mesma conta na paginação e no desenho)
     */
    protected function pAlturaItem($det, $w)
    {
        $cols = $this->pColunasItens($w);
        list($linhas, $rodape) = $this->pDescricaoItem($det, $cols[0]);
        $trib = $this->pTributosItem($det);
        $n = max(2, count($linhas) + ($rodape !== null ? 1 : 0), count($trib['bases']), count($trib['pares']));
        return ($n * $this->lhItem) + 1.2;
    }

    /**
     * Desenha um lado (rótulo + valor) de uma coluna de pares
     */
    protected function pLadoPar($x, $yb, $w, $lado, $indice)
    {
        if ($lado !== null && $lado[$indice] !== '') {
            $this->pLider($x, $yb, $w, $lado[0], $lado[$indice], $this->tamItem);
        } elseif ($lado !== null) {
            $this->pEscreve($x, $yb, $w, $this->pEnc($lado[0]), $this->tamItem, '', 'L', true);
        }
    }

    /**
     * Coluna de pares (ALÍQUOTAS ou VALOR DOS TRIBUTOS): "ICMS...18,00% / CBS...0,90%"
     */
    protected function pColunaPares($x, $yb, $w, $pares, $indice)
    {
        $wSep = 2.2;
        $wLado = ($w - $wSep) / 2;
        foreach ($pares as $par) {
            $this->pLadoPar($x, $yb, $wLado, $par[0], $indice);
            if ($par[0] !== null && $par[1] !== null) {
                $this->pEscreve($x + $wLado, $yb, $wSep, '/', $this->tamItem, '', 'C');
            }
            $this->pLadoPar($x + $wLado + $wSep, $yb, $wLado, $par[1], $indice);
            $yb += $this->lhItem;
        }
    }

    /**
     * DADOS DOS PRODUTOS / SERVIÇOS: desenha os itens [$ini, $fim)
     *
     * @return number Posição vertical final
     */
    protected function pItensDANFE($x, $y, $w, $ini, $fim)
    {
        $oldX = $x;
        $y = $this->pTitulo($x, $y, $w, 'DADOS DOS PRODUTOS / SERVIÇOS');
        $cols = $this->pColunasItens($w);
        //cabeçalho das colunas
        $yTopo = $y;
        $hCab = $this->hCabecalhoItens;
        $this->pdf->setFillColor(242, 242, 242);
        $this->pdf->rect($x, $y, $w, $hCab, 'DF');
        $this->pdf->setFillColor(255, 255, 255);
        $titulos = array(
            array('DESCRIÇÃO DO PRODUTO / SERVIÇO'), array('CST /', 'CFOP'), array('QTD / UN'), array('VLR UNIT'),
            array('VLR TOTAL'), array('BASES DE CÁLCULO'), array('ALÍQUOTAS'), array('VALOR DOS TRIBUTOS'),
        );
        $xi = $x;
        foreach ($cols as $k => $wc) {
            $t = $titulos[$k];
            $yb = (count($t) == 1) ? $y + ($hCab / 2) + 0.9 : $y + ($hCab / 2) - 0.4;
            foreach ($t as $linha) {
                $this->pEscreve($xi, $yb, $wc, $this->pEnc($linha), 5.5 * $this->kFonte, 'B', 'C');
                $yb += 2.3 * $this->kFonte;
            }
            $xi += $wc;
        }
        $y += $hCab;
        $lh = $this->lhItem;
        $tam = $this->tamItem;
        for ($i = $ini; $i < $fim; $i++) {
            $det = $this->det->item($i);
            $h = $this->pAlturaItem($det, $w);
            $prod = $this->pNo($det, 'prod');
            $icms = $this->pNo($this->pNo($det, 'imposto'), 'ICMS');
            $yb = $y + 0.6 + ($lh * 0.8);
            $xi = $oldX;
            //descrição
            list($linhas, $rodape) = $this->pDescricaoItem($det, $cols[0]);
            $ybl = $yb;
            foreach ($linhas as $l) {
                $this->pEscreve($xi, $ybl, $cols[0], $l[0], $tam, '', 'L', $l[1]);
                $ybl += $lh;
            }
            if ($rodape !== null) {
                $this->pEscreve($xi, $y + $h - 0.9, $cols[0], $rodape, $tam, '', $this->orientacao == 'P' ? 'R' : 'L', true);
            }
            $xi += $cols[0];
            //CST / CFOP
            $orig = (string) $this->pTag($icms, 'orig');
            $csosn = $this->pTag($icms, 'CSOSN');
            $cst = $this->pTag($icms, 'CST');
            if ($csosn !== null) {
                $this->pLider($xi, $yb, $cols[1], 'CSOSN', $orig . $csosn, $tam, false);
            } elseif ($cst !== null) {
                $this->pLider($xi, $yb, $cols[1], 'CST', $orig . $cst, $tam, false);
            }
            $this->pLider($xi, $yb + $lh, $cols[1], 'CFOP', (string) $this->pTag($prod, 'CFOP'), $tam, false);
            $xi += $cols[1];
            //QTD / UN
            $this->pEscreve($xi, $yb, $cols[2], $this->pTotalValor($prod, 'qCom', 4), $tam, '', 'C');
            $uCom = (string) $this->pTag($prod, 'uCom');
            if ($uCom !== '') {
                $this->pEscreve($xi, $yb + $lh, $cols[2], $this->pEnc('[' . $uCom . ']'), $tam, '', 'C', true);
            }
            $xi += $cols[2];
            //VLR UNIT / VLR TOTAL
            $this->pEscreve($xi, $yb, $cols[3], $this->pTotalValor($prod, 'vUnCom', 4), $tam, '', 'R');
            $xi += $cols[3];
            $this->pEscreve($xi, $yb, $cols[4], $this->pTotalValor($prod, 'vProd'), $tam, '', 'R');
            $xi += $cols[4];
            //BASES / ALÍQUOTAS / VALOR DOS TRIBUTOS
            $trib = $this->pTributosItem($det);
            $ybl = $yb;
            foreach ($trib['bases'] as $b) {
                $this->pLider($xi, $ybl, $cols[5], $b[0], $b[1], $tam);
                $ybl += $lh;
            }
            $xi += $cols[5];
            $this->pColunaPares($xi, $yb, $cols[6], $trib['pares'], 1);
            $xi += $cols[6];
            $this->pColunaPares($xi, $yb, $cols[7], $trib['pares'], 2);
            $y += $h;
            if ($i < $fim - 1) {
                $this->pdf->setLineWidth(0.1);
                $this->pdf->line($oldX, $y, $oldX + $w, $y);
                $this->pdf->setLineWidth(0.2);
            }
        }
        //moldura e divisões das colunas
        $this->pdf->rect($oldX, $yTopo, $w, $y - $yTopo);
        $this->pdf->line($oldX, $yTopo + $hCab, $oldX + $w, $yTopo + $hCab);
        $xi = $oldX;
        foreach ($cols as $k => $wc) {
            $xi += $wc;
            if ($k < count($cols) - 1) {
                $this->pdf->line($xi, $yTopo, $xi, $y);
            }
        }
        return $y;
    }

    //###########################################################
    // DADOS ADICIONAIS / QR CODE / RODAPÉ
    //###########################################################

    protected function pLarguraQRCode($w)
    {
        return ($this->qrCode !== '') ? round($w * 0.205, 1) : 0;
    }

    /**
     * Altura do quadro DADOS ADICIONAIS (define também $this->wAdic)
     */
    protected function pAlturaDadosAdicionais($w)
    {
        $wQR = $this->pLarguraQRCode($w);
        $this->wAdic = ($wQR > 0) ? round($w * 0.45, 1) : round($w * 0.66, 1);
        $linhas = count($this->pQuebra($this->textoAdic, $this->wAdic, 6.5));
        $h = $this->hTitulo + 4 + ($linhas * 2.7) + 1;
        if ($this->orientacao == 'P') {
            return max($h, ($wQR > 0) ? 36 : 22);
        }
        return max($h, ($wQR > 0) ? 30 : 18);
    }

    /**
     * DADOS ADICIONAIS: INFORMAÇÕES COMPLEMENTARES | RESERVADO AO FISCO e, se houver, QR CODE
     *
     * @return number Posição vertical final
     */
    protected function pDadosAdicionaisDANFE($x, $y, $w, $h)
    {
        $wQR = $this->pLarguraQRCode($w);
        $wDA = $w - $wQR - (($wQR > 0) ? 2 : 0);
        $yc = $this->pTitulo($x, $y, $wDA, 'DADOS ADICIONAIS');
        $hc = $h - $this->hTitulo;
        $wInf = $this->wAdic;
        $wRes = $wDA - $wInf;
        //INFORMAÇÕES COMPLEMENTARES
        $this->pdf->rect($x, $yc, $wInf, $hc);
        $this->pEscreve($x, $yc + 2.3, $wInf, $this->pEnc('INFORMAÇÕES COMPLEMENTARES'), 5.5, '', 'L', true);
        $yb = $yc + 5;
        foreach ($this->pQuebra($this->textoAdic, $wInf, 6.5) as $l) {
            if ($yb > $yc + $hc - 0.5) {
                break;
            }
            $this->pEscreve($x, $yb, $wInf, $l, 6.5);
            $yb += 2.7;
        }
        //RESERVADO AO FISCO (indica a contingência)
        $xr = $x + $wInf;
        $this->pdf->rect($xr, $yc, $wRes, $hc);
        $this->pEscreve($xr, $yc + 2.3, $wRes, $this->pEnc('RESERVADO AO FISCO'), 5.5, '', 'L', true);
        $xJust = $this->pSimpleGetValue($this->ide, 'xJust', 'Justificativa: ');
        $dhCont = $this->pSimpleGetValue($this->ide, 'dhCont', ' Entrada em contingência : ');
        $contingencias = array(
            2 => 'CONTINGÊNCIA FS',
            3 => 'CONTINGÊNCIA SCAN',
            4 => 'CONTINGÊNCIA DPEC',
            5 => 'CONTINGÊNCIA FSDA',
            6 => 'CONTINGÊNCIA SVC-AN',
            7 => 'CONTINGÊNCIA SVC-RS',
        );
        if (isset($contingencias[(int) $this->tpEmis])) {
            $yb = $yc + 5;
            foreach ($this->pQuebra($contingencias[(int) $this->tpEmis] . $dhCont . $xJust, $wRes, 6.5) as $l) {
                $this->pEscreve($xr, $yb, $wRes, $l, 6.5);
                $yb += 2.7;
            }
        }
        //QR CODE
        if ($wQR > 0) {
            $xq = $x + $wDA + 2;
            $this->pTitulo($xq, $y, $wQR, 'QR CODE', 'C');
            $this->pdf->rect($xq, $yc, $wQR, $hc);
            $lado = min($wQR - 6, $hc - 4);
            $this->pQRCodeDANFE($xq + (($wQR - $lado) / 2), $yc + (($hc - $lado) / 2), $lado);
        }
        return $y + $h;
    }

    /**
     * QR Code do infNFeSupl/qrCode (mesma geração do DACTE)
     */
    protected function pQRCodeDANFE($x, $y, $lado)
    {
        try {
            //o tc-lib-barcode 1.x emite E_USER_DEPRECATED no construtor; com display_errors ligado
            //o aviso sairia antes do PDF e o corromperia
            $barcode = @new Barcode();
            $bobj = $barcode->getBarcodeObj(
                'QRCODE,M',
                $this->qrCode,
                -4,
                -4,
                'black',
                array(-2, -2, -2, -2)
            )->setBackgroundColor('white');
            $pic = 'data://text/plain;base64,' . base64_encode($bobj->getPngData());
            $this->pdf->image($pic, $x, $y, $lado, $lado, 'PNG');
        } catch (\Throwable $e) {
            error_log('Danfe: QR Code não gerado - ' . $e->getMessage());
        }
    }

    /**
     * setTextoRodape
     * @param string $newTextoRodape
     * @return none
     * Author: VALTER
     */
    public function setTextoRodape($newTextoRodape)
    {
        $this->TextoRodape = $newTextoRodape;
    }

    /**
     * pRodape
     * Texto do rodapé (setTextoRodape) no pé da folha, na faixa reservada abaixo do último quadro
     */
    protected function pRodape($x, $w)
    {
        if ((string) $this->TextoRodape === '') {
            return;
        }
        $this->pEscreve($x, $this->yIni + $this->hPrint - 0.6, $w, $this->pEnc($this->TextoRodape), 6, '', 'R');
    }

    /**
     * pGeraInformacoesDaTagCompra
     * Devolve uma string contendo informação sobre as tag <compra><xNEmp>, <xPed> e <xCont> ou string vazia.
     * Aviso: Esta função não leva em consideração dados na tag xPed do item.
     *
     * @name   pGeraInformacoesDaTagCompra
     * @return string com as informacoes dos pedidos.
     */
    protected function pGeraInformacoesDaTagCompra()
    {
        $saida = "";
        if (isset($this->compra)) {
            if (! empty($this->compra->getElementsByTagName("xNEmp")->item(0)->nodeValue)) {
                $saida .= " Nota de Empenho: " . $this->compra->getElementsByTagName("xNEmp")->item(0)->nodeValue;
            }
            if (! empty($this->compra->getElementsByTagName("xPed")->item(0)->nodeValue)) {
                $saida .= " Pedido: " . $this->compra->getElementsByTagName("xPed")->item(0)->nodeValue;
            }
            if (! empty($this->compra->getElementsByTagName("xCont")->item(0)->nodeValue)) {
                $saida .= " Contrato: " . $this->compra->getElementsByTagName("xCont")->item(0)->nodeValue;
            }
        }
        return $saida;
    }

    /**
     * pGeraChaveAdicionalDeContingencia
     *
     * @name   pGeraChaveAdicionalDeContingencia
     * @return string chave
     */
    protected function pGeraChaveAdicionalDeContingencia()
    {
        //cUF tpEmis CNPJ vNF ICMSp ICMSs DD  DV
        // Quantidade de caracteres  02   01      14  14    01    01  02 01
        $forma  = "%02d%d%s%014d%01d%01d%02d";
        $cUF    = $this->ide->getElementsByTagName('cUF')->item(0)->nodeValue;
        $CNPJ   = "00000000000000" . $this->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $CNPJ   = substr($CNPJ, -14);
        $vNF    = $this->ICMSTot->getElementsByTagName("vNF")->item(0)->nodeValue * 100;
        $vICMS  = $this->ICMSTot->getElementsByTagName("vICMS")->item(0)->nodeValue;
        if ($vICMS > 0) {
            $vICMS = 1;
        }
        $icmss  = $this->ICMSTot->getElementsByTagName("vBC")->item(0)->nodeValue;
        if ($icmss > 0) {
            $icmss = 1;
        }
        $dEmi = ! empty($this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue : '';
        if ($dEmi == '') {
            $dEmi = ! empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue : '';
            $aDemi = explode('T', $dEmi);
            $dEmi = $aDemi[0];
        }
        $dd  = $dEmi;
        $rpos = strrpos($dd, '-');
        $dd  = substr($dd, $rpos +1);
        $chave = sprintf($forma, $cUF, $this->tpEmis, $CNPJ, $vNF, $vICMS, $icmss, $dd);
        $chave = $chave . $this->pModulo11($chave);
        return $chave;
    }

    /**
     * pGeraInformacoesDasNotasReferenciadas
     * Devolve uma string contendo informação sobre as notas referenciadas. Suporta N notas, eletrônicas ou não
     * Exemplo: NFe Ref.: série: 01 número: 01 emit: 11.111.111/0001-01
     * em 10/2010 [0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000]
     *
     * @return string Informacoes a serem adicionadas no rodapé sobre notas referenciadas.
     */
    protected function pGeraInformacoesDasNotasReferenciadas()
    {
        $formaNfeRef = "\r\nNFe Ref.: série:%d número:%d emit:%s em %s [%s]";
        $formaCTeRef = "\r\nCTe Ref.: série:%d número:%d emit:%s em %s [%s]";
        $formaNfRef = "\r\nNF  Ref.: série:%d numero:%d emit:%s em %s modelo: %d";
        $formaECFRef = "\r\nECF Ref.: modelo: %s ECF:%d COO:%d";
        $formaNfpRef = "\r\nNFP Ref.: série:%d número:%d emit:%s em %s modelo: %d IE:%s";
        $saida='';
        $nfRefs = $this->ide->getElementsByTagName('NFref');
        if (0 === $nfRefs->length) {
            return $saida;
        }
        foreach ($nfRefs as $nfRef) {
            if (empty($nfRef)) {
                continue;
            }
            $refNFe = $nfRef->getElementsByTagName('refNFe');
            foreach ($refNFe as $chave_acessoRef) {
                $chave_acesso = $chave_acessoRef->nodeValue;
                $chave_acessoF = $this->pFormat($chave_acesso, $this->formatoChave);
                $data = substr($chave_acesso, 4, 2)."/20".substr($chave_acesso, 2, 2);
                $cnpj = $this->pFormat(substr($chave_acesso, 6, 14), "##.###.###/####-##");
                $serie  = substr($chave_acesso, 22, 3);
                $numero = substr($chave_acesso, 25, 9);
                $saida .= sprintf($formaNfeRef, $serie, $numero, $cnpj, $data, $chave_acessoF);
            }
            $refNF = $nfRef->getElementsByTagName('refNF');
            foreach ($refNF as $umaRefNFe) {
                $data = $umaRefNFe->getElementsByTagName('AAMM')->item(0)->nodeValue;
                $cnpj = $umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue;
                $mod = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $serie = $umaRefNFe->getElementsByTagName('serie')->item(0)->nodeValue;
                $numero = $umaRefNFe->getElementsByTagName('nNF')->item(0)->nodeValue;
                $data = substr($data, 2, 2) . "/20" . substr($data, 0, 2);
                $cnpj = $this->pFormat($cnpj, "##.###.###/####-##");
                $saida .= sprintf($formaNfRef, $serie, $numero, $cnpj, $data, $mod);
            }
            $refCTe = $nfRef->getElementsByTagName('refCTe');
            foreach ($refCTe as $chave_acessoRef) {
                $chave_acesso = $chave_acessoRef->nodeValue;
                $chave_acessoF = $this->pFormat($chave_acesso, $this->formatoChave);
                $data = substr($chave_acesso, 4, 2)."/20".substr($chave_acesso, 2, 2);
                $cnpj = $this->pFormat(substr($chave_acesso, 6, 14), "##.###.###/####-##");
                $serie  = substr($chave_acesso, 22, 3);
                $numero = substr($chave_acesso, 25, 9);
                $saida .= sprintf($formaCTeRef, $serie, $numero, $cnpj, $data, $chave_acessoF);
            }
            $refECF = $nfRef->getElementsByTagName('refECF');
            foreach ($refECF as $umaRefNFe) {
                $mod    = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $nECF   = $umaRefNFe->getElementsByTagName('nECF')->item(0)->nodeValue;
                $nCOO   = $umaRefNFe->getElementsByTagName('nCOO')->item(0)->nodeValue;
                $saida .= sprintf($formaECFRef, $mod, $nECF, $nCOO);
            }
            $refNFP = $nfRef->getElementsByTagName('refNFP');
            foreach ($refNFP as $umaRefNFe) {
                $data = $umaRefNFe->getElementsByTagName('AAMM')->item(0)->nodeValue;
                $cnpj = ! empty($umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue) ?
                    $umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue :
                    '';
                $cpf = ! empty($umaRefNFe->getElementsByTagName('CPF')->item(0)->nodeValue) ?
                        $umaRefNFe->getElementsByTagName('CPF')->item(0)->nodeValue : '';
                $mod = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $serie = $umaRefNFe->getElementsByTagName('serie')->item(0)->nodeValue;
                $numero = $umaRefNFe->getElementsByTagName('nNF')->item(0)->nodeValue;
                $ie = $umaRefNFe->getElementsByTagName('IE')->item(0)->nodeValue;
                $data = substr($data, 2, 2) . "/20" . substr($data, 0, 2);
                if ($cnpj == '') {
                    $cpf_cnpj = $this->pFormat($cpf, "###.###.###-##");
                } else {
                    $cpf_cnpj = $this->pFormat($cnpj, "##.###.###/####-##");
                }
                $saida .= sprintf($formaNfpRef, $serie, $numero, $cpf_cnpj, $data, $mod, $ie);
            }
        }
        return $saida;
    }
}
