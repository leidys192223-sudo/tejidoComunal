<?php
session_start();
require_once "conexion.php";

$error = "";
$email = "";

function escapar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

function ir_al_panel($rol) {
    if ((int)$rol === 1) {
        header("Location: dashboard.php");
    } else {
        header("Location: usuario.php");
    }
    exit;
}

if (isset($_SESSION["id_usuario"], $_SESSION["id_rol"])) {
    ir_al_panel($_SESSION["id_rol"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Por favor, completa el correo y la contraseña.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Escribe un correo electrónico válido.";
    } else {

        $sql = "SELECT id_usuario, nombre_completo, email, password, id_rol
                FROM usuarios
                WHERE LOWER(TRIM(email)) = LOWER(?)
                LIMIT 1";

        $consulta = $conn->prepare($sql);

        if (!$consulta) {
            $error = "No se pudo consultar la cuenta. Revisa la conexión.";
        } else {

            $consulta->bind_param("s", $email);

            if (!$consulta->execute()) {
                $error = "No se pudo verificar la cuenta.";
            } else {

                $consulta->store_result();

                if ($consulta->num_rows === 1) {

                    $consulta->bind_result(
                        $id_usuario,
                        $nombre_completo,
                        $correo_guardado,
                        $clave_guardada,
                        $id_rol
                    );

                    $consulta->fetch();

                    if (password_verify($password, $clave_guardada)) {

                        session_regenerate_id(true);

                        $_SESSION["id_usuario"] = (int)$id_usuario;
                        $_SESSION["nombre_completo"] = $nombre_completo;
                        $_SESSION["email"] = $correo_guardado;
                        $_SESSION["id_rol"] = (int)$id_rol;

                        $consulta->close();

                        ir_al_panel($id_rol);

                    } else {
                        $error = "La contraseña no coincide con la registrada. Comprueba que la escribiste correctamente.";
                    }

                } else {
                    $error = "No encontramos una cuenta con ese correo electrónico.";
                }
            }

            $consulta->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión | Tejido Comunal</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, sans-serif;
    color: #26382d;
    background:
        linear-gradient(
            rgba(232, 240, 225, 0.73),
            rgba(226, 236, 219, 0.83)
        ),
        url("imagenes/comunidad2.png")
        center center / cover fixed no-repeat;
}

.encabezado {
    padding: 24px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.marca {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #173f2b;
}

.logo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e8f2df;
    border: 1px solid #c9dbc4;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}

.nombre-marca {
    font-size: 23px;
    font-weight: bold;
}

.barrio {
    display: block;
    font-size: 12px;
    color: #55715e;
    margin-top: 3px;
}

.volver {
    text-decoration: none;
    color: #234d34;
    font-size: 14px;
    font-weight: bold;
}

main {
    min-height: calc(100vh - 110px);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 25px 16px 60px;
}

.tarjeta {
    width: 100%;
    max-width: 440px;
    padding: 42px 36px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 15px 45px rgba(27, 58, 34, 0.15);
}

h1 {
    color: #173f2b;
    text-align: center;
    font-size: 29px;
    margin: 0 0 12px;
}

.subtitulo {
    color: #718075;
    text-align: center;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 30px;
}

.campo {
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd9cf;
    border-radius: 9px;
    font-size: 14px;
}

input:focus {
    outline: 2px solid #8dbca0;
    outline-offset: 1px;
}

button {
    width: 100%;
    padding: 15px;
    background: #111111;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #333333;
}

.registro {
    text-align: center;
    margin-top: 25px;
    color: #65756b;
    font-size: 14px;
}

.registro a {
    color: #176b45;
    font-weight: bold;
    text-decoration: none;
}

.error {
    background: #fce7e7;
    color: #9c2525;
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.5;
}

@media (max-width: 480px) {
    .encabezado {
        padding: 18px;
    }

    .nombre-marca {
        font-size: 18px;
    }

    .tarjeta {
        padding: 32px 22px;
    }

    h1 {
        font-size: 25px;
    }

    .volver {
        font-size: 12px;
    }
}
</style>  

<style>
/* FONDO ANIMADO DE TEJIDO COMUNAL */

html {
    min-height: 100%;
    background: #e8f1e8;
}

body {
    min-height: 100vh;
    background: transparent !important;
    position: relative;
    isolation: isolate;
}

/* Contenedor de las fotografías */
.fondo-login {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: -1;
    background: #e8f1e8;
    pointer-events: none;
}

/* Fotografías superpuestas */
.fondo-login img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    opacity: 0;
    transition: opacity 1.2s ease-in-out;
}

/* Fotografía visible */
.fondo-login img.activa {
    opacity: 1;
}

/* Velo verde para mantener legible el formulario */
.fondo-login::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(235, 246, 232, 0.58),
        rgba(220, 239, 222, 0.48)
    );
    pointer-events: none;
}

/* Adaptación para celulares */
@media (max-width: 600px) {
    .fondo-login img {
        object-position: center;
    }
}
</style>


</head>

<body>


<div class="fondo-login" aria-hidden="true">
    <img src="imagenes/jac1.jpg" alt="" class="activa">
    <img src="imagenes/jac2.jpg" alt="">
    <img src="imagenes/jac3.jpg" alt="">
    <img src="imagenes/jac4.jpg" alt="">
</div>

<header class="encabezado">

    <a class="marca" href="index.php">
        
<div class="logo" style="background: transparent; border: none; width: 58px; height: 58px; padding: 0;">
    <img
        src="imagenes/logo_comunal.png"
        alt="Logo de la Junta de Acción Comunal"
        style="width: 100%; height: 100%; object-fit: contain; display: block;"
    >
</div>

        <div>
            <span class="nombre-marca">Tejido Comunal</span>
            <span class="barrio">Barrio Las Margaritas</span>
        </div>
    </a>

    <a class="volver" href="index.php">
        ← Volver al inicio
    </a>

</header>

<main>
    <section class="tarjeta">

        <h1>Bienvenido de nuevo</h1>

        <p class="subtitulo">
            Ingresa a tu cuenta comunitaria
        </p>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?php echo escapar($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="campo">
                <label for="email">Correo electrónico</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="ejemplo@correo.com"
                    value="<?php echo escapar($email); ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">Iniciar sesión</button>

        </form>

        <p class="registro">
            ¿No tienes cuenta?
            <a href="registro.php">Regístrate aquí</a>
        </p>

    </section>
</main>

</body>
</html>