<?php
session_start();

require_once __DIR__ . "/conexion.php";

date_default_timezone_set("America/Bogota");

// SEGURIDAD: SOLO ADMINISTRADORES
if (empty($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

$idUsuario = (int) $_SESSION["id_usuario"];

$verificar = $conn->prepare(
    "SELECT id_rol FROM usuarios
     WHERE id_usuario = ? LIMIT 1"
);

if (!$verificar) {
    exit("No fue posible verificar el acceso.");
}

$verificar->bind_param("i", $idUsuario);
$verificar->execute();
$verificar->bind_result($rolReal);

$encontrado = $verificar->fetch();
$verificar->close();

if (!$encontrado || (int)$rolReal !== 1) {
    http_response_code(403);
    exit("Acceso exclusivo para administradores.");
}

function limpiarTexto($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// PROTECCION DE FORMULARIOS
if (empty($_SESSION["csrf_asistencias"])) {
    $_SESSION["csrf_asistencias"] = bin2hex(random_bytes(32));
}

// CONSULTAR CONTROL DE LA REUNION
function consultarEstado($conn, $idActividad) {
    $consulta = $conn->prepare(
        "SELECT id_control, estado, fecha_inicio, fecha_fin
         FROM control_asistencias
         WHERE id_actividad = ?
         ORDER BY id_control DESC
         LIMIT 1"
    );

    $consulta->bind_param("i", $idActividad);
    $consulta->execute();

    $resultado = $consulta->get_result();
    $registro = $resultado->fetch_assoc();

    $consulta->close();

    return $registro ?: null;
}

// CARGAR REUNIONES REALES


/* GUARDAR NUEVA REUNIÓN DESDE ASISTENCIAS */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["accion"] ?? "") === "crear_reunion"
) {
    $token = (string)($_POST["token"] ?? "");

    $tituloNuevo = trim($_POST["titulo_reunion"] ?? "");
    $descripcionNueva = trim($_POST["descripcion_reunion"] ?? "");
    $fechaNueva = trim($_POST["fecha_reunion"] ?? "");
    $horaNueva = trim($_POST["hora_reunion"] ?? "");
    $lugarNuevo = trim($_POST["lugar_reunion"] ?? "");

    $fechaComprobada = DateTime::createFromFormat(
        "!Y-m-d",
        $fechaNueva
    );

    $fechaCorrecta = $fechaComprobada &&
        $fechaComprobada->format("Y-m-d") === $fechaNueva;

    $horaCorrecta = preg_match(
        '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',
        $horaNueva
    );

    if (
        !hash_equals($_SESSION["csrf_asistencias"], $token) ||
        $tituloNuevo === "" ||
        strlen($tituloNuevo) > 150 ||
        $descripcionNueva === "" ||
        !$fechaCorrecta ||
        !$horaCorrecta ||
        $lugarNuevo === "" ||
        strlen($lugarNuevo) > 150
    ) {
        $_SESSION["mensaje_reunion_nueva"] =
            "Revisa los datos de la reunión e inténtalo nuevamente.";

        header("Location: control_asistencias.php");
        exit;
    }

    $guardar = $conn->prepare(
        "INSERT INTO actividades
        (
            titulo,
            descripcion,
            fecha,
            hora,
            lugar,
            id_usuario,
            tipo,
            estado
        )
        VALUES (?, ?, ?, ?, ?, ?, 'reunion', 'programada')"
    );

    if (!$guardar) {
        $_SESSION["mensaje_reunion_nueva"] =
            "No se pudo preparar el registro de la reunión.";

        header("Location: control_asistencias.php");
        exit;
    }

    $guardar->bind_param(
        "sssssi",
        $tituloNuevo,
        $descripcionNueva,
        $fechaNueva,
        $horaNueva,
        $lugarNuevo,
        $idUsuario
    );

    $guardadoCorrectamente = $guardar->execute();

    $idNuevaReunion = $conn->insert_id;

    $guardar->close();

    if ($guardadoCorrectamente) {
        $_SESSION["mensaje_reunion_nueva"] =
            "¡La reunión se guardó correctamente!";

        header(
            "Location: control_asistencias.php?reunion=" .
            $idNuevaReunion
        );
        exit;
    }

    $_SESSION["mensaje_reunion_nueva"] =
        "No se pudo guardar la reunión.";

    header("Location: control_asistencias.php");
    exit;
}

/* FIN DEL REGISTRO DE REUNIONES */

$reuniones = [];

$resultado = $conn->query(
    "SELECT id_actividad, titulo, fecha, hora, lugar
     FROM actividades
     WHERE tipo = 'reunion'
     ORDER BY fecha DESC, hora DESC"
);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $reuniones[(int)$fila["id_actividad"]] = $fila;
    }

    $resultado->free();
}

// REUNION SELECCIONADA
$idActividad = (int)(
    $_POST["id_actividad"] ??
    $_GET["reunion"] ??
    0
);

if ($idActividad === 0 && !empty($reuniones)) {
    $idActividad = (int)array_key_first($reuniones);
}

$mensaje = "";
$tipoMensaje = "";

// INICIAR O FINALIZAR ASISTENCIA
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = (string)($_POST["token"] ?? "");
    $accion = (string)($_POST["accion"] ?? "");

    if (!hash_equals(
        $_SESSION["csrf_asistencias"],
        $tokenRecibido
    )) {

        $mensaje = "Solicitud no válida.";
        $tipoMensaje = "error";

    } elseif (!isset($reuniones[$idActividad])) {

        $mensaje = "Selecciona una reunión válida.";
        $tipoMensaje = "error";

    } else {

        $registroAnterior = consultarEstado(
            $conn,
            $idActividad
        );

        $estadoAnterior =
            $registroAnterior["estado"] ?? "sin_iniciar";

        // INICIAR
        if ($accion === "iniciar") {

            if ($estadoAnterior !== "sin_iniciar") {

                $mensaje = "Esta asistencia ya se inició o finalizó.";
                $tipoMensaje = "aviso";

            } else {

                $secreto = bin2hex(random_bytes(32));

                if ($registroAnterior) {

                    $idControl = (int)$registroAnterior["id_control"];

                    $consulta = $conn->prepare(
                        "UPDATE control_asistencias
                         SET estado = 'en_curso',
                             fecha_inicio = NOW(),
                             fecha_fin = NULL,
                             secreto_qr = ?
                         WHERE id_control = ?
                         AND estado = 'sin_iniciar'"
                    );

                    $consulta->bind_param(
                        "si",
                        $secreto,
                        $idControl
                    );

                } else {

                    $consulta = $conn->prepare(
                        "INSERT INTO control_asistencias
                         (
                            id_actividad,
                            estado,
                            fecha_inicio,
                            secreto_qr
                         )
                         VALUES (
                            ?,
                            'en_curso',
                            NOW(),
                            ?
                         )"
                    );

                    $consulta->bind_param(
                        "is",
                        $idActividad,
                        $secreto
                    );
                }

                $consulta->execute();
                $cambios = $consulta->affected_rows;
                $consulta->close();

                if ($cambios > 0) {
                    $mensaje = "¡Asistencia iniciada correctamente!";
                    $tipoMensaje = "exito";
                } else {
                    $mensaje = "No se pudo iniciar la asistencia.";
                    $tipoMensaje = "error";
                }
            }

        // FINALIZAR
        } elseif ($accion === "finalizar") {

            if ($estadoAnterior !== "en_curso") {

                $mensaje = "No hay una asistencia activa para finalizar.";
                $tipoMensaje = "aviso";

            } else {

                $idControl = (int)$registroAnterior["id_control"];

                $consulta = $conn->prepare(
                    "UPDATE control_asistencias
                     SET estado = 'finalizada',
                         fecha_fin = NOW()
                     WHERE id_control = ?
                     AND estado = 'en_curso'"
                );

                $consulta->bind_param("i", $idControl);
                $consulta->execute();

                $cambios = $consulta->affected_rows;
                $consulta->close();

                if ($cambios > 0) {
                    $mensaje = "Asistencia finalizada correctamente.";
                    $tipoMensaje = "exito";
                } else {
                    $mensaje = "No se pudo finalizar la asistencia.";
                    $tipoMensaje = "error";
                }
            }

        } else {

            $mensaje = "Acción no reconocida.";
            $tipoMensaje = "error";
        }
    }
}

// ESTADO ACTUAL
$reunionActual = $reuniones[$idActividad] ?? null;

$control = $reunionActual
    ? consultarEstado($conn, $idActividad)
    : null;

$estado = $control["estado"] ?? "sin_iniciar";

$estados = [
    "sin_iniciar" => "Sin iniciar",
    "en_curso" => "En curso",
    "finalizada" => "Finalizada"
];

// LISTA REAL DE ASISTENTES
$asistentes = [];

if ($reunionActual) {

    $consulta = $conn->prepare(
        "SELECT
            u.nombre_completo,
            aa.fecha_registro
         FROM asistencia_actividades aa
         INNER JOIN usuarios u
            ON u.id_usuario = aa.id_usuario
         WHERE aa.id_actividad = ?
           AND aa.asistio = 1
         ORDER BY aa.fecha_registro DESC"
    );

    if ($consulta) {

        $consulta->bind_param("i", $idActividad);
        $consulta->execute();

        $resultado = $consulta->get_result();

        while ($fila = $resultado->fetch_assoc()) {
            $asistentes[] = $fila;
        }

        $consulta->close();
    }
}

// PREPARAR CODIGO QR
$enlaceQR = "";
$vencimientoQR = 0;
$secretoQR = null;

if ($estado === "en_curso" && !empty($control["id_control"])) {

    $idControl = (int)$control["id_control"];

    $consulta = $conn->prepare(
        "SELECT secreto_qr
         FROM control_asistencias
         WHERE id_control = ?
         LIMIT 1"
    );

    $consulta->bind_param("i", $idControl);
    $consulta->execute();
    $consulta->bind_result($secretoQR);
    $consulta->fetch();
    $consulta->close();
}

if (!empty($secretoQR)) {

    // CADA TURNO DURA 180 SEGUNDOS
    $turnoQR = (int)floor(time() / 180);

    $firmaQR = hash_hmac(
        "sha256",
        $idActividad . "|" . $turnoQR,
        $secretoQR
    );

    $protocolo = (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    ) ? "https" : "http";

    $carpeta = rtrim(
        str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])),
        "/"
    );

    $enlaceQR =
        $protocolo . "://" .
        $_SERVER["HTTP_HOST"] .
        $carpeta .
        "/registrar_asistencia.php" .
        "?reunion=" . $idActividad .
        "&turno=" . $turnoQR .
        "&firma=" . urlencode($firmaQR);

    $vencimientoQR = ($turnoQR + 1) * 180;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Asistencias | Tejido Comunal</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f7f6;
    color: #163a2c;
}

a {
    text-decoration: none;
}

.pagina {
    display: grid;
    grid-template-columns: 255px minmax(0, 1fr);
    min-height: 100vh;
}

/* MENU VERDE */
.lateral {
    background: linear-gradient(155deg, #06432f, #073a2b);
    color: white;
    padding: 22px 14px;
    display: flex;
    flex-direction: column;
}

.marca {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 7px 28px;
}

.marca img {
    width: 62px;
    height: 62px;
    object-fit: contain;
    border-radius: 12px;
    background: white;
}

.marca strong {
    display: block;
    font-size: 17px;
}

.marca small {
    display: block;
    color: #d0e8dc;
    margin-top: 5px;
    font-size: 11px;
}

.navegacion {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.navegacion a {
    display: flex;
    align-items: center;
    gap: 13px;
    color: white;
    padding: 13px 15px;
    border-radius: 11px;
    font-weight: bold;
    font-size: 14px;
}

.navegacion a:hover,
.navegacion .activo {
    background: #277753;
}

.icono {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
}

.pie-lateral {
    margin-top: auto;
    padding: 18px 12px 4px;
    border-top: 1px solid #46836a;
    line-height: 1.6;
    font-size: 13px;
}

.pie-lateral small {
    display: block;
    color: #c3e1d0;
}

/* CONTENIDO */
.contenido {
    padding: 0 27px 38px;
    min-width: 0;
}

.cabecera {
    min-height: 77px;
    border-bottom: 1px solid #dce7e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.administrador {
    background: white;
    border: 1px solid #dce9e2;
    border-radius: 35px;
    padding: 11px 17px;
    color: #0e6444;
    font-size: 12px;
    font-weight: bold;
}

.titulo {
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 24px 0;
}

.icono-titulo {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: #dff2e6;
    color: #08633e;
}

.titulo h1 {
    font-size: 29px;
    margin: 0 0 5px;
}

.titulo p {
    margin: 0;
    color: #698075;
    font-size: 14px;
}

/* DISTRIBUCION DE TARJETAS */
.fila-superior {
    display: grid;
    grid-template-columns: 1fr 1.13fr .95fr;
    gap: 17px;
}

.fila-inferior {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(235px, 1fr);
    gap: 17px;
    margin-top: 17px;
}

.tarjeta {
    background: white;
    border: 1px solid #e0eae4;
    box-shadow: 0 5px 20px rgba(18, 62, 39, .06);
    border-radius: 15px;
    padding: 20px;
    min-width: 0;
}

.tarjeta h2 {
    margin: 0 0 17px;
    font-size: 17px;
    display: flex;
    align-items: center;
    gap: 9px;
}

.tarjeta h2 .icono {
    color: #087049;
}

.dato {
    font-size: 13px;
    line-height: 1.6;
    border-top: 1px solid #eff3f0;
    padding: 10px 0;
}

.dato strong {
    display: block;
    color: #24523a;
}

.selector {
    width: 100%;
    border: 1px solid #ccddd1;
    border-radius: 9px;
    padding: 11px;
    background: white;
    margin: 12px 0;
}

.estado {
    font-size: 12px;
    padding: 7px 12px;
    border-radius: 20px;
    font-weight: bold;
    display: inline-block;
}

.estado.en_curso {
    background: #ddf7e8;
    color: #067344;
}

.estado.sin_iniciar {
    background: #edf0ec;
    color: #56675d;
}

.estado.finalizada {
    background: #ffe8e8;
    color: #ae3238;
}

/* QR */
.qr-centro {
    min-height: 190px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

#qr-dibujo {
    padding: 10px;
    background: white;
    border: 1px solid #e8ede9;
    border-radius: 12px;
}

.espera {
    text-align: center;
    color: #71877b;
    font-size: 13px;
    padding: 30px 8px;
}

.tiempo {
    text-align: center;
    margin-top: 13px;
    color: #296045;
    font-size: 14px;
}

.tiempo strong {
    font-size: 28px;
    display: block;
    margin-top: 5px;
}

.barra-tiempo {
    background: #e8efeb;
    height: 8px;
    border-radius: 20px;
    overflow: hidden;
    margin: 12px 0;
}

#barra-qr {
    background: #0a995d;
    width: 100%;
    height: 100%;
}

/* BOTONES */
.boton {
    display: block;
    width: 100%;
    border: 0;
    border-radius: 9px;
    padding: 14px 11px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
}

.boton-verde {
    background: #086844;
    color: white;
}

.boton-rojo {
    background: #d6333f;
    color: white;
}

.boton:disabled {
    background: #e6ece7;
    color: #809186;
    cursor: not-allowed;
}

.form-boton {
    margin: 12px 0;
}

.ayuda {
    background: #e8f7ef;
    color: #36654d;
    padding: 12px;
    border-radius: 10px;
    font-size: 12px;
    line-height: 1.6;
    margin-top: 12px;
}

.nota {
    color: #6b8073;
    font-size: 12px;
    line-height: 1.65;
    margin-top: 13px;
}

/* TABLA DE ASISTENTES */
.tabla-contenedor {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th, td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #e8efe9;
}

th {
    background: #e9f4f6;
    color: #183a4b;
}

.vacio {
    text-align: center;
    color: #789082;
    padding: 30px 12px;
}

.presente {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    background: #d7f8e4;
    color: #087045;
    font-size: 11px;
    font-weight: bold;
}

.resumen {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid #e9f0ea;
    font-size: 13px;
}

.resumen b {
    font-size: 20px;
    color: #075c3d;
}

.alerta {
    border-radius: 10px;
    padding: 14px 17px;
    margin-bottom: 18px;
    font-weight: bold;
    font-size: 14px;
}

.alerta.exito {
    background: #dff7e8;
    color: #0d6942;
}

.alerta.error {
    background: #ffe7e7;
    color: #a92e37;
}

.alerta.aviso {
    background: #fff2d7;
    color: #7a5e14;
}

/* ADAPTACION A PANTALLAS PEQUEÑAS */
@media (max-width: 1150px) {
    .fila-superior {
        grid-template-columns: 1fr 1fr;
    }

    .controles {
        grid-column: 1 / -1;
    }
}

@media (max-width: 800px) {
    .pagina {
        grid-template-columns: 1fr;
    }

    .lateral {
        padding: 13px;
    }

    .navegacion {
        flex-direction: row;
        overflow-x: auto;
    }

    .navegacion a {
        white-space: nowrap;
    }

    .pie-lateral {
        display: none;
    }

    .contenido {
        padding: 0 15px 25px;
    }

    .fila-superior,
    .fila-inferior {
        grid-template-columns: 1fr;
    }

    .controles {
        grid-column: auto;
    }
}

@media print {
    .lateral,
    .cabecera,
    .form-boton,
    .selector,
    .ayuda,
    .tiempo {
        display: none !important;
    }

    .pagina {
        display: block;
    }

    .contenido {
        padding: 0;
    }

    .tarjeta {
        box-shadow: none;
    }
}
</style>
</head>

<body>

<!-- ICONOS -->
<svg
    style="position:absolute;width:0;height:0;overflow:hidden"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
>
    <symbol id="i-home" viewBox="0 0 24 24">
        <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>
    </symbol>

    <symbol id="i-people" viewBox="0 0 24 24">
        <circle cx="9" cy="7" r="3"/>
        <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
        <path d="M17 11a3 3 0 1 0-1-6"/>
        <path d="M18 14a5 5 0 0 1 3 5v1"/>
    </symbol>

    <symbol id="i-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M7 3v4M17 3v4M3 10h18"/>
    </symbol>

    <symbol id="i-check" viewBox="0 0 24 24">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <path d="m8 12 3 3 5-6"/>
    </symbol>

    <symbol id="i-qr" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7"/>
        <rect x="14" y="3" width="7" height="7"/>
        <rect x="3" y="14" width="7" height="7"/>
        <path d="M14 14h3v3h-3zM20 14v7h-7"/>
    </symbol>

    <symbol id="i-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l4 2"/>
    </symbol>

    <symbol id="i-logout" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <path d="M16 17l5-5-5-5M21 12H9"/>
    </symbol>
</svg>

<div class="pagina">

    <!-- MENU LATERAL -->
    <aside class="lateral">

        <div class="marca">

            <img
                src="imagenes/logo_comunal.png"
                alt="Logo de Tejido Comunal"
            >

            <div>
                
         <strong>Tejido Comunal</strong>
         <small>Las Margaritas</small>

            </div>

        </div>

        <nav class="navegacion">

            <a href="dashboard.php?seccion=inicio">
                <svg class="icono"><use href="#i-home"/></svg>
                Inicio
            </a>

            <a href="dashboard.php?seccion=miembros">
                <svg class="icono"><use href="#i-people"/></svg>
                Miembros
            </a>

            <a href="dashboard.php?seccion=agenda">
                <svg class="icono"><use href="#i-calendar"/></svg>
                Agenda
            </a>

            <a href="dashboard.php?seccion=reuniones">
                <svg class="icono"><use href="#i-people"/></svg>
                Reuniones
            </a>

            <a href="control_asistencias.php" class="activo">
                <svg class="icono"><use href="#i-check"/></svg>
                Asistencias
            </a>

            <a href="dashboard.php?seccion=tareas">
                <svg class="icono"><use href="#i-check"/></svg>
                Tareas
            </a>

            <a href="dashboard.php?seccion=comunicados">
                <svg class="icono"><use href="#i-calendar"/></svg>
                Comunicados
            </a>

            <a href="dashboard.php?seccion=novedades">
                <svg class="icono"><use href="#i-calendar"/></svg>
                Novedades
            </a>

            <a href="dashboard.php?seccion=historial">
                <svg class="icono"><use href="#i-clock"/></svg>
                Historial
            </a>

            <a href="cerrar_sesion.php">
                <svg class="icono"><use href="#i-logout"/></svg>
                Cerrar sesión
            </a>

        </nav>

        <div class="pie-lateral">
            <strong>
                <?php echo limpiarTexto(
                    $_SESSION["nombre_completo"] ?? "Administración"
                ); ?>
            </strong>

            <small>Administrador comunal</small>
        </div>

    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido">

        <header class="cabecera">
            <strong>Tejido Comunal / Asistencias</strong>

            <span class="administrador">
                ADMINISTRADOR
            </span>
        </header>

        <div class="titulo">

            <span class="icono-titulo">
                <svg class="icono"
                     style="width:29px;height:29px;">
                    <use href="#i-people"/>
                </svg>
            </span>

            <div>
                <h1>Asistencias</h1>

                <p>
                    Control de asistencia de las reuniones
                    de nuestra comunidad.
                </p>
            </div>

        </div>

        <?php if ($mensaje !== ""): ?>

            <div class="alerta <?php echo limpiarTexto($tipoMensaje); ?>">
                <?php echo limpiarTexto($mensaje); ?>
            </div>

        <?php endif; ?>

        <!-- TARJETAS SUPERIORES -->
        <div class="fila-superior">

            <!-- INFORMACION DE LA REUNION -->
            <section class="tarjeta">

                <h2>
                    <svg class="icono"><use href="#i-calendar"/></svg>
                    Reunión en seguimiento
                </h2>

                <form method="GET">

                    <label for="reunion">
                        <strong>Selecciona una reunión</strong>
                    </label>

                    <select
                        class="selector"
                        id="reunion"
                        name="reunion"
                        onchange="this.form.submit()"
                    >

                        <?php if (empty($reuniones)): ?>
                            <option>No hay reuniones registradas</option>
                        <?php endif; ?>

                        <?php foreach ($reuniones as $reunion): ?>

                            <option
                                value="<?php echo (int)$reunion["id_actividad"]; ?>"
                                <?php echo
                                    $idActividad === (int)$reunion["id_actividad"]
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                <?php echo limpiarTexto($reunion["titulo"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

                <?php if ($reunionActual): ?>
                    
<!-- CREAR NUEVA REUNION -->

<details style="
    margin: 18px 0;
    padding: 15px;
    background: #f4faf6;
    border: 1px solid #d5e9dc;
    border-radius: 12px;
">

    <summary style="
        color: #086844;
        font-weight: bold;
        cursor: pointer;
        font-size: 14px;
    ">
        + Crear nueva reunión
    </summary>

    <form method="POST" style="margin-top: 20px;">

        <input type="hidden"
               name="accion"
               value="crear_reunion">

        <input type="hidden"
               name="token"
               value="<?php echo limpiarTexto($_SESSION['csrf_asistencias']); ?>">

        <label><strong>Nombre de la reunión</strong></label>
        <input type="text"
               name="titulo_reunion"
               class="selector"
               placeholder="Ej. Asamblea general del barrio"
               maxlength="150"
               required>

        <label><strong>¿De qué se va a tratar?</strong></label>
        <textarea name="descripcion_reunion"
                  class="selector"
                  rows="3"
                  placeholder="Escribe los temas de la reunión..."
                  style="font-family: Arial, sans-serif;"
                  required></textarea>

        <label><strong>Fecha</strong></label>
        <input type="date"
               name="fecha_reunion"
               class="selector"
               required>

        <label><strong>Hora</strong></label>
        <input type="time"
               name="hora_reunion"
               class="selector"
               required>

        <label><strong>Lugar</strong></label>
        <input type="text"
               name="lugar_reunion"
               class="selector"
               placeholder="Ej. Salón comunal"
               maxlength="150"
               required>

        <button type="submit"
                class="boton boton-verde">
            Guardar nueva reunión
        </button>

    </form>

</details>

<!-- FIN CREAR NUEVA REUNION -->


                    <span class="estado <?php echo limpiarTexto($estado); ?>">
                        <?php echo limpiarTexto($estados[$estado]); ?>
                    </span>

                    <div class="dato">
                        <strong>Nombre de la reunión</strong>
                        <?php echo limpiarTexto($reunionActual["titulo"]); ?>
                    </div>

                    <div class="dato">
                        <strong>Fecha y hora</strong>
                        <?php echo limpiarTexto($reunionActual["fecha"]); ?>
                        ·
                        <?php echo limpiarTexto($reunionActual["hora"]); ?>
                    </div>

                    <div class="dato">
                        <strong>Lugar</strong>
                        <?php echo limpiarTexto($reunionActual["lugar"]); ?>
                    </div>

                <?php else: ?>

                    <p>No hay una reunión seleccionada.</p>

                <?php endif; ?>

            </section>

            <!-- CODIGO QR -->
            <section class="tarjeta">

                <h2>
                    <svg class="icono"><use href="#i-qr"/></svg>
                    Código QR para proyectar
                </h2>

                <?php if ($enlaceQR !== ""): ?>

                    <div class="qr-centro">
                        <div id="qr-dibujo"></div>
                    </div>

                    <div class="tiempo">
                        Tiempo restante
                        <strong id="contador-qr">03:00</strong>
                    </div>

                    <div class="barra-tiempo">
                        <div id="barra-qr"></div>
                    </div>

                    <div class="ayuda">
                        El código cambia automáticamente
                        cada tres minutos mientras la
                        asistencia esté habilitada.
                    </div>

                <?php else: ?>

                    <div class="qr-centro">

                        <div class="espera">
                            <svg class="icono"
                                 style="width:54px;height:54px;">
                                <use href="#i-qr"/>
                            </svg>

                            <p>
                                El QR aparecerá aquí cuando
                                inicies la asistencia.
                            </p>
                        </div>

                    </div>

                <?php endif; ?>

            </section>

            <!-- CONTROLES -->
            <section class="tarjeta controles">

                <h2>
                    <svg class="icono"><use href="#i-check"/></svg>
                    Controles de asistencia
                </h2>

                <p class="nota">Estado actual:</p>

                <span class="estado <?php echo limpiarTexto($estado); ?>">
                    <?php echo limpiarTexto($estados[$estado]); ?>
                </span>

                <form method="POST" class="form-boton">

                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo limpiarTexto($_SESSION["csrf_asistencias"]); ?>"
                    >

                    <input
                        type="hidden"
                        name="id_actividad"
                        value="<?php echo (int)$idActividad; ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="iniciar"
                    >

                    <button
                        type="submit"
                        class="boton boton-verde"
                        <?php echo
                            (!$reunionActual || $estado !== "sin_iniciar")
                            ? "disabled"
                            : "";
                        ?>
                    >
                        ▶ Iniciar asistencia
                    </button>

                </form>

                <form
                    method="POST"
                    class="form-boton"
                    onsubmit="return confirm('¿Deseas finalizar la asistencia de esta reunión?');"
                >

                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo limpiarTexto($_SESSION["csrf_asistencias"]); ?>"
                    >

                    <input
                        type="hidden"
                        name="id_actividad"
                        value="<?php echo (int)$idActividad; ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="finalizar"
                    >

                    <button
                        type="submit"
                        class="boton boton-rojo"
                        <?php echo
                            $estado !== "en_curso"
                            ? "disabled"
                            : "";
                        ?>
                    >
                        ■ Finalizar asistencia
                    </button>

                </form>

                <div class="ayuda">
                    Al finalizar, se cierra el registro
                    y el código QR deja de ser válido.
                </div>

            </section>

        </div>

        <!-- TARJETAS INFERIORES -->
        <div class="fila-inferior">

            <!-- LISTA DE ASISTENTES -->
            <section class="tarjeta">

                <h2>
                    <svg class="icono"><use href="#i-people"/></svg>
                    Lista de asistentes
                </h2>

                <div class="tabla-contenedor">

                    <table>

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre completo</th>
                                <th>Hora de registro</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($asistentes as $indice => $persona): ?>

                                <tr>
                                    <td>
                                        <?php echo $indice + 1; ?>
                                    </td>

                                    <td>
                                        <?php echo limpiarTexto(
                                            $persona["nombre_completo"]
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php echo limpiarTexto(
                                            date(
                                                "h:i a",
                                                strtotime($persona["fecha_registro"])
                                            )
                                        ); ?>
                                    </td>

                                    <td>
                                        <span class="presente">
                                            Presente
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (empty($asistentes)): ?>

                                <tr>
                                    <td colspan="4" class="vacio">
                                        Todavía no hay asistencias
                                        registradas para esta reunión.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <!-- RESUMEN -->
            <section class="tarjeta">

                <h2>
                    <svg class="icono"><use href="#i-people"/></svg>
                    Resumen de asistencia
                </h2>

                <div class="resumen">
                    <span>Asistencias registradas</span>
                    <b><?php echo count($asistentes); ?></b>
                </div>

                <div class="resumen">
                    <span>Estado de la reunión</span>

                    <span class="estado <?php echo limpiarTexto($estado); ?>">
                        <?php echo limpiarTexto($estados[$estado]); ?>
                    </span>
                </div>

                <div class="resumen">
                    <span>Hora de cierre</span>

                    <strong>
                        <?php
                        echo !empty($control["fecha_fin"])
                            ? limpiarTexto(
                                date(
                                    "h:i a",
                                    strtotime($control["fecha_fin"])
                                )
                            )
                            : "—";
                        ?>
                    </strong>
                </div>

                <button
                    type="button"
                    class="boton boton-verde"
                    style="margin-top:20px;"
                    onclick="window.print()"
                >
                    ↓ Imprimir / Guardar como PDF
                </button>

                <p class="nota">
                    Puedes guardar el reporte como PDF
                    desde la ventana de impresión.
                </p>

            </section>

        </div>

        <p class="nota">
            El código QR se genera cada tres minutos.
            En el siguiente paso conectaremos su escaneo
            con el registro real de asistencia.
        </p>

    </main>

</div>

<!-- GENERAR Y ACTUALIZAR QR -->
<?php if ($enlaceQR !== ""): ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
(function () {

    const enlace = <?php echo json_encode(
        $enlaceQR,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ); ?>;

    const vencimiento = <?php echo (int)$vencimientoQR * 1000; ?>;

    const destino = document.getElementById("qr-dibujo");
    const contador = document.getElementById("contador-qr");
    const barra = document.getElementById("barra-qr");

    if (typeof QRCode === "function") {

        new QRCode(destino, {
            text: enlace,
            width: 165,
            height: 165,
            colorDark: "#071d15",
            colorLight: "#ffffff"
        });

    } else {

        destino.textContent =
            "No se pudo cargar el código QR. Comprueba la conexión a Internet.";
    }

    function actualizarTiempo() {

        const segundos = Math.max(
            0,
            Math.ceil((vencimiento - Date.now()) / 1000)
        );

        const minutos = Math.floor(segundos / 60);
        const resto = segundos % 60;

        contador.textContent =
            String(minutos).padStart(2, "0") +
            ":" +
            String(resto).padStart(2, "0");

        barra.style.width =
            (segundos / 180 * 100) + "%";

        if (segundos === 0) {
            window.location.replace(
                "control_asistencias.php?reunion=<?php echo (int)$idActividad; ?>"
            );
        }
    }

    actualizarTiempo();

    setInterval(actualizarTiempo, 1000);

})();
</script>

<?php endif; ?>

</body>
</html>