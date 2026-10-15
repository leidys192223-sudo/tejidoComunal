<?php

function codigo_administrador_actual($crearClave = true)
{
    $zonaHoraria = new DateTimeZone("America/Bogota");

    $archivoClave = __DIR__ . "/clave_autorizacion_privada.php";

    if (!file_exists($archivoClave)) {
        if (!$crearClave) {
            return false;
        }

        $claveNueva = bin2hex(random_bytes(32));

        $contenido = "<?php\nreturn "
            . var_export($claveNueva, true)
            . ";\n";

        if (file_put_contents($archivoClave, $contenido, LOCK_EX) === false) {
            return false;
        }
    }

    $claveSecreta = require $archivoClave;

    if (!is_string($claveSecreta) || strlen($claveSecreta) < 32) {
        return false;
    }

    $fechaHoy = (new DateTimeImmutable(
        "now",
        $zonaHoraria
    ))->format("Y-m-d");

    $resultado = hash_hmac(
        "sha256",
        $fechaHoy,
        $claveSecreta
    );

    $numero = hexdec(substr($resultado, 0, 8)) % 1000000;

    return str_pad(
        (string)$numero,
        6,
        "0",
        STR_PAD_LEFT
    );
}