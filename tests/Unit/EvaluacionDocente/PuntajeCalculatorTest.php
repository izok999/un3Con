<?php

namespace Tests\Unit\EvaluacionDocente;

use App\Services\EvaluacionDocente\PuntajeCalculator;
use PHPUnit\Framework\TestCase;

class PuntajeCalculatorTest extends TestCase
{
    public function test_calcula_el_promedio_ponderado_con_criterios_numericos(): void
    {
        $calculator = new PuntajeCalculator;

        $puntaje = $calculator->calcular([
            [
                'tipo_respuesta' => 'escala',
                'peso' => 20,
                'valor_numerico' => 4,
            ],
            [
                'tipo_respuesta' => 'escala',
                'peso' => 40,
                'valor_numerico' => 5,
            ],
            [
                'tipo_respuesta' => 'mixto',
                'peso' => 40,
                'valor_numerico' => 3,
            ],
        ]);

        $this->assertSame(4.0, $puntaje);
    }

    public function test_ignora_criterios_sin_valor_numerico_en_el_calculo(): void
    {
        $calculator = new PuntajeCalculator;

        $puntaje = $calculator->calcular([
            [
                'tipo_respuesta' => 'texto',
                'peso' => 50,
                'valor_numerico' => null,
            ],
            [
                'tipo_respuesta' => 'escala',
                'peso' => 50,
                'valor_numerico' => 5,
            ],
        ]);

        $this->assertSame(5.0, $puntaje);
    }

    public function test_devuelve_cero_si_no_hay_criterios_numericos_calculables(): void
    {
        $calculator = new PuntajeCalculator;

        $puntaje = $calculator->calcular([
            [
                'tipo_respuesta' => 'texto',
                'peso' => 100,
                'valor_numerico' => null,
            ],
        ]);

        $this->assertSame(0.0, $puntaje);
    }
}
