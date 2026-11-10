<?php

namespace NFePHP\DA\Legacy;

/**
 * Pdf com suporte a estados gráficos (ExtGState, PDF 1.4): modo de mistura e opacidade.
 *
 * Usado pelo Danfe para desenhar as marcas d'água por cima do conteúdo com mistura
 * "Multiply": no fundo branco a marca fica igual à de antes, e as faixas cinza dos títulos
 * não a escondem mais; o texto preto continua preto.
 */
class PdfTransparencia extends Pdf
{
    /**
     * @var array estados gráficos usados no documento: chave => ['params' => [...], 'n' => obj]
     */
    protected $extGStates = array();

    /**
     * Ativa o modo de mistura e a opacidade para o que for desenhado a seguir.
     *
     * @param string $modo  Normal, Multiply, Screen, Darken...
     * @param float  $alpha opacidade de 0 a 1 (traço e preenchimento)
     */
    public function setMistura($modo = 'Normal', $alpha = 1.0)
    {
        $alpha = max(0, min(1, (float) $alpha));
        $params = array(
            'BM' => '/' . preg_replace('/[^A-Za-z]/', '', $modo),
            'ca' => sprintf('%.3F', $alpha),
            'CA' => sprintf('%.3F', $alpha),
        );
        $chave = implode('|', $params);
        if (!isset($this->extGStates[$chave])) {
            $this->extGStates[$chave] = array('params' => $params, 'i' => count($this->extGStates) + 1);
        }
        if (version_compare($this->pdfVersion, '1.4', '<')) {
            $this->pdfVersion = '1.4';
        }
        $this->out(sprintf('/GS%d gs', $this->extGStates[$chave]['i']));
    }

    protected function putExtGStates()
    {
        foreach ($this->extGStates as $chave => $gs) {
            $this->newObj();
            $this->extGStates[$chave]['n'] = $this->n;
            $this->out('<</Type /ExtGState');
            foreach ($gs['params'] as $nome => $valor) {
                $this->out('/' . $nome . ' ' . $valor);
            }
            $this->out('>>');
            $this->out('endobj');
        }
    }

    protected function putResources()
    {
        $this->putExtGStates();
        parent::putResources();
    }

    protected function putResourceDict()
    {
        parent::putResourceDict();
        if (!empty($this->extGStates)) {
            $this->out('/ExtGState <<');
            foreach ($this->extGStates as $gs) {
                $this->out('/GS' . $gs['i'] . ' ' . $gs['n'] . ' 0 R');
            }
            $this->out('>>');
        }
    }
}
