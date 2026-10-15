<?php
session_start();

// Limpiar los datos de la sesión.
$_SESSION = [];

// Eliminar la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

// Cerrar la sesión.
session_destroy();

// Volver a la página principal.
header("Location: index.php");
exit;