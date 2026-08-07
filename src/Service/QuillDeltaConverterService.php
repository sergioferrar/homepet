<?php

namespace App\Service;

class QuillDeltaConverterService
{
    /**
     * Converte um JSON Delta do Quill para HTML formatado
     * 
     * @param string|null $deltaJson JSON do Quill Delta
     * @return string HTML formatado
     */
    public function deltaToHtml(?string $deltaJson): string
    {
        if (!$deltaJson) {
            return '';
        }

        try {
            $delta = json_decode($deltaJson, true);
            if (!isset($delta['ops']) || !is_array($delta['ops'])) {
                return htmlspecialchars($deltaJson, ENT_QUOTES);
            }

            $html = '';
            $listActive = false;
            $listType = null;

            foreach ($delta['ops'] as $op) {
                $insert = $op['insert'] ?? '';
                $attributes = $op['attributes'] ?? [];

                // Processar quebras de linha
                if ($insert === "\n") {
                    // Se estamos em uma lista e a próxima operação não faz parte da lista, fechar
                    if ($listActive && empty($attributes['list'])) {
                        $html .= $this->getListCloseTag($listType);
                        $listActive = false;
                        $listType = null;
                    }
                    
                    if (!isset($attributes['list'])) {
                        $html .= '</p>';
                        $html .= '<p style="margin:0.5rem 0; line-height:1.6;">';
                    }
                    continue;
                }

                // Processar listas
                if (isset($attributes['list'])) {
                    if (!$listActive || $listType !== $attributes['list']) {
                        if ($listActive) {
                            $html .= $this->getListCloseTag($listType);
                        }
                        $listType = $attributes['list'];
                        $listActive = true;
                        $html .= $this->getListOpenTag($listType);
                    }
                    
                    $text = htmlspecialchars($insert, ENT_QUOTES);
                    $html .= '<li style="margin-left:1.5rem; margin-bottom:0.5rem;">' . $this->applyTextFormatting($text, $attributes) . '</li>';
                    continue;
                }

                // Fechar lista se não há mais operações com lista
                if ($listActive) {
                    $html .= $this->getListCloseTag($listType);
                    $listActive = false;
                    $listType = null;
                }

                // Processar texto normal
                $text = htmlspecialchars($insert, ENT_QUOTES);
                if (strlen($text) > 0) {
                    $html .= $this->applyTextFormatting($text, $attributes);
                }
            }

            // Fechar lista se ainda estiver aberta
            if ($listActive) {
                $html .= $this->getListCloseTag($listType);
            }

            // Fechar parágrafo final
            if (strpos($html, '<p') !== false && strpos($html, '</p>') === false) {
                $html .= '</p>';
            }

            return $html;
        } catch (\Exception $e) {
            return htmlspecialchars($deltaJson, ENT_QUOTES);
        }
    }

    /**
     * Aplica formatação de texto (bold, italic, etc)
     * 
     * @param string $text Texto a formatar
     * @param array $attributes Atributos de formatação
     * @return string Texto formatado
     */
    private function applyTextFormatting(string $text, array $attributes = []): string
    {
        if (isset($attributes['bold']) && $attributes['bold']) {
            $text = '<strong style="font-weight:700;">' . $text . '</strong>';
        }

        if (isset($attributes['italic']) && $attributes['italic']) {
            $text = '<em style="font-style:italic;">' . $text . '</em>';
        }

        if (isset($attributes['underline']) && $attributes['underline']) {
            $text = '<u style="text-decoration:underline;">' . $text . '</u>';
        }

        if (isset($attributes['strike']) && $attributes['strike']) {
            $text = '<s style="text-decoration:line-through;">' . $text . '</s>';
        }

        if (isset($attributes['link']) && $attributes['link']) {
            $url = htmlspecialchars($attributes['link'], ENT_QUOTES);
            $text = '<a href="' . $url . '" target="_blank" style="color:#0066cc; text-decoration:underline;">' . $text . '</a>';
        }

        // Espaçamento padrão
        if (strpos($text, '<') === false) {
            // Se não tem tags, envolver em span padrão para manter coerência
            return '<span style="line-height:1.6;">' . $text . '</span>';
        }

        return $text;
    }

    /**
     * Retorna a tag de abertura para uma lista
     * 
     * @param string $listType Tipo de lista (bullet ou ordered)
     * @return string Tag HTML
     */
    private function getListOpenTag(string $listType): string
    {
        if ($listType === 'ordered') {
            return '<ol style="margin:0.5rem 0; padding-left:1.5rem;">';
        }
        return '<ul style="margin:0.5rem 0; padding-left:0; list-style:none;">';
    }

    /**
     * Retorna a tag de fechamento para uma lista
     * 
     * @param string $listType Tipo de lista (bullet ou ordered)
     * @return string Tag HTML
     */
    private function getListCloseTag(string $listType): string
    {
        if ($listType === 'ordered') {
            return '</ol>';
        }
        return '</ul>';
    }

    /**
     * Converte para texto simples removendo todas as formatações
     * 
     * @param string|null $deltaJson JSON do Quill Delta
     * @return string Texto simples
     */
    public function deltaToPlainText(?string $deltaJson): string
    {
        if (!$deltaJson) {
            return '';
        }

        try {
            $delta = json_decode($deltaJson, true);
            if (!isset($delta['ops']) || !is_array($delta['ops'])) {
                return $deltaJson;
            }

            $text = '';
            foreach ($delta['ops'] as $op) {
                $text .= $op['insert'] ?? '';
            }

            return trim($text);
        } catch (\Exception $e) {
            return $deltaJson;
        }
    }
}