<?php

namespace App\Services\Asistencia;

/**
 * Diccionario de codigos del export del Control de Asistencia y estados derivados.
 *
 * Los codigos llegan en la misma celda que las marcaciones: una celda puede traer
 * "07:30 19:20" (marcaciones), "SR" (sin registro) o una combinacion de codigos.
 */
final class Codigos
{
    /** Mascaras de bits: permiten guardar varios codigos de un dia en un solo entero. */
    public const V   = 1;    // vacacion
    public const VDN = 2;    // vacacion de Navidad
    public const LDM = 4;    // licencia de maternidad
    public const LPM = 8;    // licencia de paternidad
    public const LR  = 16;   // licencia remunerada
    public const I   = 32;   // inasistencia
    public const SR  = 64;   // sin registro
    public const BM  = 128;  // baja medica

    public const VACACION = self::V | self::VDN;
    public const LICENCIA = self::LDM | self::LPM | self::LR | self::BM;

    /** Estados resueltos de un dia. */
    public const PRESENTE = 0;
    public const FALTA    = 1;
    public const VAC      = 2;
    public const LIC      = 3;
    public const NOLAB    = 4;

    public const BITS = [
        'V' => self::V, 'VDN' => self::VDN, 'LDM' => self::LDM, 'LPM' => self::LPM,
        'LR' => self::LR, 'BM' => self::BM, 'I' => self::I, 'SR' => self::SR,
    ];

    public const NOMBRES = [
        'V'   => 'Vacacion',
        'VDN' => 'Vacacion de Navidad',
        'LDM' => 'Licencia de maternidad',
        'LPM' => 'Licencia de paternidad',
        'LR'  => 'Licencia remunerada',
        'BM'  => 'Baja medica',
        'I'   => 'Inasistencia',
        'SR'  => 'Sin registro',
    ];

    public static function bit(string $codigo): int
    {
        return self::BITS[strtoupper($codigo)] ?? 0;
    }

    /** Descompone una mascara en la lista de codigos que la componen. */
    public static function desglose(int $mascara): array
    {
        $out = [];
        foreach (self::BITS as $nombre => $bit) {
            if ($mascara & $bit) {
                $out[] = $nombre;
            }
        }

        return $out;
    }
}
