<?php

namespace NFePHP\DA\MDFe;

/**
 * Esta classe gera do PDF do MDFDe, conforme regras e estruturas
 * estabelecidas pela SEFAZ.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @name      Damdfe.php
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-da for the canonical source repository
 * @author    Leandro C. Lopez <leandro dot castoldi at gmail dot com>
 */

use Com\Tecnick\Barcode\Barcode; //VALTER - qrCode
//use NFePHP\Common\Dom\Dom; //VALTER
use NFePHP\DA\Legacy\Dom; //VALTER
use NFePHP\DA\Legacy\Common;
use NFePHP\DA\Legacy\Pdf;

class Damdfe extends Common
{
    //publicas
    public $logoAlign='L'; //alinhamento do logo
    public $yDados=0;
    public $debugMode=0; //ativa ou desativa o modo de debug
    //privadas
    /**
     * objeto fpdf()
     * @var object
     */
    protected $pdf = ""; // objeto fpdf()
    protected $xml; // string XML NFe
    protected $logomarca=''; // path para logomarca em jpg
    protected $errMsg=''; // mesagens de erro
    protected $errStatus=false;// status de erro TRUE um erro ocorreu false sem erros
    protected $orientacao='P'; //orientação da DANFE P-Retrato ou L-Paisagem
    protected $papel='A4'; //formato do papel
    //destivo do arquivo pdf I-borwser, S-retorna o arquivo, D-força download, F-salva em arquivo local
    protected $destino = 'I';
    protected $pdfDir=''; //diretorio para salvar o pdf com a opção de destino = F
    protected $fontePadrao='Times'; //Nome da Fonte para gerar o DANFE
    protected $version = '1.0.0';
    protected $wPrint; //largura imprimivel
    protected $hPrint; //comprimento imprimivel
    protected $formatoChave="#### #### #### #### #### #### #### #### #### #### ####";
    protected $qrCodH = 11; //VALTER qrCode
    //variaveis da carta de correção
    protected $id;
    protected $chMDFe;
    protected $tpAmb;
    protected $cOrgao;
    protected $xCondUso;
    protected $dhEvento;
    protected $cStat;
    protected $xMotivo;
    protected $CNPJDest = '';
    protected $dhRegEvento;
    protected $nProt;
    protected $tpEmis;
    //objetos
    private $dom;
    // private $procEventoNFe;
    // private $evento;
    // private $infEvento;
    // private $retEvento;
    // private $rinfEvento;
    protected $qrCodMDFe; //qrCode - VALTER

    protected $mdfeProc;
    protected $infMDFe;
    protected $emit;
    protected $CNPJ;
    protected $IE;
    protected $xNome;
    protected $enderEmit;
    protected $xLgr;
    protected $nro;
    protected $xBairro;
    protected $UF;
    protected $xMun;
    protected $CEP;
    protected $ide;
    protected $mod;
    protected $serie;
    protected $dhEmi;
    protected $UFIni;
    protected $UFFim;
    protected $nMDF;
    protected $tot;
    protected $qNFe;
    protected $qNF;
    protected $qCTe;
    protected $qCT;
    protected $cUnid;
    protected $qCarga;
    protected $infModal;
    protected $rodo;
    protected $ciot;
    protected $infContratante;
    protected $seg;
    protected $veicTracao;
    protected $veicReboque;
    protected $valePed;
    protected $infCpl;
    protected $dhRecbto;
    protected $condutor;

    protected $TextoRodape = ''; //VALTER
    protected $TextoMensagem = ''; //VALTER
    
    /**
     * __construct
     *
     * @param string $xmlfile Arquivo XML da MDFe
     * @param string $sOrientacao (Opcional) Orientação da impressão P-retrato L-Paisagem
     * @param string $sPapel Tamanho do papel (Ex. A4)
     * @param string $sPathLogo Caminho para o arquivo do logo
     * @param string $sDestino Estabelece a direção do envio do documento PDF I-browser D-browser com download S-
     * @param string $sDirPDF Caminho para o diretorio de armazenamento dos arquivos PDF
     * @param string $fonteDAMDFE Nome da fonte alternativa do DAnfe
     * @param integer $mododebug 0-Não 1-Sim e 2-nada (2 default)
     */
    public function __construct(
        $xmlfile = '',
        $sOrientacao = '',
        $sPapel = '',
        $sPathLogo = '',
        $sDestino = 'I',
        $sDirPDF = '',
        $fontePDF = '',
        $mododebug = 2
    ) {
        //define o caminho base da instalação do sistema
        if (!defined('PATH_ROOT')) {
            define('PATH_ROOT', dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR);
        }
//ajuste do tempo limite de resposta do processo
        set_time_limit(1800);
//definição do caminho para o diretorio com as fontes do FDPF
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', 'font/');
        }

        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($this->debugMode) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } else {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        $this->orientacao   = $sOrientacao;
        $this->papel        = $sPapel;
        $this->pdf          = '';
        $this->xml          = $xmlfile;
        $this->logomarca    = $sPathLogo;
        $this->destino      = $sDestino;
        $this->pdfDir       = $sDirPDF;
        // verifica se foi passa a fonte a ser usada
        if (empty($fontePDF)) {
            $this->fontePadrao = 'Times';
        } else {
            $this->fontePadrao = $fontePDF;
        }
        //se for passado o xml
        if (empty($xmlfile)) {
            $this->errMsg = 'Um caminho para o arquivo xml da MDFe deve ser passado!';
            $this->errStatus = true;
        }
        if (!is_file($xmlfile)) {
            $this->errMsg = 'Um caminho para o arquivo xml da MDFe deve ser passado!';
            $this->errStatus = true;
        }

//        $docxml = file_get_contents($xmlfile);
        $this->dom = new Dom();
        $this->dom->loadXML($this->xml);
        $this->mdfeProc = $this->dom->getElementsByTagName("mdfeProc")->item(0);
        $this->infMDFe = $this->dom->getElementsByTagName("infMDFe")->item(0);
        $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
        
        if ($this->emit->getElementsByTagName("CPF")->item(0)) {
            $this->CNPJ = $this->dom->getElementsByTagName("CPF")->item(0)->nodeValue;
        } else {
            $this->CNPJ = $this->dom->getElementsByTagName("CNPJ")->item(0)->nodeValue;
        }

        //$this->CNPJ = $this->dom->getElementsByTagName("CNPJ")->item(0)->nodeValue;

        $this->IE = $this->dom->getElementsByTagName("IE")->item(0)->nodeValue;
        $this->xNome = $this->dom->getElementsByTagName("xNome")->item(0)->nodeValue;
        $this->enderEmit = $this->dom->getElementsByTagName("enderEmit")->item(0);
        $this->xLgr = $this->dom->getElementsByTagName("xLgr")->item(0)->nodeValue;
        $this->nro = $this->dom->getElementsByTagName("nro")->item(0)->nodeValue;
        $this->xBairro = $this->dom->getElementsByTagName("xBairro")->item(0)->nodeValue;
        $this->UF = $this->dom->getElementsByTagName("UF")->item(0)->nodeValue;
        $this->xMun = $this->dom->getElementsByTagName("xMun")->item(0)->nodeValue;
        $this->CEP = $this->dom->getElementsByTagName("CEP")->item(0)->nodeValue;
        $this->ide = $this->dom->getElementsByTagName("ide")->item(0);
        $this->tpAmb = $this->dom->getElementsByTagName("tpAmb")->item(0)->nodeValue;
        $this->mod = $this->dom->getElementsByTagName("mod")->item(0)->nodeValue;
        $this->serie = $this->dom->getElementsByTagName("serie")->item(0)->nodeValue;
        $this->dhEmi = $this->dom->getElementsByTagName("dhEmi")->item(0)->nodeValue;
        $this->UFIni = $this->dom->getElementsByTagName("UFIni")->item(0)->nodeValue;
        $this->UFFim = $this->dom->getElementsByTagName("UFFim")->item(0)->nodeValue;
        $this->nMDF = $this->dom->getElementsByTagName("nMDF")->item(0)->nodeValue;
        $this->tpEmis = $this->dom->getElementsByTagName("tpEmis")->item(0)->nodeValue;
        $this->tot = $this->dom->getElementsByTagName("tot")->item(0);
        $this->qNFe = "";
        if ($this->dom->getElementsByTagName("qNFe")->item(0) != "") {
            $this->qNFe = $this->dom->getElementsByTagName("qNFe")->item(0)->nodeValue;
        }
        $this->qNF = "";
        if ($this->dom->getElementsByTagName("qNF")->item(0) != "") {
            $this->qNF = $this->dom->getElementsByTagName("qNF")->item(0)->nodeValue;
        }
        $this->qCTe = "";
        if ($this->dom->getElementsByTagName("qCTe")->item(0) != "") {
            $this->qCTe = $this->dom->getElementsByTagName("qCTe")->item(0)->nodeValue;
        }
        $this->qCT = "";
        if ($this->dom->getElementsByTagName("qCT")->item(0) != "") {
            $this->qCT = $this->dom->getElementsByTagName("qCT")->item(0)->nodeValue;
        }
        $this->cUnid = $this->dom->getElementsByTagName("cUnid")->item(0)->nodeValue; //VALTER
        $this->qCarga = $this->dom->getElementsByTagName("qCarga")->item(0)->nodeValue;
        $this->infModal = $this->dom->getElementsByTagName("infModal")->item(0);
        $this->rodo = $this->dom->getElementsByTagName("rodo")->item(0);
        $this->ciot = "";
        if ($this->dom->getElementsByTagName('CIOT')->item(0) != "") {
            $this->ciot = $this->dom->getElementsByTagName('CIOT')->item(0)->nodeValue;
        }
        //infContratante - VALTER
        $this->infContratante = "";
        if ($this->dom->getElementsByTagName('infContratante')->item(0) != "") {
            //$this->infContratante = $this->dom->getElementsByTagName('infContratante')->item(0)->nodeValue; //esta caso queira pegar a string
            $this->infContratante = $this->dom->getElementsByTagName('infContratante')->item(0);
        }
        //infContratante - VALTER
        //seg - VALTER
        $this->seg = ""; //seguro
        $this->seg = $this->dom->getElementsByTagName('seg');
        /*if ($this->dom->getElementsByTagName('seg') != "") {
            $this->seg = $this->dom->getElementsByTagName('seg');
        }*/
        //seg - VALTER
        $this->veicTracao = $this->dom->getElementsByTagName("veicTracao")->item(0);
        $this->veicReboque = $this->dom->getElementsByTagName("veicReboque");
        //Rafael
        $this->seg = $this->dom->getElementsByTagName("seg");
        $this->valePed = "";
        if ($this->dom->getElementsByTagName("valePed")->item(0) != "") {
            $this->valePed = $this->dom->getElementsByTagName("valePed")->item(0)->getElementsByTagName("disp");
        }
        $this->infCpl = ($infCpl = $this->dom->getElementsByTagName('infCpl')->item(0)) ? $infCpl->nodeValue : "";
        $this->chMDFe = str_replace(
            'MDFe',
            '',
            $this->infMDFe->getAttribute("Id")
        );

        //qrCode - VALTER
        $this->qrCodMDFe = $this->dom->getElementsByTagName('qrCodMDFe')->item(0) ?
            $this->dom->getElementsByTagName('qrCodMDFe')->item(0)->nodeValue : null;

        if (is_object($this->mdfeProc)) {
            $this->nProt = ! empty($this->mdfeProc->getElementsByTagName("nProt")->item(0)->nodeValue) ?
                    $this->mdfeProc->getElementsByTagName("nProt")->item(0)->nodeValue : '';
            $this->dhRecbto = $this->mdfeProc->getElementsByTagName("dhRecbto")->item(0)->nodeValue;
        }
    }//fim construct
    /**
     *buildMDFe
     *
     */
    public function buildMDFe()
    {
        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        if ($this->orientacao == 'P') {
            // margens do PDF
            $margSup = 7;
            $margEsq = 7;
            $margDir = 7;
            // posição inicial do relatorio
            $xInic = 7;
            $yInic = 7;
            if ($this->papel =='A4') { //A4 210x297mm
                $maxW = 210;
                $maxH = 297;
            }
        } else {
            // margens do PDF
            $margSup = 7;
            $margEsq = 7;
            $margDir = 7;
            // posição inicial do relatorio
            $xInic = 7;
            $yInic = 7;
            if ($this->papel =='A4') { //A4 210x297mm
                $maxH = 210;
                $maxW = 297;
            }
        }//orientação
        //largura imprimivel em mm
        $this->wPrint = $maxW-($margEsq+$xInic);
        //comprimento imprimivel em mm
        $this->hPrint = $maxH-($margSup+$yInic);
        // estabelece contagem de paginas
        $this->pdf->AliasNbPages();
        // fixa as margens
        $this->pdf->SetMargins($margEsq, $margSup, $margDir);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetFillColor(255, 255, 255);
        // inicia o documento
        $this->pdf->Open();
        // adiciona a primeira página
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->SetTextColor(0, 0, 0);
        //montagem da página
        $pag = 1;
        $x = $xInic;
        $y = $yInic;
        //coloca o cabeçalho Paisagem
        if ($this->orientacao == 'P') {
            $y = $this->headerMDFeRetrato($x, $y, $pag);
        } else {
            $y = $this->headerMDFePaisagem($x, $y, $pag);
        }
        //coloca os dados da MDFe
        $y = $this->bodyMDFe($x, $y);
        //coloca os campos de seguro //VALTER
        $y = $this->seguroMDFe($x, $y); //VALTER
        //coloca os dados da MDFe
        $y = $this->footerMDFe($x, $y);
    } //fim buildCCe
    /**
     * headerMDFePaisagem
     * @param float $x
     * @param float $y
     * @param integer $pag
     * @return string
     */
    private function headerMDFePaisagem($x, $y, $pag)
    {
        $oldX = $x;
        $oldY = $y;
        $maxW = $this->wPrint;
        //####################################################################################
        //coluna esquerda identificação do emitente
        $w = $maxW; //round($maxW*0.41, 0);// 80;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $w1 = $w;
        $h=30;
        $oldY += $h;
        $this->pTextBox($x, $y, $w, $h);
        if (is_file($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            //largura da imagem em mm
            $logoWmm = ($logoInfo[0]/72)*25.4;
            //altura da imagem em mm
            $logoHmm = ($logoInfo[1]/72)*25.4;
            if ($this->logoAlign=='L') {
                $nImgW = round($w/4.5, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = $x+1;
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                //estabelecer posições do texto
                $x1 = round($xImg + $nImgW +1, 0);
                $y1 = round($y+2, 0);
                $tw = round(2*$w/3, 0);
            }
            if ($this->logoAlign=='C') {
                $nImgH = round($h/3, 0);
                $nImgW = round($logoWmm * ($nImgH/$logoHmm), 0);
                $xImg = round(($w-$nImgW)/2+$x, 0);
                $yImg = $y+3;
                $x1 = $x;
                $y1 = round($yImg + $nImgH + 1, 0);
                $tw = $w;
            }
            if ($this->logoAlign=='R') {
                $nImgW = round($w/3, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = round($x+($w-(1+$nImgW)), 0);
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                $x1 = $x;
                $y1 = round($h/3+$y, 0);
                $tw = round(2*$w/3, 0);
            }
            $this->pdf->Image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH, 'jpeg');
        } else {
            $x1 = $x;
            $y1 = round($h/3+$y, 0);
            $tw = $w;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $razao = $this->xNome;

        if (strlen($this->CNPJ) == 11) {
            $cnpj = 'CPF: '.$this->pFormat($this->CNPJ, "###.###.###-##");
        } else {
            $cnpj = 'CNPJ: '.$this->pFormat($this->CNPJ, "###.###.###/####-##");
        }

        //$cnpj = 'CNPJ: '.$this->pFormat($this->CNPJ, "###.###.###/####-##");

        $ie = 'IE: '.$this->pFormat($this->IE, '##/########');
        $lgr = 'Logradouro: '.$this->xLgr;
        $nro = 'Nº: '.$this->nro;
        $bairro = 'Bairro: '.$this->xBairro;
        $CEP = $this->CEP;
        $CEP = 'CEP: '.$this->pFormat($CEP, "##.###-###");
        $UF = 'UF: '.$this->UF;
        $mun = 'Municipio: '.$this->xMun;
        
        $texto = $razao . "\n" . $cnpj . ' - ' . $ie . "\n";
        $texto .= $lgr . ' - ' . $nro . "\n";
        $texto .= $bairro . "\n";
        $texto .= $UF . ' - ' . $mun . ' - ' . $CEP;
        $this->pTextBox($x1, $y1+5, $tw, 8, $texto, $aFont, 'T', 'L', 0, '');
        $x = $x+$maxW/2;
        $w = $maxW / 2;
        $this->pTextBox($x, $y, $w, $h);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>12, 'style'=>'I');
        $this->pTextBox(
            $x,
            $y,
            $w,
            8,
            'DAMDFE - Documento Auxiliar de Manifesto Eletronico de Documentos Fiscais',
            $aFont,
            'T',
            'C',
            0,
            ''
        );
        $this->pTextBox($x, $y, $w, 6);
        $bH = 13;
        $bW = round(($w), 0);
        $this->pdf->SetFillColor(0, 0, 0);
        $this->pdf->Code128($x+5, $y+7.5, $this->chMDFe, $bW-10, $bH);
        $this->pdf->SetFillColor(255, 255, 255);

        // VALTER - qrCode no modo paisagem não testado
        if ($this->qrCodMDFe) {
            $y = $this->pQRDANFE($x, $y1 - 3, 50);
        }

        $y = $y + 22;
        $this->pTextBox($x, $y, $w, 8);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'I');
        $tsHora = $this->pConvertTime($this->dhEvento);
        $texto = 'CHAVE DE ACESSO';
        $this->pTextBox($x, $y, $maxW, 6, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $texto = $this->pFormat($this->chMDFe, $this->formatoChave);
        $this->pTextBox($x, $y+3, $w, 6, $texto, $aFont, 'T', 'C', 0, '');
        $y = $y + 11;
        $this->pTextBox($x, $y, $w, 12);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'I');
        $texto = 'PROTOCOLO DE AUTORIZACAO DE USO';
        $this->pTextBox($x, $y, $w, 8, $texto, $aFont, 'T', 'L', 0, '');
        
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        if (is_object($this->mdfeProc)) {
            $tsHora = $this->pConvertTime($this->dhRecbto);
            $texto = $this->nProt.' - '.date('d/m/Y   H:i:s', $tsHora);
        } else {
            $texto = 'DAMDFE impresso em contingência - '.date('d/m/Y   H:i:s');
        }
        $this->pTextBox($x, $y+4, $w, 8, $texto, $aFont, 'T', 'C', 0, '');

        // Impressão de mensagem (para encerramento, etc)
        if ($this->TextoMensagem != '') {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }
            $h = -90;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = $this->TextoMensagem;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
        }

        if ($this->tpAmb != 1) {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pTextBox($x, $yy+14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }//fim orientacao
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            //indicar FALTA DO PROTOCOLO se MDFe não for em contingência
            if (($this->tpEmis == 2 || $this->tpEmis == 5)) {
                //Contingência
                $texto = "DAMDFE Emitido em Contingência";
                $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
                $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
                $texto = "devido à problemas técnicos";
                $this->pTextBox($x, $yy+12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            }
            $this->pdf->SetTextColor(0, 0, 0);
        }

        return $y;
    }// fim headerMDFe
    
    /**
     * headerMDFeRetrato
     *
     * @param float $x
     * @param float $y
     * @param integer $pag
     * @return string
     */
    private function headerMDFeRetrato($x, $y, $pag)
    {
        $oldX = $x;
        $oldY = $y;
        $maxW = $this->wPrint;
        //####################################################################################
        //coluna esquerda identificação do emitente
        $w = $maxW; //round($maxW*0.41, 0);// 80;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'I');
        $w1 = $w;
        $h=20;
        $oldY += $h;
        $this->pTextBox($x, $y, $w, $h);
        if (is_file($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            //largura da imagem em mm
            $logoWmm = ($logoInfo[0]/72)*25.4;
            //altura da imagem em mm
            $logoHmm = ($logoInfo[1]/72)*25.4;
            if ($this->logoAlign=='L') {
                $nImgW = round($w/8, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = $x+1;
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                //estabelecer posições do texto
                $x1 = round($xImg + $nImgW +1, 0);
                $y1 = round($y+2, 0);
                $tw = round(2*$w/3, 0);
            }
            if ($this->logoAlign=='C') {
                $nImgH = round($h/3, 0);
                $nImgW = round($logoWmm * ($nImgH/$logoHmm), 0);
                $xImg = round(($w-$nImgW)/2+$x, 0);
                $yImg = $y+3;
                $x1 = $x;
                $y1 = round($yImg + $nImgH + 1, 0);
                $tw = $w;
            }
            if ($this->logoAlign=='R') {
                $nImgW = round($w/3, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = round($x+($w-(1+$nImgW)), 0);
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                $x1 = $x;
                $y1 = round($h/3+$y, 0);
                $tw = round(2*$w/3, 0);
            }
            $this->pdf->Image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH, 'jpeg');
        } else {
            $x1 = $x+40;
            $y1 = $y;
            $tw = $w;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $razao = $this->xNome;

        if (strlen($this->CNPJ) == 11) {
            $cnpj = 'CPF: '.$this->pFormat($this->CNPJ, "###.###.###-##");
        } else {
            $cnpj = 'CNPJ: '.$this->pFormat($this->CNPJ, "###.###.###/####-##");
        }

        //$cnpj = 'CNPJ: '.$this->pFormat($this->CNPJ, "###.###.###/####-##");

        $ie = 'IE: '.$this->pFormat($this->IE, '###/#######');
        $lgr = 'Logradouro: '.$this->xLgr;
        $nro = 'Nº: '.$this->nro;
        $bairro = 'Bairro: '.$this->xBairro;
        $CEP = $this->CEP;
        $CEP = 'CEP: '.$this->pFormat($CEP, "##.###-###");
        $mun = 'Municipio: '.$this->xMun;
        $UF = 'UF: '.$this->UF;
        $texto = $razao . "\n" . $cnpj . ' - ' . $ie . "\n";
        $texto .= $lgr . ' - ' . $nro . "\n";
        $texto .= $bairro . "\n";
        $texto .= $UF . ' - ' . $mun . ' - ' . $CEP;
        $this->pTextBox($x1, $y1, $tw, 8, $texto, $aFont, 'T', 'L', 0, '');
        //##################################################
        $y = $h + 8;
        $this->pTextBox($x, $y, $maxW, 6);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>12, 'style'=>'I');
        $this->pTextBox(
            $x,
            $y,
            $maxW,
            8,
            'DAMDFE - Documento Auxiliar de Manifesto Eletronico de Documentos Fiscais',
            $aFont,
            'T',
            'C',
            0,
            ''
        );
        $y = $y + 8;

        $bW = 75; //$maxW - 100; //VALTER
        $this->pdf->SetFillColor(0, 0, 0);
        // VALTER - qrCode
        $bcH = $x+(($w-$bW)/2);
        $qcH = 0;
        if ($this->qrCodMDFe) {
            $qcH = $this->qrCodH; 
            $bcH = $x+15;
        }
        $bH = 16+$qcH; //VALTER
        $this->pTextBox($x, $y, $maxW, 20+$qcH); //20 //VALTER
        //$this->pdf->Code128($x+(($w-$bW)/2), $y+2, $this->chMDFe, $bW, $bH); //VALTER $bW-10 //$this->pdf->Code128($x+5 + 50, $y+2, $this->chMDFe, $bW, $bH);
        $this->pdf->Code128($bcH, $y+2, $this->chMDFe, $bW, $bH); //VALTER $bW-10 //$this->pdf->Code128($x+5 + 50, $y+2, $this->chMDFe, $bW, $bH);
        $this->pdf->SetFillColor(255, 255, 255);

        // VALTER - qrCode
        if ($this->qrCodMDFe) {
            $this->pQRDANFE($y); 
        }

        $y = $y + 22+$qcH; //22; //VALTER
        $this->pTextBox($x, $y, $maxW, 10);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'I');
        $tsHora = $this->pConvertTime($this->dhEvento);
        $texto = 'CHAVE DE ACESSO';
        $this->pTextBox($x, $y, $maxW, 6, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $texto = $this->pFormat($this->chMDFe, $this->formatoChave);
        $this->pTextBox($x, $y+4, $maxW, 6, $texto, $aFont, 'T', 'C', 0, '');
        $y = $y + 12;
        $this->pTextBox($x, $y, $maxW, 10);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'I');
        $texto = 'PROTOCOLO DE AUTORIZACAO DE USO';
        $this->pTextBox($x, $y, $maxW, 8, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        if (is_object($this->mdfeProc)) {
            $tsHora = $this->pConvertTime($this->dhRecbto);
            $texto = $this->nProt.' - '.date('d/m/Y   H:i:s', $tsHora);
        } else {
            $texto = 'DAMDFE impresso em contingência - '.date('d/m/Y   H:i:s');
        }
        $this->pTextBox($x, $y+4, $maxW, 8, $texto, $aFont, 'T', 'C', 0, '');

        // Impressão de mensagem (para encerramento, etc)
        if ($this->TextoMensagem != '') {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }
            $h = -90; //55;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = $this->TextoMensagem;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
        }

        if ($this->tpAmb != 1) {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pTextBox($x, $yy+14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            $x = 10;
            if ($this->orientacao == 'P') {
                $yy = round($this->hPrint*2/3, 0);
            } else {
                $yy = round($this->hPrint/2, 0);
            }//fim orientacao
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            //indicar FALTA DO PROTOCOLO se MDFe não for em contingência
            if (($this->tpEmis == 2 || $this->tpEmis == 5)) {
                //Contingência
                $texto = "DAMDFE Emitido em Contingência";
                $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
                $this->pTextBox($x, $yy, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
                $texto = "devido à problemas técnicos";
                $this->pTextBox($x, $yy+12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            }
            $this->pdf->SetTextColor(0, 0, 0);
        }
        return $y+12;
    }// fim headerMDFe
    
    /**
     * bodyMDFe
     *
     * @param float $x
     * @param float $y
     * @return void
     */
    private function bodyMDFe($x, $y)
    {
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint / 2;
        }
        $x2 = ($maxW / 6);
        $x1 = $x2;
        $this->pTextBox($x, $y, $x2-7, 12);
        $texto = 'Modelo';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x, $y, $x2-7, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->mod;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x, $y+4, $x2-7, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 = $x2;
        $this->pTextBox($x1, $y, $x2-7, 12);
        $texto = 'Série';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-7, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->serie;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2-7, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2-7;
        $this->pTextBox($x1, $y, $x2+5, 12);
        $texto = 'Número';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2+5, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->pFormat(str_pad($this->nMDF, 9, '0', STR_PAD_LEFT), '###.###.###');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2+5, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2+5;
        $this->pTextBox($x1, $y, $x2-7, 12);
        $texto = 'FL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-7, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = '1';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2-7, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2-7;
        $this->pTextBox($x1, $y, $x2+11, 12);
        $texto = 'Data e Hora de Emissão';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2+11, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $data = explode('T', $this->dhEmi);
        $texto = $this->pYmd2dmy($data[0]).' - '.$data[1];
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2+11, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2+11;
        $this->pTextBox($x1, $y, $x2-15, 12);
        $texto = 'UF Carreg.';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-15, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->UFIni;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2-15, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $maxW = $this->wPrint;

        $x1 += $x2-15;
        $this->pTextBox($x1, $y, $x2-13, 12);
        $texto = 'UF Descar.';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-13, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->UFFim;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2-13, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $maxW = $this->wPrint;



        $x1 = $x;
        $x2 = $maxW;
        $y += 14;
        $this->pTextBox($x1, $y, $x2, 23);
        $texto = 'Modal Rodoviário de Carga';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $this->pTextBox($x1, $y+1, $x2, 8, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 = $x;
        $x2 = ($maxW / 6);
        $y += 6;
        $this->pTextBox($x1, $y, $x2, 12);
        $texto = 'Qtd. CT-e';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = str_pad($this->qCTe, 3, '0', STR_PAD_LEFT);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2;
        $this->pTextBox($x1, $y, $x2, 12);
        $texto = 'Qtd. NF-e';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = str_pad($this->qNFe, 3, '0', STR_PAD_LEFT);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 += $x2;
        $this->pTextBox($x1, $y, $x2, 12);
        $texto = 'Peso Total '.$this->zUnidadeCarga();
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = number_format($this->qCarga, 4, ', ', '.');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);

        //CIOT //VALTER
        $x1 += $x2;
        $this->pTextBox($x1, $y, $x2, 12);
        $texto = 'CIOT';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->ciot;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        //CIOT //VALTER

        //CONTRATANTE //VALTER
        if ($this->infContratante != '') {
            $x1 += $x2;
            $x2 = ($maxW - ($x2 * 4));
            $this->pTextBox($x1, $y, $x2, 12); //caixa
            $texto = 'Contratante';
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);

            if (! empty($this->infContratante->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
                $texto = $this->pFormat(
                    $this->infContratante->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                    "###.###.###/####-##"
                );
            } else {
                $texto = ! empty($this->infContratante->getElementsByTagName("CPF")->item(0)->nodeValue) ?
                    $this->pFormat(
                        $this->infContratante->getElementsByTagName("CPF")->item(0)->nodeValue,
                        "###.###.###-##"
                    ) : '';
            }

            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
            $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        }
        //CONTRATANTE //VALTER

        $x1 = $x;
        $y += 12;
        $yold = $y;
        $x2 = round($maxW / 2, 0);
        $texto = 'Veículo';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $y += 5;
        $x2 = round($maxW / 4, 0);
        $tamanho = 22;
        $this->pTextBox($x1, $y, $x2, $tamanho);
        $texto = 'Placa';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $this->veicTracao->getElementsByTagName("placa")->item(0)->nodeValue;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $altura = $y + 4;
        /** @var \DOMNodeList $veicReboque */
        $veicReboque = $this->veicReboque;
        foreach ($veicReboque as $item) {
            /** @var \DOMElement $item */
            $altura += 4;
            $texto = $item->getElementsByTagName('placa')->item(0)->nodeValue;
            $this->pTextBox($x1, $altura, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        }
        $x1 += $x2;
        $this->pTextBox($x1, $y, $x2, $tamanho);
        $texto = 'RNTRC';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        // RNTRC Não informado
        if ($this->rodo->getElementsByTagName("RNTRC")->length > 0) {
            $texto = $this->rodo->getElementsByTagName("RNTRC")->item(0)->nodeValue;
        } else {
            $texto = "";
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
        $this->pTextBox($x1, $y+4, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
        $altura = $y + 4;
        /** @var \DOMNodeList $veicReboque */
        $veicReboque = $this->veicReboque;
        foreach ($veicReboque as $item) {
            /** @var \DOMElement $item */
            $DOMNodeList = $item->getElementsByTagName('RNTRC');
            if ($DOMNodeList->length > 0) {
                $altura += 4;
                $texto = $DOMNodeList->item(0)->nodeValue;
                $this->pTextBox($x1, $altura, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
            }
        }
        $x1 = $x;
        $y += 22;
        $x2 = round($maxW / 2, 0);
        $valesPedagios = 1;
        $temVales = false;
        if ($this->valePed != "" && $this->valePed->length > 0) {
            $valesPedagios = $this->valePed->length;
            $temVales = true;
        }
        $tamanho = ($valesPedagios * 7.5);
        if (!$temVales) {
            $valesPedagios = 0;
        }
        $this->pTextBox($x1, $y, $x2, 11+$tamanho/2);
        $texto = 'Vale Pedágio';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $y += 5;
        $x2 = ($x2 / 3);
        $this->pTextBox($x1, $y, $x2-3, 6+($tamanho/2));
        $texto = 'Responsável CNPJ';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-4, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $altura = $y;
        for ($i = 0; $i < $valesPedagios; $i++) {
            $altura += 4;
            $texto = $this->valePed->item($i)->getElementsByTagName('CNPJForn')->item(0)->nodeValue;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
            $this->pTextBox($x1 + 1, $altura, $x2-5, 10, $texto, $aFont, 'T', 'L', 0, '', false);
        }
        $x1 += $x2-3;
        $this->pTextBox($x1, $y, $x2-3, 6+($tamanho/2));
        $texto = 'Fornecedora CNPJ';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2-4, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $altura = $y;
        for ($i = 0; $i < $valesPedagios; $i++) {
            $altura += 4;
            $texto = $this->valePed->item($i)->getElementsByTagName('CNPJPg')->item(0)->nodeValue;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
            $this->pTextBox($x1 + 1, $altura, $x2-5, 10, $texto, $aFont, 'T', 'L', 0, '', false);
        }
        $x1 += $x2-3;
        $this->pTextBox($x1, $y, $x2+6, 6+($tamanho/2));
        $texto = 'Nº Comprovante';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2+6, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $altura = $y;
        for ($i = 0; $i < $valesPedagios; $i++) {
            $altura += 4;
            $texto = $this->valePed->item($i)->getElementsByTagName('nCompra')->item(0)->nodeValue;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
            $this->pTextBox($x1 + 1, $altura, $x2+5, 10, $texto, $aFont, 'T', 'L', 0, '', false);
        }
        if (!$temVales) {
            $altura += 4;
        }
        $this->condutor = $this->veicTracao->getElementsByTagName('condutor');
        $x1 = round($maxW / 2, 0) + 7;
        $y = $yold;
        $x2 = round($maxW / 2, 0);
        $texto = 'Condutor';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $y += 5;
        $x2 = ($maxW / 4);
        $this->pTextBox($x1, $y, $x2, 33+($tamanho/2));
        $texto = 'CPF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        $yold = $y;
        for ($i = 0; $i < $this->condutor->length; $i++) {
            $y += 4;
            $texto = $this->condutor->item($i)->getElementsByTagName('CPF')->item(0)->nodeValue;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'');
            $this->pTextBox($x1 + 1, $y, $x2 - 1, 10, $texto, $aFont, 'T', 'L', 0, '', false);
        }
        $y = $yold;
        $x1 += $x2;
        $this->pTextBox($x1, $y, $x2, 33+($tamanho/2));
        $texto = 'Nome';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        for ($i = 0; $i < $this->condutor->length; $i++) {
            $y += 4;
            $texto = $this->condutor->item($i)->getElementsByTagName('xNome')->item(0)->nodeValue;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1 + 1, $y, $x2 - 1, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        }
        return $altura + 7;
    }//fim bodyMDFe

    /**
     * VALTER
     * seguroMDFe
     *
     * @param float $x
     * @param float $y
     * @return void
     */
    private function seguroMDFe($x, $y)
    {
        if ($this->seg != '') {
            if ($this->orientacao == 'P') {
                $maxW = $this->wPrint;
            } else {
                $maxW = $this->wPrint / 2;
            }

            $x2 = $maxW;
            $x1 = $x;
            $this->pTextBox($x1, $y, $x2, 7);
            $this->pTextBox($x1, $y, $x2, 72); //caixa
            $y += 1;
            $texto = 'Seguro';
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
            $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'C', 0, '', false);
            $y += 6;

            //----------------
            //Responsável
            //----------------
            $x2 = ($maxW / 3)-23;
            $x1 = $x;

            // Início - Sobreposição de informações (#29711);
            $x1 += 2;
            $x2 += 2;
            // Fim - Sobreposição de informações (#29711);

            //$this->pTextBox($x1, $y, $x2, 12); //caixa
            $texto = 'Responsável';
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);

            //Rafael
            $i = 14; //Quantidade de Número Averbação que cabe no Campo;
            $contador = 0;
            $texto = '';
            $altura = $y + 4;
            /** @var \DOMNodeList $seg */
            $seg = $this->seg;
            //De inicio esse laço vai trazer 15 números de averbação, hoje esse número vai nos atender muito bem.
            //Caso no futuro alguém informe mais de 15 números, dai voltamos a mexer nesse laço ou repensar em como fazer ele.
            //E provavelmente teremos que mexer no Layout da Damdfe.
            //Laço para percorrer todas as Tags 'seg'.
            foreach ($seg as $item) {
                /** @var \DOMElement $item */
                $seg_infResp = $item->getElementsByTagName("infResp")->item(0);

                 //Esse if não vai deixar trazer mais de 15 número de averbação
                if($i < $contador) {
                    break;
                } else {
                    $altura += 4;
                    if (!empty($seg_infResp) && !empty($seg_infResp->getElementsByTagName("respSeg")->item(0)->nodeValue)) {
                        switch ($seg_infResp->getElementsByTagName("respSeg")->item(0)->nodeValue) {
                            case 1:
                                $texto = "(1) Emitente - ";
                                break;
                            case 2:
                                $texto = "(2) Contratante - ";
                            break;
                        }
                    }

                    if (!empty($seg_infResp) && !empty($seg_infResp->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
                        $texto .= $this->pFormat(
                            $seg_infResp->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                            "###.###.###/####-##"
                        );
                    } else if (!empty($seg_infResp) && !empty($seg_infResp->getElementsByTagName("CPF")->item(0)->nodeValue)) {
                        $texto .= $this->pFormat(
                            $seg_infResp->getElementsByTagName("CPF")->item(0)->nodeValue,
                            "###.###.###-##"
                        );
                    }
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                    $this->pTextBox($x1, $altura-3, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
                }
                $contador++;
            }

            //----------------
            //Seguradora
            //----------------
            $x1 += $x2;
            //$x2 = ($maxW / 2)+50;
            $x2 = ($maxW - $x1)-100;
            //$this->pTextBox($x1+5, $y, $x2, 20); //caixa

            // Início - Sobreposição de informações (#29711);
            $x1 += 2;
            $x2 += 2;
            // Fim - Sobreposição de informações (#29711);

            $texto = 'Seguradora';
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1+2, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);


            $i = 14; //Quantidade de Número Averbação que cabe no Campo;
            $contador = 0;
            $texto = '';
            $altura = $y + 4;
            /** @var \DOMNodeList $seg */
            $seg = $this->seg;
            //De inicio esse laço vai trazer 15 números de averbação, hoje esse número vai nos atender muito bem.
            //Caso no futuro alguém informe mais de 15 números, dai voltamos a mexer nesse laço ou repensar em como fazer ele.
            //E provavelmente teremos que mexer no Layout da Damdfe.
            //Laço para percorrer todas as Tags 'seg'.
            foreach($seg as $item){
                /** @var \DOMElement $item */
                $seg_infSeg = $item->getElementsByTagName("infSeg")->item(0);

                //Esse if não vai deixar trazer mais de 15 número de averbação
                if($i < $contador) {
                    break;
                } else {
                    $altura += 4;
                    if (!empty($seg_infSeg) && !empty($seg_infSeg->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
                        $texto = $this->pFormat(
                        $seg_infSeg->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                        "###.###.###/####-##"
                    );
                    } else if (!empty($seg_infSeg) && !empty($seg_infSeg->getElementsByTagName("CPF")->item(0)->nodeValue)) {
                        $texto = $this->pFormat(
                        $seg_infSeg->getElementsByTagName("CPF")->item(0)->nodeValue,
                        "###.###.###-##"
                    );
                    }
                    if (!empty($seg_infSeg) && !empty($seg_infSeg->getElementsByTagName("xSeg")->item(0)->nodeValue)) {
                        //$texto .= ' - '.$seg_infSeg->getElementsByTagName("xSeg")->item(0)->nodeValue;

                        // Início - Sobreposição de informações (#29711);
                        $texto .= ' - '. substr($seg_infSeg->getElementsByTagName("xSeg")->item(0)->nodeValue, 0, 14);
                        // Fim - Sobreposição de informações (#29711);
                    }
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                    $this->pTextBox($x1+1, $altura-3, $x2+5, 20, $texto, $aFont, 'T', 'C', 0, '', false);
                }
                $contador++;
            }
            //$y += 5; //nova linha

            //----------------
            //Número Apólice
            //----------------
            $x1 += $x2;
            $x2 = ($maxW / 2)-40;
            //$this->pTextBox($x1, $y, $x2, 12); //caixa
            $texto = 'Número Apólice';

            // Início - Sobreposição de informações (#29711);
            $x1 -= 4;
            $x2 -= 4;
            // Fim - Sobreposição de informações (#29711);

            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1+15, $y, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);

            $i= 14; //Quantidade de Número Averbação que cabe no Campo;
            $contador = 0;
            $texto = '';
            $altura = $y + 4;
            /** @var \DOMNodeList $seg */
            $seg = $this->seg;
            //De inicio esse laço vai trazer 15 números de averbação, hoje esse número vai nos atender muito bem.
            //Caso no futuro alguém informe mais de 15 números, dai voltamos a mexer nesse laço ou repensar em como fazer ele.
            //E provavelmente teremos que mexer no Layout da Damdfe.
            //Laço para percorrer todas as Tags 'seg'.
            foreach ($seg as $item) {
                /** @var \DOMElement $item */
                 //Esse if não vai deixar trazer mais de 15 número de averbação
                if($i < $contador) {
                    break;
                } else {
                    if (isset($item->getElementsByTagName("nApol")->item(0)->nodeValue)) {
                        $altura += 4;
                        $texto = $item->getElementsByTagName("nApol")->item(0)->nodeValue;
                        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                        $this->pTextBox($x1, $altura-3, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
                    } else {
                        $altura += 4;
                        $texto = '';
                        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                        $this->pTextBox($x1, $altura-3, $x2, 10, $texto, $aFont, 'T', 'C', 0, '', false);
                    }
                }  
                $contador++;  
            }

            //----------------
            //Número Averbação
            //----------------
            $x1 += $x;
            $x2 = ($maxW /2)+380;
            $y -= 1;
            //$this->pTextBox($x1-161, $y, $x2, 66); //caixa
            $texto = 'Número Averbação';
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $this->pTextBox($x1+40, $y+1, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
            //$y += 12; //nova linha

            //Rafael
            $i = 14; //Quantidade de Número Averbação que cabe no Campo;
            $contador = 0;
            $texto = '';
            $altura = $y + 4;
            $y += 12;
            /** @var \DOMNodeList $seg */
            $seg = $this->seg;
            //De inicio esse laço vai trazer 15 números de averbação, hoje esse número vai nos atender muito bem.
            //Caso no futuro alguém informe mais de 15 números, dai voltamos a mexer nesse laço ou repensar em como fazer ele.
            //E provavelmente teremos que mexer no Layout da Damdfe.
            //Laço para percorrer todas as Tags 'seg'.
            foreach ($seg as $item) {
                /** @var \DOMElement $item */
                //Esse if não vai deixar trazer mais de 15 número de averbação
                if($i < $contador) {
                    break;
                } else {
                    if (isset($item->getElementsByTagName("nAver")->item(0)->nodeValue)) {
                        $altura += 4;
                        $texto = $item->getElementsByTagName("nAver")->item(0)->nodeValue;
                        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                        $this->pTextBox($x1-170, $altura-2, $x2, 12, $texto, $aFont, 'T', 'C', 0, '', false);
                    } else {
                        $altura += 4;
                        $texto = '';
                        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                        $this->pTextBox($x1-170, $altura-2, $x2, 12, $texto, $aFont, 'T', 'C', 0, '', false);
                    }
                }
                $contador++;
            }
            $y += 12;
            $y += 3;
        }
        return $y;
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
     * Para adicionar mensagens, como o "Encerramento", etc
     * @param string $newTextoMensagem
     * @return none
     * Author: VALTER
     */
    public function setTextoMensagem($newTextoMensagem)
    {
        $this->TextoMensagem = $newTextoMensagem;
        
    }

    /**
     * qrCode
     * @author Valter
     */
    protected function pQRDANFE($y = 0)
    {
        $margemInterna = 3; //2;
        $maxW = $this->wPrint;
        $w = ($maxW * 1) + 4;
        $barcode = new Barcode();
        $bobj = $barcode->getBarcodeObj(
            'QRCODE,M',
            $this->qrCodMDFe,
            -4,
            -4,
            'black',
            array(-2, -2, -2, -2)
        )->setBackgroundColor('white');
        $qrcode = $bobj->getPngData();
        $wQr = 25;
        $hQr = 25;
        $yQr = ($y + $margemInterna);
        $xQr = 160;
        // prepare a base64 encoded "data url"
        $pic = 'data://text/plain;base64,' . base64_encode($qrcode);
        $info = getimagesize($pic);
        $link = $this->qrCodMDFe;
        $this->pdf->image($pic, $xQr, $yQr, $wQr, $hQr, 'PNG', $link);
        //return ($yQr + $margemInterna);
    }

    /**
     * footerMDFe
     *
     * @param float $x
     * @param float $y
     */
    private function footerMDFe($x, $y)
    {
        $maxW = $this->wPrint;
        $x2 = $maxW;
        $this->pTextBox($x, $y+40, $x2, 20);
        $texto = 'Observação
        '.$this->infCpl;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $this->pTextBox($x, $y+40, $x2, 8, $texto, $aFont, 'T', 'L', 0, '', false);
        /* //VALTER
        $y = $this->hPrint -4;
        $texto = "Impresso em  ". date('d/m/Y   H:i:s');
        $w = $this->wPrint-4;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'I');
        $this->pTextBox($x, $y, $w, 4, $texto, $aFont, 'T', 'L', 0, '');
        */
        $texto = $this->TextoRodape;
        $y = $this->hPrint - 4;
        $w = $this->wPrint - 4;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w, 4, $texto, $aFont, 'T', 'R', 0, '');
    }//fim footerCCe
    /**
     * printMDFe
     *
     * @param string $nome
     * @param string $destino
     * @param string $printer
     * @return string
     */
    public function printMDFe($nome = '', $destino = 'I', $printer = '')
    {
        //monta
        $command = '';
        if ($nome == '') {
            $file = $this->pdfDir.'mdfe.pdf';
        } else {
            $file = $this->pdfDir.$nome;
        }
        if ($destino != 'I' && $destino != 'S' && $destino != 'F') {
            $destino = 'I';
        }
        if ($printer != '') {
            $command = "-P $printer";
        }

        $this->buildMDFe();
        $arq = $this->pdf->Output($file, $destino);
        if ($destino == 'S' && $command != '') {
            //aqui pode entrar a rotina de impressão direta
            $command = "lpr $command $file";
            system($command, $retorno);
        }

        return $arq;
    }//fim printMDFe

    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }

    /**
     * zUnidadeCarga
     * Converte a imformação de peso contida no MDFe
     * Author: Valter
     *
     * @param  string $c unidade de medida extraida da MDFe
     * @return string
     */
    private function zUnidadeCarga()
    {
        switch ($this->cUnid) {
            case '01':
                $r = '(Kg)';
                break;
            case '02':
                $r = '(Ton)';
                break;
            default:
                $r = '';
        }
        return $r;
    } //fim unidade
}
