<?php
session_start();

require_once __DIR__ . "/conexion.php";

date_default_timezone_set("America/Bogota");

function escaparAsistencia($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$mensaje = "";
$tipoMensaje = "error";
$reunion = null;
$puedeRegistrar = false;

$idActividad = filter_var(
    $_POST["reunion"] ?? $_GET["reunion"] ?? null,
    FILTER_VALIDATE_INT
);

$turno = filter_var(
    $_POST["turno"] ?? $_GET["turno"] ?? null,
    FILTER_VALIDATE_INT
);

$firma = (string)(
    $_POST["firma"] ?? $_GET["firma"] ?? ""
);

$idUsuario = (int)($_SESSION["id_usuario"] ?? 0);

// Verificar que sea un vecino registrado.
if ($idUsuario === 0) {

    $mensaje = "Debes iniciar sesión como usuario para registrar tu asistencia.";

} else {

    $consulta = $conn->prepare(
        "SELECT id_rol
         FROM usuarios
         WHERE id_usuario = ?
         LIMIT 1"
    );

    $consulta->bind_param("i", $idUsuario);
    $consulta->execute();

    $datosRol = $consulta->get_result()->fetch_assoc();
    $consulta->close();

    if (!$datosRol || (int)$datosRol["id_rol"] !== 2) {
        $mensaje = "Este registro es exclusivo para los usuarios de la comunidad.";
    }
}

// Comprobar los datos del QR.
if ($mensaje === "") {

    if (
        !$idActividad ||
        $turno === false ||
        $turno === null ||
        !preg_match('/^[a-f0-9]{64}$/', $firma)
    ) {
        $mensaje = "El enlace del código QR no es válido.";
    }
}

// Consultar la reunión y su control.
if ($mensaje === "") {

    $consulta = $conn->prepare(
        "SELECT
            c.id_control,
            c.estado,
            c.secreto_qr,
            a.titulo,
            a.fecha,
            a.hora,
            a.lugar
         FROM control_asistencias c
         INNER JOIN actividades a
            ON a.id_actividad = c.id_actividad
         WHERE c.id_actividad = ?
           AND a.tipo = 'reunion'
         ORDER BY c.id_control DESC
         LIMIT 1"
    );

    $consulta->bind_param("i", $idActividad);
    $consulta->execute();

    $reunion = $consulta->get_result()->fetch_assoc();
    $consulta->close();

    if (!$reunion) {

        $mensaje = "No se encontró la reunión.";

    } elseif ($reunion["estado"] !== "en_curso") {

        $mensaje = "El registro de asistencia de esta reunión está cerrado.";

    } elseif ((int)floor(time() / 180) !== (int)$turno) {

        $mensaje = "Este código QR ya venció. Escanea el nuevo código proyectado en la reunión.";

    } else {

        $firmaCorrecta = hash_hmac(
            "sha256",
            $idActividad . "|" . $turno,
            $reunion["secreto_qr"]
        );

        if (!hash_equals($firmaCorrecta, $firma)) {

            $mensaje = "El código QR no es válido.";

        } else {

            $puedeRegistrar = true;
        }
    }
}

// Registrar únicamente cuando el vecino confirme.
if (
    $puedeRegistrar &&
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    try {

        $conn->begin_transaction();

        // Bloquear el control durante el registro
        // para evitar registros simultáneos duplicados.
        $consulta = $conn->prepare(
            "SELECT estado, secreto_qr
             FROM control_asistencias
             WHERE id_control = ?
             FOR UPDATE"
        );

        $idControl = (int)$reunion["id_control"];

        $consulta->bind_param("i", $idControl);
        $consulta->execute();

        $controlActual = $consulta->get_result()->fetch_assoc();
        $consulta->close();

        $turnoActual = (int)floor(time() / 180);

        $firmaActual = $controlActual
            ? hash_hmac(
                "sha256",
                $idActividad . "|" . $turno,
                $controlActual["secreto_qr"]
            )
            : "";

        if (
            !$controlActual ||
            $controlActual["estado"] !== "en_curso" ||
            $turnoActual !== (int)$turno ||
            !hash_equals($firmaActual, $firma)
        ) {

            $conn->rollback();

            $mensaje = "El QR venció o la asistencia fue cerrada.";
            $puedeRegistrar = false;

        } else {

            // Revisar si ya existe una asistencia
            // para esta reunión y este usuario.
            $consulta = $conn->prepare(
                "SELECT id_asistencia, asistio
                 FROM asistencia_actividades
                 WHERE id_actividad = ?
                   AND id_usuario = ?
                 LIMIT 1"
            );

            $consulta->bind_param(
                "ii",
                $idActividad,
                $idUsuario
            );

            $consulta->execute();

            $anterior = $consulta->get_result()->fetch_assoc();
            $consulta->close();

            if ($anterior && (int)$anterior["asistio"] === 1) {

                $conn->commit();

                $mensaje = "Tu asistencia ya estaba registrada para esta reunión.";
                $tipoMensaje = "exito";

            } elseif ($anterior) {

                $idAsistencia = (int)$anterior["id_asistencia"];

                $guardar = $conn->prepare(
                    "UPDATE asistencia_actividades
                     SET asistio = 1,
                         fecha_registro = NOW()
                     WHERE id_asistencia = ?"
                );

                $guardar->bind_param("i", $idAsistencia);
                $guardar->execute();
                $guardar->close();

                $conn->commit();

                $mensaje = "¡Tu asistencia quedó registrada correctamente!";
                $tipoMensaje = "exito";

            } else {

                $guardar = $conn->prepare(
                    "INSERT INTO asistencia_actividades
                     (id_actividad, id_usuario, asistio)
                     VALUES (?, ?, 1)"
                );

                $guardar->bind_param(
                    "ii",
                    $idActividad,
                    $idUsuario
                );

                $guardado = $guardar->execute();
                $guardar->close();

                if (!$guardado) {
                    throw new Exception("No se pudo guardar la asistencia.");
                }

                $conn->commit();

                $mensaje = "¡Tu asistencia quedó registrada correctamente!";
                $tipoMensaje = "exito";
            }

            $puedeRegistrar = false;
        }

    } catch (Throwable $error) {

        $conn->rollback();

        $mensaje = "No se pudo registrar la asistencia. Inténtalo nuevamente.";
        $tipoMensaje = "error";
        $puedeRegistrar = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrar asistencia | Tejido Comunal</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #f2f8f4;
            color: #173f2b;
        }

        header {
            background: #173f2b;
            color: white;
            padding: 17px;
            text-align: center;
        }

        header h2 {
            margin: 0;
        }

        header p {
            margin: 7px 0 0;
            color: #d1e8db;
        }

        main {
            max-width: 520px;
            margin: 45px auto;
            padding: 0 16px;
        }

        .tarjeta {
            background: white;
            border: 1px solid #dceae0;
            border-radius: 22px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 60, 35, 0.08);
            text-align: center;
        }

        .logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
        }

        h1 {
            font-size: 26px;
            margin-bottom: 12px;
        }

        .detalle {
            text-align: left;
            background: #f2f8f4;
            padding: 18px;
            border-radius: 13px;
            margin: 22px 0;
            line-height: 1.8;
        }

        .mensaje {
            padding: 18px;
            border-radius: 12px;
            margin: 20px 0;
            line-height: 1.6;
        }

        .exito {
            background: #ddf5e6;
            color: #086a40;
        }

        .error {
            background: #fceaea;
            color: #a52e36;
        }

        button, .volver {
            display: block;
            width: 100%;
            padding: 15px;
            border: 0;
            border-radius: 12px;
            background: #08734b;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            margin-top: 16px;
        }

        .volver {
            background: #e6f2eb;
            color: #075c38;
        }
    </style>
</head>

<body>

<header>
    <h2>Tejido Comunal</h2>
    <p>Junta de Acción Comunal · Las Margaritas</p>
</header>

<main>
    <div class="tarjeta">

        <img
            src="imagenes/logo_comunal.png"
            class="logo"
            alt="Logo de Tejido Comunal"
        >

        <h1>Registro de asistencia</h1>

        <?php if ($reunion): ?>

            <div class="detalle">
                <strong>Reunión:</strong>
                <?php echo escaparAsistencia($reunion["titulo"]); ?>

                <hr>

                <strong>Fecha:</strong>
                <?php echo escaparAsistencia($reunion["fecha"]); ?>

                <hr>

                <strong>Hora:</strong>
                <?php echo escaparAsistencia($reunion["hora"]); ?>

                <hr>

                <strong>Lugar:</strong>
                <?php echo escaparAsistencia($reunion["lugar"]); ?>
            </div>

        <?php endif; ?>

        <?php if ($mensaje !== ""): ?>

            <div class="mensaje <?php echo escaparAsistencia($tipoMensaje); ?>">
                <?php echo escaparAsistencia($mensaje); ?>
            </div>

        <?php endif; ?>

        <?php if ($puedeRegistrar): ?>

            <p>
                Confirma tu participación en esta reunión.
            </p>

            <form method="POST">

                <input
                    type="hidden"
                    name="reunion"
                    value="<?php echo (int)$idActividad; ?>"
                >

                <input
                    type="hidden"
                    name="turno"
                    value="<?php echo (int)$turno; ?>"
                >

                <input
                    type="hidden"
                    name="firma"
                    value="<?php echo escaparAsistencia($firma); ?>"
                >

                <button type="submit">
                    ✓ Confirmar mi asistencia
                </button>

            </form>

        <?php endif; ?>

        <a class="volver" href="usuario.php">
            Volver a Tejido Comunal
        </a>

        <?php if ($idUsuario === 0): ?>

            <a class="volver" href="login.php">
                Iniciar sesión
            </a>

        <?php endif; ?>

    </div>
</main>

</body>
</html>