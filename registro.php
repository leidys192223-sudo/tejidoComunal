<?php
session_start();
require_once "conexion.php";

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

// Si la persona ya inició sesión, entra a su panel.
if (!empty($_SESSION["id_usuario"])) {
    $destino = (int)($_SESSION["id_rol"] ?? 2) === 1
        ? "dashboard.php"
        : "usuario.php";

    header("Location: " . $destino);
    exit;
}

// Protección del formulario.
if (empty($_SESSION["csrf_registro"])) {
    $_SESSION["csrf_registro"] = bin2hex(random_bytes(32));
}

$mensaje = "";
$sectores = [];

$datos = [
    "nombre_completo" => "",
    "fecha_nacimiento" => "",
    "contacto" => "",
    "direccion_hogar" => "",
    "id_sector" => "",
    "email" => "",
    "tipo_cuenta" => "usuario"
];

// Obtener los sectores de la base de datos.
$resultado = $conn->query(
    "SELECT id_sector, nombre_sector
     FROM sectores
     ORDER BY nombre_sector"
);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $sectores[] = $fila;
    }
}

// Procesar el registro.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim((string)($_POST[$campo] ?? $valor));
    }

    $password = (string)($_POST["password"] ?? "");
    $codigo_admin = trim((string)($_POST["codigo_admin"] ?? ""));
    $acepta = isset($_POST["acepta_datos"]);

    $token_valido = hash_equals(
        $_SESSION["csrf_registro"],
        (string)($_POST["csrf_token"] ?? "")
    );

    $sector = filter_var(
        $datos["id_sector"],
        FILTER_VALIDATE_INT
    );

    $sector_existe = false;

    foreach ($sectores as $fila) {
        if (
            $sector !== false &&
            (int)$fila["id_sector"] === (int)$sector
        ) {
            $sector_existe = true;
            break;
        }
    }

    $es_admin = $datos["tipo_cuenta"] === "administrador";

    // Solo se permite registrar administradores
    // con un código configurado en el servidor.
    $codigo_configurado = getenv("CODIGO_ADMIN_TEJIDO");
    $autorizado_admin = false;

    if (
        $es_admin &&
        is_string($codigo_configurado) &&
        strlen($codigo_configurado) >= 12 &&
        $codigo_admin !== ""
    ) {
        $autorizado_admin = hash_equals(
            $codigo_configurado,
            $codigo_admin
        );
    }

    $fecha_valida = DateTime::createFromFormat(
        "!Y-m-d",
        $datos["fecha_nacimiento"]
    );

    if (!$token_valido) {

        $mensaje = "La sesión del formulario venció. Recarga la página.";

    } elseif (
        $datos["nombre_completo"] === "" ||
        $datos["fecha_nacimiento"] === "" ||
        $datos["contacto"] === "" ||
        $datos["direccion_hogar"] === "" ||
        $datos["email"] === "" ||
        $password === "" ||
        !$sector_existe
    ) {

        $mensaje = "Por favor, completa todos los campos.";

    } elseif (
        !in_array(
            $datos["tipo_cuenta"],
            ["usuario", "administrador"],
            true
        )
    ) {

        $mensaje = "Selecciona un tipo de cuenta válido.";

    } elseif (
        !filter_var($datos["email"], FILTER_VALIDATE_EMAIL)
    ) {

        $mensaje = "Escribe un correo electrónico válido.";

    } elseif (strlen($password) < 8) {

        $mensaje = "La contraseña debe tener al menos 8 caracteres.";

    } elseif (
        !$fecha_valida ||
        $fecha_valida->format("Y-m-d") !== $datos["fecha_nacimiento"] ||
        $datos["fecha_nacimiento"] > date("Y-m-d")
    ) {

        $mensaje = "Escribe una fecha de nacimiento válida.";

    } elseif (!$acepta) {

        $mensaje = "Debes aceptar el tratamiento de datos personales.";

    } elseif ($es_admin && !$autorizado_admin) {

        $mensaje = "No se pudo autorizar el registro de administrador.";

    } else {

        // Verificar si el correo ya está registrado.
        $consulta = $conn->prepare(
            "SELECT id_usuario
             FROM usuarios
             WHERE email = ?
             LIMIT 1"
        );

        $consulta->bind_param("s", $datos["email"]);
        $consulta->execute();
        $consulta->store_result();

        if ($consulta->num_rows > 0) {

            $mensaje = "Este correo electrónico ya está registrado.";

        } else {

            $clave_segura = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $rol = $es_admin ? 1 : 2;

            $insertar = $conn->prepare(
                "INSERT INTO usuarios
                (
                    nombre_completo,
                    fecha_nacimiento,
                    contacto,
                    direccion_hogar,
                    email,
                    password,
                    id_sector,
                    id_rol
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $insertar->bind_param(
                "ssssssii",
                $datos["nombre_completo"],
                $datos["fecha_nacimiento"],
                $datos["contacto"],
                $datos["direccion_hogar"],
                $datos["email"],
                $clave_segura,
                $sector,
                $rol
            );

            if ($insertar->execute()) {

                // Obtener el ID del usuario recién creado.
                $nuevo_id = (int)$conn->insert_id;

                // Iniciar sesión automáticamente.
                session_regenerate_id(true);

                $_SESSION["id_usuario"] = $nuevo_id;
                $_SESSION["nombre_completo"] = $datos["nombre_completo"];
                $_SESSION["email"] = $datos["email"];
                $_SESSION["id_rol"] = $rol;

                unset($_SESSION["csrf_registro"]);

                $insertar->close();
                $consulta->close();

                // Enviar al panel correspondiente.
                if ($rol === 1) {
                    header("Location: dashboard.php");
                } else {
                    header("Location: usuario.php");
                }

                exit;

            } else {

                $mensaje = "No se pudo crear la cuenta. Inténtalo nuevamente.";
            }

            $insertar->close();
        }

        $consulta->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Crear cuenta | Tejido Comunal</title>

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
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    border: 1px solid #c9dbc4;
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
    max-width: 570px;
    margin: 25px auto 60px;
    padding: 0 16px;
}

.formulario {
    background: rgba(255, 255, 255, 0.97);
    padding: 35px;
    border-radius: 20px;
    box-shadow: 0 15px 45px rgba(27, 58, 34, 0.15);
}

h1 {
    text-align: center;
    color: #173f2b;
    font-size: 30px;
    margin: 0 0 10px;
}

.subtitulo {
    text-align: center;
    color: #718075;
    line-height: 1.6;
    font-size: 14px;
    margin-bottom: 28px;
}

.etiqueta-tipo {
    display: block;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 12px;
}

.opciones {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 25px;
}

.opcion {
    position: relative;
}

.opcion input {
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
}

.opcion span {
    display: inline-block;
    background: #171717;
    color: white;
    padding: 11px 21px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    border: 2px solid transparent;
    transition: 0.2s;
}

.opcion input:checked + span {
    background: #000;
    border-color: #8bbca0;
    box-shadow: 0 0 0 2px #d7e9dd;
}

.opcion input:focus-visible + span {
    outline: 3px solid #76ac8b;
    outline-offset: 3px;
}

label.campo {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin: 16px 0 7px;
}

input:not([type="radio"]):not([type="checkbox"]),
select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #cbd9cf;
    border-radius: 9px;
    font-size: 14px;
    background: white;
    color: #26382d;
}

input:focus,
select:focus {
    outline: 2px solid #8dbca0;
    outline-offset: 1px;
}

.admin-campo {
    background: #f4f7f1;
    padding: 15px;
    border-radius: 12px;
    margin: 18px 0;
    border: 1px solid #dce8da;
}

.admin-campo p {
    color: #667669;
    font-size: 12px;
    line-height: 1.5;
    margin-bottom: 4px;
}

.aceptacion {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin: 22px 0;
    font-size: 13px;
    line-height: 1.6;
}

.aceptacion input {
    margin-top: 3px;
    accent-color: #174e33;
}

.boton-registro {
    width: 100%;
    border: none;
    background: #111111;
    color: white;
    padding: 15px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.boton-registro:hover {
    background: #333333;
}

.enlace {
    text-align: center;
    font-size: 14px;
    margin: 24px 0 0;
}

.enlace a {
    color: #176b45;
    font-weight: bold;
    text-decoration: none;
}

.mensaje {
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 14px;
    line-height: 1.5;
}

.error {
    background: #fce7e7;
    color: #9c2525;
}

.pie {
    text-align: center;
    font-size: 12px;
    color: #52705a;
    margin-top: 22px;
}

@media (max-width: 600px) {

    .encabezado {
        padding: 18px;
    }

    .nombre-marca {
        font-size: 18px;
    }

    .formulario {
        padding: 24px 20px;
    }

    h1 {
        font-size: 25px;
    }

    .opcion span {
        padding: 10px 15px;
    }

    .volver {
        font-size: 12px;
    }
}
</style>

</head>

<body>

<header class="encabezado">

    <a class="marca" href="login.php">

       
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

    <a class="volver" href="login.php">
        ← Iniciar sesión
    </a>

</header>

<main>

    <div class="formulario">

        <h1>Crear cuenta</h1>

        <p class="subtitulo">
            Únete a nuestra comunidad y participa
            en las iniciativas del barrio.
        </p>

        <?php if ($mensaje !== ""): ?>

            <div class="mensaje error">
                <?php echo escapar($mensaje); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="registro.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo escapar($_SESSION["csrf_registro"]); ?>"
            >

            <span class="etiqueta-tipo">
                Selecciona tu tipo de cuenta
            </span>

            <div class="opciones">

                <label class="opcion">

                    <input
                        type="radio"
                        name="tipo_cuenta"
                        value="usuario"
                        <?php echo $datos["tipo_cuenta"] !== "administrador" ? "checked" : ""; ?>
                    >

                    <span>Usuario</span>

                </label>

                <label class="opcion">

                    <input
                        type="radio"
                        name="tipo_cuenta"
                        value="administrador"
                        <?php echo $datos["tipo_cuenta"] === "administrador" ? "checked" : ""; ?>
                    >

                    <span>Administrador</span>

                </label>

            </div>

            <label class="campo" for="nombre">
                Nombre completo
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre_completo"
                value="<?php echo escapar($datos["nombre_completo"]); ?>"
                autocomplete="name"
                required
            >

            <label class="campo" for="fecha">
                Fecha de nacimiento
            </label>

            <input
                type="date"
                id="fecha"
                name="fecha_nacimiento"
                value="<?php echo escapar($datos["fecha_nacimiento"]); ?>"
                required
            >

            <label class="campo" for="contacto">
                Número de contacto
            </label>

            <input
                type="tel"
                id="contacto"
                name="contacto"
                value="<?php echo escapar($datos["contacto"]); ?>"
                required
            >

            <label class="campo" for="direccion">
                Dirección del hogar
            </label>

            <input
                type="text"
                id="direccion"
                name="direccion_hogar"
                value="<?php echo escapar($datos["direccion_hogar"]); ?>"
                required
            >

            <label class="campo" for="sector">
                Sector
            </label>

            <select id="sector" name="id_sector" required>

                <option value="">
                    Selecciona tu sector
                </option>

                <?php foreach ($sectores as $fila): ?>

                    <option
                        value="<?php echo (int)$fila["id_sector"]; ?>"
                        <?php echo (string)$datos["id_sector"] === (string)$fila["id_sector"] ? "selected" : ""; ?>
                    >
                        <?php echo escapar($fila["nombre_sector"]); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label class="campo" for="email">
                Correo electrónico
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo escapar($datos["email"]); ?>"
                autocomplete="email"
                required
            >

            <label class="campo" for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <div class="admin-campo" id="campoAdmin" hidden>

                <label class="campo" for="codigo_admin">
                    Código de autorización
                </label>

                <input
                    type="password"
                    id="codigo_admin"
                    name="codigo_admin"
                    autocomplete="off"
                    disabled
                >

                <p>
                    Solo las personas autorizadas por la Junta
                    pueden crear una cuenta de administrador.
                </p>

            </div>

            <div class="aceptacion">

                <input
                    type="checkbox"
                    id="acepta"
                    name="acepta_datos"
                    required
                >

                <label for="acepta">
                    Acepto el tratamiento de mis datos personales
                    para la gestión de mi participación comunitaria.
                </label>

            </div>

            <button class="boton-registro" type="submit">
                Registrarme
            </button>

        </form>

        <p class="enlace">
            ¿Ya tienes cuenta?
            <a href="login.php">Iniciar sesión</a>
        </p>

    </div>

    <p class="pie">
        Tejido Comunal · Barrio Las Margaritas
    </p>

</main>

<script>
const opciones = document.querySelectorAll(
    'input[name="tipo_cuenta"]'
);

const campoAdmin = document.getElementById("campoAdmin");
const codigoAdmin = document.getElementById("codigo_admin");

function actualizarTipoCuenta() {

    const seleccionado = document.querySelector(
        'input[name="tipo_cuenta"]:checked'
    );

    const administrador =
        seleccionado && seleccionado.value === "administrador";

    campoAdmin.hidden = !administrador;
    codigoAdmin.disabled = !administrador;
    codigoAdmin.required = administrador;

    if (!administrador) {
        codigoAdmin.value = "";
    }
}

opciones.forEach(function(opcion) {
    opcion.addEventListener("change", actualizarTipoCuenta);
});

actualizarTipoCuenta();
</script>

</body>
</html>