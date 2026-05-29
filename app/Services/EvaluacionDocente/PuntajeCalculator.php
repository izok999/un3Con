<?php

namespace App\Services\EvaluacionDocente;

class PuntajeCalculator
{
    /**
     * @param  array<int, array{tipo_respuesta: string, peso: int|float|string, valor_numerico: int|float|string|null}>  $criterios
     */
    public function calcular(array $criterios): float
    {
        $pesoTotal = 0.0;
        $puntajeAcumulado = 0.0;

        foreach ($criterios as $criterio) {
            if (! $this->debeIncluirseEnCalculo($criterio['tipo_respuesta'], $criterio['valor_numerico'])) {
                continue;
            }

            $peso = (float) $criterio['peso'];
            $valorNumerico = (float) $criterio['valor_numerico'];

            if ($peso <= 0) {
                continue;
            }

            $pesoTotal += $peso;
            $puntajeAcumulado += $valorNumerico * $peso;
        }

        if ($pesoTotal === 0.0) {
            return 0.0;
        }

        return round($puntajeAcumulado / $pesoTotal, 2);
    }

    protected function debeIncluirseEnCalculo(string $tipoRespuesta, int|float|string|null $valorNumerico): bool
    {
        if ($valorNumerico === null || $valorNumerico === '') {
            return false;
        }

        return in_array($tipoRespuesta, ['escala', 'mixto'], true);
    }
}
