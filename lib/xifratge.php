<?php

/**
 * Xifratge simètric dels secrets desats a la BD (AES-256-GCM, OpenSSL).
 * La clau viu a config/local.php → 'clau_xifratge' (32 bytes en base64),
 * mai a la BD: si algú s'endú un volcat de la BD, no pot llegir-ne els secrets.
 *
 * Format: "v1:" + base64(iv[12] . tag[16] . text xifrat).
 */

const XIFRATGE_PREFIX = 'v1:';

function clauXifratge(): string
{
    $clau = base64_decode((string) config('clau_xifratge', ''), true);
    if ($clau === false || strlen($clau) !== 32) {
        throw new RuntimeException(
            "Falta la clau de xifratge (config/local.php → 'clau_xifratge', 32 bytes en base64)."
        );
    }

    return $clau;
}

function xifra(string $text): string
{
    $iv = random_bytes(12);
    $tag = '';
    $xifrat = openssl_encrypt($text, 'aes-256-gcm', clauXifratge(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($xifrat === false) {
        throw new RuntimeException("No s'ha pogut xifrar el valor.");
    }

    return XIFRATGE_PREFIX . base64_encode($iv . $tag . $xifrat);
}

/**
 * @throws RuntimeException si el valor s'ha manipulat o la clau no és la correcta
 */
function desxifra(string $valor): string
{
    if (!str_starts_with($valor, XIFRATGE_PREFIX)) {
        throw new RuntimeException('Format de valor xifrat desconegut.');
    }
    $dades = base64_decode(substr($valor, strlen(XIFRATGE_PREFIX)), true);
    if ($dades === false || strlen($dades) < 29) {
        throw new RuntimeException('Valor xifrat malmès.');
    }

    $text = openssl_decrypt(
        substr($dades, 28),
        'aes-256-gcm',
        clauXifratge(),
        OPENSSL_RAW_DATA,
        substr($dades, 0, 12),
        substr($dades, 12, 16)
    );
    if ($text === false) {
        throw new RuntimeException("No s'ha pogut desxifrar el valor (clau incorrecta o dada manipulada).");
    }

    return $text;
}
