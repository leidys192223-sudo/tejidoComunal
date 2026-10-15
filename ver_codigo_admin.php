<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once "conexion.php";
require_once __DIR__ . "/generador_codigo_admin.php";


// Comprobar que haya una sesión iniciada.
if (empty($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

// Verificar el rol directamente en la base de datos.
$id_usuario = (int)$_SESSION["id_usuario"];

$consulta = $conn->prepare(
    "SELECT id_rol
     FROM usuarios
     WHERE id_usuario = ?
     LIMIT 1"
);

if (!$consulta) {
    http_response_code(500);
    exit("No se pudo verificar el acceso.");
}

$consulta->bind_param("i", $id_usuario);
$consulta->execute();
$consulta->bind_result($rol);

$existe = $consulta->fetch();
$consulta->close();

if (!$existe || (int)$rol !== 1) {
    http_response_code(403);
    exit("Acceso exclusivo para administradores.");
}

// Generar el código vigente.
$codigo = codigo_administrador_actual(true);

if ($codigo === false) {
    exit("No se pudo generar el código de autorización.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Código de autorización | Tejido Comunal</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, sans-serif;
    background: #f3f8f4;
    color: #234332;
}

header {
    background: #173f2b;
    color: white;
    padding: 24px;
    text-align: center;
}

header h1 {
    font-size: 25px;
    margin: 0;
}

main {
    max-width: 530px;
    margin: 65px auto;
    padding: 0 18px;
}

.tarjeta {
    background: white;
    border-radius: 22px;
    padding: 38px 25px;
    text-align: center;
    box-shadow: 0 12px 35px rgba(23, 63, 43, .12);
}

h2 {
    color: #173f2b;
}

.codigo {
    background: #dff2e5;
    border: 2px dashed #8cbaa0;
    border-radius: 14px;
    padding: 20px 10px;
    margin: 25px 0;
    font-size: clamp(30px, 9vw, 43px);
    font-weight: bold;
    letter-spacing: 6px;
    color: #174b30;
}

p {
    line-height: 1.7;
    color: #63786b;
}

.aviso {
    background: #fff0d8;
    padding: 15px;
    border-radius: 12px;
    font-size: 14px;
}

.volver {
    display: inline-block;
    margin-top: 25px;
    padding: 13px 20px;
    border-radius: 10px;
    background: #173f2b;
    color: white;
    text-decoration: none;
}
</style>
</head>

<body>

<header>
    <h1>🌿 Tejido Comunal</h1>
</header>

<main>
    <div class="tarjeta">
        <div style="font-size:45px">🔐</div>

        <h2>Código de autorización</h2>

        <p>
            Este código permite autorizar el registro
            de nuevos administradores.
        </p>

        <div class="codigo">
            <?php echo htmlspecialchars($codigo, ENT_QUOTES, "UTF-8"); ?>
        </div>

        <div class="aviso">
            🔒 El código cambia automáticamente cada día
            a las 12:00 a. m., hora de Colombia.
        </div>

        <p>
            Compártelo únicamente con personas autorizadas
            por la Junta de Acción Comunal.
        </p>

        <a class="volver" href="dashboard.php">
            ← Volver al panel
        </a>
    </div>
</main>

</body>
</html>