<?php

namespace App\Service;

/**
 * Valida a data opcional informada no PDV para lançar/concluir uma venda "esquecida".
 *
 * Regra: só aceita ONTEM ou HOJE (formato Y-m-d).
 *  - vazio ou HOJE  => devolve null (fluxo normal, o sistema usa NOW() como sempre)
 *  - ONTEM          => devolve ontem + horário atual
 *  - qualquer outra => lança \InvalidArgumentException
 */
final class DataVendaRetroativa
{
    public static function resolver(?string $valor): ?\DateTime
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $dia = \DateTime::createFromFormat('!Y-m-d', $valor);
        $erros = \DateTime::getLastErrors();
        $invalida = $dia === false
            || ($erros && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))
            || $dia->format('Y-m-d') !== $valor;

        if ($invalida) {
            throw new \InvalidArgumentException('Data da venda inválida.');
        }

        $hoje  = new \DateTime('today');
        $ontem = (new \DateTime('today'))->modify('-1 day');

        if ($dia < $ontem || $dia > $hoje) {
            throw new \InvalidArgumentException('A data da venda só pode ser a de ontem ou a de hoje.');
        }

        if ($dia == $hoje) {
            return null;
        }

        $agora = new \DateTime();

        return $dia->setTime((int) $agora->format('H'), (int) $agora->format('i'), (int) $agora->format('s'));
    }
}
