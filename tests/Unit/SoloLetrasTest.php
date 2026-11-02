<?php

namespace Tests\Unit;

use App\Rules\SoloLetras;
use PHPUnit\Framework\TestCase;

class SoloLetrasTest extends TestCase
{
    private function mensaje(mixed $valor): ?string
    {
        $mensaje = null;

        (new SoloLetras)->validate('nombre', $valor, function (string $fallo) use (&$mensaje): void {
            $mensaje = $fallo;
        });

        return $mensaje;
    }

    public function test_acepta_letras_con_tildes_y_espacios(): void
    {
        $this->assertNull($this->mensaje('Juan Pérez'));
        $this->assertNull($this->mensaje('María José Núñez'));
    }

    public function test_rechaza_numeros(): void
    {
        $this->assertNotNull($this->mensaje('Juan 123'));
    }

    public function test_rechaza_signos(): void
    {
        $this->assertNotNull($this->mensaje('Juan #Pérez'));
        $this->assertNotNull($this->mensaje('Juan-Pérez'));
    }

    public function test_rechaza_valores_vacios_o_no_texto(): void
    {
        $this->assertNotNull($this->mensaje('   '));
        $this->assertNotNull($this->mensaje(['Juan']));
    }
}
