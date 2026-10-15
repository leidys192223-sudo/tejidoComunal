<?php
session_start();

if (
    !isset($_SESSION["id_usuario"]) ||
    (int)($_SESSION["id_rol"] ?? 0) !== 1
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/gestion_comunal.php"; 

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


/* GUARDAR NOVEDADES EN LA BASE DE DATOS */

// Crear el código de seguridad del formulario.
if (empty($_SESSION["csrf_novedades"])) {
    $_SESSION["csrf_novedades"] = bin2hex(random_bytes(32));
}

// Guardar cuando el administrador envía una novedad.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["titulo_novedad"])
) {
    $tituloNovedad = trim($_POST["titulo_novedad"] ?? "");
    $tipoNovedad = trim($_POST["tipo_novedad"] ?? "");
    $fechaNovedad = trim($_POST["fecha_novedad"] ?? "");
    $descripcionNovedad = trim($_POST["descripcion_novedad"] ?? "");
    $tokenNovedad = (string)($_POST["token_novedad"] ?? "");

    $idAdministradorNovedad = (int)$_SESSION["id_usuario"];

    $tiposPermitidosNovedad = [
        "noticia",
        "avance",
        "situacion",
        "otro"
    ];

    $fechaComprobada = DateTime::createFromFormat(
        "!Y-m-d",
        $fechaNovedad
    );

    $fechaValida = $fechaComprobada &&
        $fechaComprobada->format("Y-m-d") === $fechaNovedad;

    if (
        !hash_equals($_SESSION["csrf_novedades"], $tokenNovedad) ||
        $tituloNovedad === "" ||
        strlen($tituloNovedad) > 150 ||
        !in_array($tipoNovedad, $tiposPermitidosNovedad, true) ||
        !$fechaValida ||
        $descripcionNovedad === ""
    ) {
        header("Location: dashboard.php?seccion=novedades&error=1");
        exit;
    }

    $sqlNovedad = "
        INSERT INTO novedades
        (titulo, tipo, fecha, descripcion, id_usuario)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmtNovedad = $conn->prepare($sqlNovedad);

    if ($stmtNovedad) {
        $stmtNovedad->bind_param(
            "ssssi",
            $tituloNovedad,
            $tipoNovedad,
            $fechaNovedad,
            $descripcionNovedad,
            $idAdministradorNovedad
        );

        if ($stmtNovedad->execute()) {
            $stmtNovedad->close();

            header("Location: dashboard.php?seccion=novedades&guardado=1");
            exit;
        }

        $stmtNovedad->close();
    }

    header("Location: dashboard.php?seccion=novedades&error=1");
    exit;
}

/* FIN DE GUARDAR NOVEDADES */


/* GUARDAR REUNIONES EN LA BASE DE DATOS */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["titulo_reunion"])
) {
    $titulo = trim($_POST["titulo_reunion"] ?? "");
    $fecha = $_POST["fecha_reunion"] ?? "";
    $hora = $_POST["hora_reunion"] ?? "";
    $lugar = trim($_POST["lugar_reunion"] ?? "");
    $descripcion = trim($_POST["descripcion_reunion"] ?? "");
    $id_usuario = (int) $_SESSION["id_usuario"];

    if (
        $titulo !== "" &&
        $fecha !== "" &&
        $hora !== "" &&
        $lugar !== ""
    ) {
        $sql = "INSERT INTO actividades
                (titulo, descripcion, fecha, hora, lugar,
                 id_usuario, tipo, estado)
                VALUES (?, ?, ?, ?, ?, ?, 'reunion', 'programada')";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "sssssi",
                $titulo,
                $descripcion,
                $fecha,
                $hora,
                $lugar,
                $id_usuario
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: dashboard.php?seccion=reuniones&guardado=1");
                exit;
            }

            $stmt->close();
        }
    }
}
/* ACTIVIDADES PARA EL CALENDARIO COMUNITARIO */

 
/* GUARDAR EVENTOS DESDE AGENDA */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["titulo_agenda"])
) {
    $tipoAgenda = trim($_POST["tipo_agenda"] ?? "");
    $tituloAgenda = trim($_POST["titulo_agenda"] ?? "");
    $fechaAgenda = trim($_POST["fecha_agenda"] ?? "");
    $horaAgenda = trim($_POST["hora_agenda"] ?? "");
    $descripcionAgenda = trim($_POST["descripcion_agenda"] ?? "");

    $idAdministradorAgenda = (int)$_SESSION["id_usuario"];

    // Los tipos permitidos por el formulario.
    $tiposPermitidosAgenda = ["actividad", "reserva"];

    // Por ahora, el formulario no tiene campo de lugar.
    $lugarAgenda = ($tipoAgenda === "reserva")
        ? "Salón comunal"
        : "Por definir";

    $fechaValida = DateTime::createFromFormat(
        "!Y-m-d",
        $fechaAgenda
    );

    $fechaCorrecta = $fechaValida &&
        $fechaValida->format("Y-m-d") === $fechaAgenda;

    $horaCorrecta = preg_match(
        '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',
        $horaAgenda
    );

    if (
        in_array($tipoAgenda, $tiposPermitidosAgenda, true) &&
        $tituloAgenda !== "" &&
        mb_strlen($tituloAgenda) <= 150 &&
        $fechaCorrecta &&
        $horaCorrecta
    ) {
        $sqlGuardarAgenda = "
            INSERT INTO actividades
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
            VALUES
            (?, ?, ?, ?, ?, ?, ?, 'programada')
        ";

        $stmtAgenda = $conn->prepare($sqlGuardarAgenda);

        if ($stmtAgenda) {
            $stmtAgenda->bind_param(
                "sssssis",
                $tituloAgenda,
                $descripcionAgenda,
                $fechaAgenda,
                $horaAgenda,
                $lugarAgenda,
                $idAdministradorAgenda,
                $tipoAgenda
            );

            if ($stmtAgenda->execute()) {
                $stmtAgenda->close();

                header(
                    "Location: dashboard.php?seccion=agenda&guardado=1"
                );
                exit;
            }

            $stmtAgenda->close();
        }
    }

    header(
        "Location: dashboard.php?seccion=agenda&error=1"
    );
    exit;
}

/* FIN DE GUARDAR EVENTOS DE AGENDA */

$actividadesCalendario = [];

$sqlCalendario = "SELECT id_actividad, titulo, descripcion,
                         fecha, hora, lugar, tipo
                  FROM actividades
                  ORDER BY fecha ASC, hora ASC";

$resultadoCalendario = $conn->query($sqlCalendario);

if ($resultadoCalendario) {
    while ($actividad = $resultadoCalendario->fetch_assoc()) {
        $actividadesCalendario[] = $actividad;
    }
}
$nombre_admin = $_SESSION["nombre_completo"] ?? "Administrador";


/* GUARDAR TAREAS COMUNITARIAS */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["titulo_tarea"])
) {
    $tituloTarea = trim($_POST["titulo_tarea"] ?? "");
    $descripcionTarea = trim($_POST["descripcion_tarea"] ?? "");
    $fechaTarea = $_POST["fecha_tarea"] ?? "";
    $estadoTarea = $_POST["estado_tarea"] ?? "pendiente";
    $idAdministrador = (int) $_SESSION["id_usuario"];

    $guardadoTarea = guardarTareaComunal(
        $conn,
        $tituloTarea,
        $descripcionTarea,
        $fechaTarea,
        $estadoTarea,
        $idAdministrador
    );

    if ($guardadoTarea) {
        header("Location: dashboard.php?seccion=tareas&guardado=1");
        exit;
    }

    $errorTarea = "No se pudo guardar la tarea.";
}

$secciones_permitidas = [
    "inicio",
    "miembros",
    "agenda",
    "reuniones",
    "asistencias",
    "tareas",
    "comunicados",
    "novedades",
    "historial",
    "codigo"
];

$seccion = $_GET["seccion"] ?? "inicio";

if (!in_array($seccion, $secciones_permitidas, true)) {
    $seccion = "inicio";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tejido Comunal | Administración</title>

    <style>
        :root {
            --verde: #153f2b;
            --verde-claro: #1e6840;
            --fondo: #f8faf7;
            --lila: #f1e9f6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--fondo);
            color: #24352b;
        }

        .menu-lateral {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--verde);
            color: white;
            padding: 25px 16px;
            overflow-y: auto;
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            padding: 8px;
        }

        .marca-icono {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: #e7f2e8;
            border-radius: 12px;
            font-size: 26px;
        }

        .marca strong {
            display: block;
            font-size: 17px;
        }

        .marca small {
            display: block;
            margin-top: 5px;
            color: #c4d8ca;
        }

        .menu-lateral nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            color: #e5efe8;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
        }

        .menu-lateral nav a:hover,
        .menu-lateral nav a.activo {
            background: #2d6847;
            color: white;
        }

        .contenido-principal {
            margin-left: 260px;
            padding: 28px 35px;
            min-height: 100vh;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 35px;
        }

        .encabezado h1 {
            margin: 0;
            font-size: 28px;
        }

        .identidad-admin {
            text-align: right;
        }

        .identidad-admin small {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: var(--verde-claro);
            letter-spacing: 1px;
        }

        .nombre-admin {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 30px;
            background: white;
            box-shadow: 0 2px 12px #00000012;
            font-weight: bold;
        }

        @media (max-width: 750px) {
            .menu-lateral {
                position: relative;
                width: 100%;
                height: auto;
            }

            .contenido-principal {
                margin-left: 0;
                padding: 20px;
            }

            .encabezado {
                flex-wrap: wrap;
            }
        }
    </style>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>

<body>

<aside class="menu-lateral">

    <div class="marca">
        
<div class="marca-icono" style="background: transparent; padding: 0; width: 55px; height: 55px;">
    <img src="imagenes/logo_comunal.png"
         alt="Logo de la Junta de Acción Comunal"
         style="width: 100%; height: 100%; object-fit: contain;">
</div>

        <div>
            <strong>Tejido Comunal</strong>
            <small>Las Margaritas</small>
        </div>
    </div>

    <nav>
        <?php
            
$opciones_menu = [
    "inicio" => ["house", "Inicio"],
    "miembros" => ["users", "Miembros"],
    "agenda" => ["calendar-days", "Agenda"],
    "reuniones" => ["handshake", "Reuniones"],
    "asistencias" => ["clipboard-check", "Asistencias"],
    "tareas" => ["list-todo", "Tareas"],
    "comunicados" => ["megaphone", "Comunicados"],
    "novedades" => ["newspaper", "Novedades"],
    "historial" => ["history", "Historial"],
    "codigo" => ["key-round", "Código de autorización"]
];

        foreach ($opciones_menu as $clave => $opcion):
        ?>
            <a
                
href="<?= $clave === 'asistencias' ? 'control_asistencias.php' : 'dashboard.php?seccion=' . urlencode($clave) ?>"

               class="<?= $seccion === $clave ? 'activo' : '' ?>"
            >
                
<span class="icono-menu" data-lucide="<?= escapar($opcion[0]) ?>"></span>
                <span><?= escapar($opcion[1]) ?></span>
            </a>
        <?php endforeach; ?>

        <a href="cerrar_sesion.php">
         <span class="icono-menu" data-lucide="log-out"></span>
            <span>Cerrar sesión</span>
        </a>
    </nav>

</aside>

<main class="contenido-principal">

    <header class="encabezado">
        <h1><?= escapar(ucfirst($seccion)) ?></h1>

        <div class="identidad-admin">
            <small>ADMINISTRADOR</small>
            <div class="nombre-admin">
                <?= escapar($nombre_admin) ?>
            </div>
        </div>
    </header>

    <style>
        .bienvenida {
            text-align: center;
            margin: 20px auto 35px;
        }

        .bienvenida h2 {
            font-size: 30px;
            color: #153f2b;
            margin-bottom: 12px;
        }

        .bienvenida p {
            font-size: 16px;
            color: #53675a;
        }

        .carrusel {
            max-width: 1000px;
            margin: 0 auto 35px;
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 20px #00000012;
        }

        .carrusel-imagen {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            display: block;
        }

        .carrusel-controles {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            gap: 15px;
        }

        .carrusel-controles h3 {
            margin: 0;
            color: #153f2b;
        }

        .controles-fotos {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .flecha-foto {
            border: none;
            background: #e4f0e7;
            color: #153f2b;
            border-radius: 9px;
            padding: 9px 13px;
            cursor: pointer;
            font-size: 19px;
        }

        .flecha-foto:hover {
            background: #cce4d3;
        }

        .puntos-fotos {
            display: flex;
            gap: 7px;
        }

        .punto-foto {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #c9d7cd;
        }

        .punto-foto.activo {
            background: #1e6840;
        }
    </style>

    <?php if ($seccion === "inicio"): ?>

        <section class="bienvenida">
            <h2>¡Bienvenidos a Tejido Comunal!</h2>
            <p>
                Junta de Acción Comunal del barrio Las Margaritas.
                Un espacio para construir comunidad.
            </p>
        </section>

        <section class="carrusel">

            <img
                id="imagenComunidad"
                class="carrusel-imagen"
                src="imagenes/comunidad1.png"
                alt="Fotografía de nuestra comunidad"
            >

            <div class="carrusel-controles">

                <h3>Nuestra comunidad</h3>

                <div class="controles-fotos">

                    <button
                        type="button"
                        class="flecha-foto"
                        onclick="cambiarFoto(-1)"
                        aria-label="Fotografía anterior"
                    >
                        &#10094;
                    </button>

                    <div class="puntos-fotos">
                        <span class="punto-foto activo"></span>
                        <span class="punto-foto"></span>
                        <span class="punto-foto"></span>
                    </div>

                    <button
                        type="button"
                        class="flecha-foto"
                        onclick="cambiarFoto(1)"
                        aria-label="Fotografía siguiente"
                    >
                        &#10095;
                    </button>

                </div>
            </div>
        </section>

    <?php endif; ?>

    <script>
        const fotosComunidad = [
            "imagenes/comunidad1.png",
            "imagenes/comunidad2.png",
            "imagenes/comunidad3.png"
        ];

        let fotoActual = 0;

        function cambiarFoto(direccion) {
            fotoActual =
                (fotoActual + direccion + fotosComunidad.length)
                % fotosComunidad.length;

            const imagen = document.getElementById("imagenComunidad");

            if (imagen) {
                imagen.src = fotosComunidad[fotoActual];
            }

            const puntos = document.querySelectorAll(".punto-foto");

            puntos.forEach(function(punto, indice) {
                punto.classList.toggle(
                    "activo",
                    indice === fotoActual
                );
            });
        }
         
         // Cambiar las imágenes automáticamente cada 3 segundos
if (document.getElementById("imagenComunidad")) {
    setInterval(function () {
        cambiarFoto(1);
    }, 3000);
}
    </script>

    <style>
        .panel-agenda {
            max-width: 1050px;
            margin: 0 auto;
        }

        .tarjeta-agenda {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 3px 15px #00000010;
            margin-bottom: 22px;
        }

        .tarjeta-agenda h2 {
            color: #153f2b;
            margin-top: 0;
        }

        .boton-agenda {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            background: #1e6840;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .boton-agenda:hover {
            background: #153f2b;
        }

        .formulario-agenda {
            display: none;
            margin-top: 22px;
            padding: 24px;
            border-radius: 14px;
            background: #f1e9f6;
        }

        .formulario-agenda.visible {
            display: block;
        }

        .campo-agenda {
            margin-bottom: 17px;
        }

        .campo-agenda label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .campo-agenda input,
        .campo-agenda select,
        .campo-agenda textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd9ce;
            border-radius: 9px;
            font-size: 15px;
        }

        .campo-agenda textarea {
            min-height: 100px;
        }

        .nota-agenda {
            color: #53675a;
            font-size: 14px;
        }
    </style>

    <?php if ($seccion === "agenda"): ?>

        <section class="panel-agenda">

            <div class="tarjeta-agenda">

                <h2>Agenda comunitaria</h2>

                <p>
                    Organiza actividades, reuniones y reservas
                    del salón comunal del barrio Las Margaritas.
                </p>

                <div class="calendario-comunal" style="margin: 20px 0;">
    <h3>Calendario de programación</h3>
   

<div id="calendarioAdministrador"></div>


<style>
/* Diseño del calendario del administrador */
#calendarioAdministrador {
    background: #e3eefb;
    border-radius: 20px;
    padding: 30px;
    margin: 20px 0;
    color: #153f2b;
}

/* Casillas blancas y redondeadas */
#calendarioAdministrador button {
    border: none !important;
    border-radius: 10px !important;
    color: #153f2b;
    font-weight: 700;
    cursor: pointer;
}

/* Espacio al pasar el mouse */
#calendarioAdministrador button:hover {
    box-shadow: 0 2px 8px rgba(21, 63, 43, 0.12);
}

/* Adaptación a celulares */
@media (max-width: 600px) {
    #calendarioAdministrador {
        padding: 12px;
    }
}
</style>



<div id="fechaSeleccionadaAgenda">
    Selecciona un día para programar una actividad.
    
  </p>

</div>
                <button
                    type="button"
                    class="boton-agenda"
                    onclick="mostrarFormularioAgenda()"
                >
                    + Programación
                </button>

                <style>
            /* Formulario de programación de Agenda */
            #formularioAgenda {
             display: none !important;
}

#formularioAgenda.visible {
    display: block !important;
}
</style>

                <form
                    id="formularioAgenda"
                    class="formulario-agenda"
                    method="post"
                    action="dashboard.php?seccion=agenda"
                >

                    <h3>Nuevo evento o reserva</h3>

                    <div class="campo-agenda">
                        <label for="tipoAgenda">Tipo</label>

                        <select
                            id="tipoAgenda"
                            name="tipo_agenda"
                            required
                        >
                            <option value="">Seleccionar</option>
                            <option value="actividad">
                                Actividad comunitaria
                            </option>
                            <option value="reserva">
                                Reserva del salón comunal
                            </option>
                        </select>
                    </div>

                    <div class="campo-agenda">
                        <label for="tituloAgenda">
                            Nombre del evento
                        </label>

                        <input
                            type="text"
                            id="tituloAgenda"
                            name="titulo_agenda"
                            placeholder="Ejemplo: Fiesta de 15 años"
                            required
                        >
                    </div>

                    <div class="campo-agenda">
                        <label for="fechaAgenda">Fecha</label>

                        <input
                            type="date"
                            id="fechaAgenda"
                            name="fecha_agenda"
                            readonly
                            required
                        >
                    </div>

                    <div class="campo-agenda">
                        <label for="horaAgenda">
                            Hora de inicio
                        </label>

                        <input
                            type="time"
                            id="horaAgenda"
                            name="hora_agenda"
                            required
                        >
                    </div>

                    <div class="campo-agenda">
                        <label for="descripcionAgenda">
                            Descripción
                        </label>

                        <textarea
                            id="descripcionAgenda"
                            name="descripcion_agenda"
                            placeholder="Escribe los detalles del evento"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="boton-agenda"
                    >
                        Guardar evento
                    </button>

                    <p class="nota-agenda">
                        El formulario ya está diseñado.
                        La conexión para guardar los eventos
                        en la base de datos se añadirá después.
                    </p>

                </form>

            </div>

        </section>

    <?php endif; ?>

    <script>
function mostrarFormularioAgenda() {
    const formulario = document.getElementById("formularioAgenda");

    if (formulario) {
        formulario.classList.toggle("visible");
    }
}

(function () {
    const calendario = document.getElementById("calendarioAdministrador");
    
const actividadesGuardadas = <?php
    echo json_encode(
        $actividadesCalendario,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: "[]";
?>;

    const mensaje = document.getElementById("fechaSeleccionadaAgenda");

    if (!calendario || !mensaje) return;

    const meses = [
        "Enero", "Febrero", "Marzo", "Abril",
        "Mayo", "Junio", "Julio", "Agosto",
        "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    const hoy = new Date();
    let mes = hoy.getMonth();
    let anio = hoy.getFullYear();

    function dibujarCalendario() {
        calendario.innerHTML = "";

        const encabezado = document.createElement("div");
        encabezado.style.cssText =
            "display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;";

        const anterior = document.createElement("button");
        anterior.type = "button";
        anterior.textContent = "❮";

        const titulo = document.createElement("strong");
        titulo.textContent = meses[mes] + " " + anio;

        const siguiente = document.createElement("button");
        siguiente.type = "button";
        siguiente.textContent = "❯";

        anterior.onclick = function () {
            mes--;
            if (mes < 0) {
                mes = 11;
                anio--;
            }
            dibujarCalendario();
        };

        siguiente.onclick = function () {
            mes++;
            if (mes > 11) {
                mes = 0;
                anio++;
            }
            dibujarCalendario();
        };

        encabezado.append(anterior, titulo, siguiente);
        calendario.appendChild(encabezado);

        const cuadricula = document.createElement("div");
        cuadricula.style.cssText =
            "display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px;";

        ["L", "M", "M", "J", "V", "S", "D"].forEach(function (nombre) {
            const celda = document.createElement("strong");
            celda.textContent = nombre;
            celda.style.textAlign = "center";
            cuadricula.appendChild(celda);
        });

        const primerDia = (new Date(anio, mes, 1).getDay() + 6) % 7;
        const totalDias = new Date(anio, mes + 1, 0).getDate();

        for (let i = 0; i < primerDia; i++) {
            cuadricula.appendChild(document.createElement("span"));
        }

        for (let dia = 1; dia <= totalDias; dia++) {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.textContent = dia;

            boton.style.cssText =
                "padding:10px 2px;border:1px solid #d5e5d9;border-radius:7px;background:white;color:#153f2b;cursor:pointer;";

            


            
const fechaDelDia =
    anio + "-" +
    String(mes + 1).padStart(2, "0") + "-" +
    String(dia).padStart(2, "0");

const tieneActividad = actividadesGuardadas.some(function (actividad) {
    return actividad.fecha === fechaDelDia;
});

if (tieneActividad) {
    boton.style.background = "#e4f4e9";
    boton.style.color = "#126b45";
    boton.style.fontWeight = "bold";

    const punto = document.createElement("span");

    punto.style.cssText =
        "display:block;width:6px;height:6px;" +
        "background:#219861;border-radius:50%;" +
        "margin:4px auto 0;";

    boton.appendChild(punto);
    
}
   boton.onclick = function () {

                const fecha = anio + "-" +
                    String(mes + 1).padStart(2, "0") + "-" +
                    String(dia).padStart(2, "0");

                const campoFecha = document.getElementById("fechaAgenda");

                if (campoFecha) {
                    campoFecha.value = fecha;
                }

                
mensaje.replaceChildren();

const encabezadoDia = document.createElement("strong");
encabezadoDia.textContent =
    "Actividades del " + dia + " de " +
    meses[mes].toLowerCase() + " de " + anio;

mensaje.appendChild(encabezadoDia);

const actividadesDelDia = actividadesGuardadas.filter(function (actividad) {
    return actividad.fecha === fecha;
});

if (actividadesDelDia.length === 0) {
    const aviso = document.createElement("span");
    aviso.textContent = "No hay actividades programadas para este día.";
    aviso.style.cssText = "display:block;margin-top:12px;color:#607967;";
    mensaje.appendChild(aviso);
} else {
    actividadesDelDia.forEach(function (actividad) {
        const tarjeta = document.createElement("span");

        
    tarjeta.style.cssText =
         "display:block;background:#ffffff;" +
        "border:1px solid #dce9e0;" +
        "border-left:5px solid #17643b;" +
        "padding:22px 25px;border-radius:14px;" +
        "margin-top:15px;white-space:pre-line;" +
         "line-height:1.8;font-size:16px;" +
        "box-shadow:0 4px 15px rgba(21,63,43,0.07);" +
         "color:#153f2b;";


        
const dibujosAgenda = {
    calendario:
        '<rect x="3" y="5" width="18" height="16" rx="2"/>' +
        '<path d="M7 3v4M17 3v4M3 10h18"/>',

    reloj:
        '<circle cx="12" cy="12" r="9"/>' +
        '<path d="M12 7v5l3 2"/>',

    ubicacion:
        '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/>' +
        '<circle cx="12" cy="10" r="2.5"/>'
};

function crearFilaAgenda(tipoIcono, texto) {
    const fila = document.createElement("div");

    fila.style.cssText =
        "display:flex;align-items:center;gap:12px;" +
        "margin:10px 0;color:#153f2b;";

    const icono = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "svg"
    );

    icono.setAttribute("width", "23");
    icono.setAttribute("height", "23");
    icono.setAttribute("viewBox", "0 0 24 24");
    icono.setAttribute("fill", "none");
    icono.setAttribute("stroke", "#24573d");
    icono.setAttribute("stroke-width", "2");
    icono.setAttribute("stroke-linecap", "round");
    icono.setAttribute("stroke-linejoin", "round");

    icono.style.flexShrink = "0";
    icono.innerHTML = dibujosAgenda[tipoIcono];

    const contenido = document.createElement("span");
    contenido.textContent = texto;

    fila.append(icono, contenido);

    return fila;
}

tarjeta.replaceChildren();

const tituloActividad = document.createElement("h3");
tituloActividad.textContent = actividad.titulo;
tituloActividad.style.cssText =
    "color:#153f2b;font-size:19px;margin:0 0 12px;";

tarjeta.appendChild(tituloActividad);

tarjeta.appendChild(
    crearFilaAgenda(
        "calendario",
        "Tipo: " + actividad.tipo
    )
);

tarjeta.appendChild(
    crearFilaAgenda(
        "reloj",
        "Hora: " + actividad.hora
    )
);

tarjeta.appendChild(
    crearFilaAgenda(
        "ubicacion",
        "Lugar: " + (actividad.lugar || "No especificado")
    )
);

const descripcionActividad = document.createElement("p");

descripcionActividad.textContent =
    actividad.descripcion || "Sin descripción";

descripcionActividad.style.cssText =
    "color:#607967;margin:15px 0 0;" +
    "padding-top:12px;border-top:1px solid #e1e9e2;" +
    "white-space:pre-wrap;";

tarjeta.appendChild(descripcionActividad);

        mensaje.appendChild(tarjeta);
    });
}


                
cuadricula.querySelectorAll("button").forEach(function (b) {
    b.style.background = "white";
    b.style.color = "#153f2b";
    b.style.fontWeight = "normal";
});

boton.style.background = "#17643d";
boton.style.color = "white";
boton.style.fontWeight = "bold";

};


            cuadricula.appendChild(boton);
        }

        calendario.appendChild(cuadricula);
    }

    dibujarCalendario();
})();
</script>

    <style>
        .panel-novedades {
            max-width: 1050px;
            margin: 0 auto;
        }

        .tarjeta-novedades {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 3px 15px #00000010;
            margin-bottom: 22px;
        }

        .tarjeta-novedades h2 {
            margin-top: 0;
            color: #153f2b;
        }

        .boton-novedades {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            background: #1e6840;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .boton-novedades:hover {
            background: #153f2b;
        }

        .formulario-novedades {
            display: none;
            margin-top: 22px;
            padding: 24px;
            border-radius: 14px;
            background: #f1e9f6;
        }

        .formulario-novedades.visible {
            display: block;
        }

        .campo-novedades {
            margin-bottom: 17px;
        }

        .campo-novedades label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .campo-novedades input,
        .campo-novedades select,
        .campo-novedades textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd9ce;
            border-radius: 9px;
            background: white;
            font-size: 15px;
        }

        .campo-novedades textarea {
            min-height: 120px;
            resize: vertical;
        }

        .nota-novedades {
            margin-top: 15px;
            color: #53675a;
            font-size: 14px;
        }
    </style>

    <?php if ($seccion === "novedades"): ?>

        <section class="panel-novedades">

            <div class="tarjeta-novedades">

                <h2>Novedades de la comunidad</h2>

                <p>
                    Registra las noticias, avances y situaciones
                    importantes del barrio Las Margaritas.
                </p>

                <button
                    type="button"
                    class="boton-novedades"
                    onclick="mostrarFormularioNovedades()"
                >
                    + Crear novedad
                </button>

<!-- NOVEDADES GUARDADAS -->

<div style="margin: 25px 0;">

    <h3 style="color: #153f2b; margin-bottom: 18px;">
        Novedades publicadas
    </h3>

    <?php
    $consultaNovedades = $conn->query(
        "SELECT id_novedad, titulo, tipo, fecha, descripcion
         FROM novedades
         ORDER BY fecha DESC, id_novedad DESC"
    );
    ?>

    <?php if ($consultaNovedades && $consultaNovedades->num_rows > 0): ?>

        <?php while ($novedad = $consultaNovedades->fetch_assoc()): ?>

            <div style="
                background: #f4faf6;
                border: 1px solid #d8e9de;
                border-left: 5px solid #17643d;
                border-radius: 13px;
                padding: 20px;
                margin-bottom: 15px;
            ">

                <h3 style="
                    margin: 0 0 12px;
                    color: #153f2b;
                ">
                    <?php echo escapar($novedad["titulo"]); ?>
                </h3>

                <p style="margin: 8px 0;">
                    <strong>Categoría:</strong>
                    <?php
                    $categorias = [
                        "noticia" => "Noticia",
                        "avance" => "Avance comunitario",
                        "situacion" => "Situación del barrio",
                        "otro" => "Otro"
                    ];

                    echo escapar(
                        $categorias[$novedad["tipo"]] ?? $novedad["tipo"]
                    );
                    ?>
                </p>

                <p style="margin: 8px 0;">
                    <strong>Fecha:</strong>
                    <?php echo escapar($novedad["fecha"]); ?>
                </p>

                <p style="
                    margin: 12px 0 0;
                    line-height: 1.6;
                    white-space: pre-wrap;
                "><?php echo escapar($novedad["descripcion"]); ?></p>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p style="
            background: #f4faf6;
            padding: 18px;
            border-radius: 12px;
            color: #53675a;
        ">
            Todavía no hay novedades publicadas.
        </p>

    <?php endif; ?>

    <?php
    if ($consultaNovedades) {
        $consultaNovedades->free();
    }
    ?>

</div>

<!-- FIN NOVEDADES GUARDADAS -->

                <form
                    id="formularioNovedades"
                    class="formulario-novedades"
                    method="post"
                    action="dashboard.php?seccion=novedades"
                >
                
                 <input
                 type="hidden"
                 name="token_novedad"
                 value="<?php echo escapar($_SESSION['csrf_novedades']); ?>"
>


                    <h3>Nueva novedad</h3>

                    <div class="campo-novedades">
                        <label for="tituloNovedad">
                            Título de la novedad
                        </label>

                        <input
                            type="text"
                            id="tituloNovedad"
                            name="titulo_novedad"
                            placeholder="Ejemplo: Jornada de limpieza"
                            required
                        >
                    </div>

                    <div class="campo-novedades">
                        <label for="tipoNovedad">
                            Categoría
                        </label>

                        <select
                            id="tipoNovedad"
                            name="tipo_novedad"
                            required
                        >
                            <option value="">Seleccionar</option>
                            <option value="noticia">Noticia</option>
                            <option value="avance">Avance comunitario</option>
                            <option value="situacion">Situación del barrio</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="campo-novedades">
                        <label for="fechaNovedad">
                            Fecha
                        </label>

                        <input
                            type="date"
                            id="fechaNovedad"
                            name="fecha_novedad"
                            required
                        >
                    </div>

                    <div class="campo-novedades">
                        <label for="descripcionNovedad">
                            Descripción
                        </label>

                        <textarea
                            id="descripcionNovedad"
                            name="descripcion_novedad"
                            placeholder="Escribe los detalles de la novedad"
                            required
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="boton-novedades"
                    >
                        Guardar novedad
                    </button>

                    <p class="nota-novedades">
                        El formulario está diseñado.
                        La conexión para guardar novedades
                        en la base de datos se añadirá después.
                    </p>

                </form>

            </div>

        </section>

    <?php endif; ?>

    <script>
        function mostrarFormularioNovedades() {
            const formulario =
                document.getElementById("formularioNovedades");

            if (formulario) {
                formulario.classList.toggle("visible");
            }
        }
    </script>

    <style>
        .panel-historial {
            max-width: 1050px;
            margin: 0 auto;
        }

        .tarjeta-historial {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 3px 15px #00000010;
            margin-bottom: 22px;
        }

        .tarjeta-historial h2 {
            margin-top: 0;
            color: #153f2b;
        }

        .filtros-historial {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 22px;
            padding: 20px;
            border-radius: 12px;
            background: #f1e9f6;
        }

        .campo-historial {
            flex: 1 1 160px;
        }

        .campo-historial label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .campo-historial select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd9ce;
            border-radius: 9px;
            background: white;
            font-size: 15px;
        }

        .boton-historial {
            padding: 12px 20px;
            border: none;
            border-radius: 9px;
            background: #1e6840;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .boton-historial:hover {
            background: #153f2b;
        }

        .boton-historial.secundario {
            background: #e4f0e7;
            color: #153f2b;
        }

        .tabla-historial {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
        }

        .tabla-historial th,
        .tabla-historial td {
            padding: 13px;
            border-bottom: 1px solid #e4ece6;
            text-align: left;
        }

        .tabla-historial th {
            background: #e4f0e7;
            color: #153f2b;
        }

        .detalle-historial {
            display: none;
            margin-top: 18px;
            padding: 18px;
            border-radius: 12px;
            background: #f4faf5;
        }

        .detalle-historial.visible {
            display: block;
        }

        .mensaje-historial {
            margin-top: 20px;
            color: #53675a;
        }

        .contenedor-tabla-historial {
            overflow-x: auto;
        }
    </style>

    <?php if ($seccion === "historial"): ?>

        
<section class="panel-historial" style="max-width: 100%; width: 100%;">

    <iframe
        src="historial.php"
        title="Historial de actividades"
        style="
            display: block;
            width: 100%;
            height: 850px;
            border: none;
            border-radius: 18px;
            background: #f3f7f3;
        "
    ></iframe>

</section>

    <?php endif; ?>

    <script>
        function buscarHistorial() {
            const mensaje =
                document.getElementById("mensajeHistorial");

            if (mensaje) {
                mensaje.textContent =
                    "Seleccionaste los filtros. " +
                    "La consulta a la base de datos " +
                    "todavía está pendiente.";
            }
        }

        function verTodoHistorial() {
            const mes =
                document.getElementById("mesHistorial");

            const anio =
                document.getElementById("anioHistorial");

            const detalle =
                document.getElementById("detalleHistorial");

            const mensaje =
                document.getElementById("mensajeHistorial");

            if (mes) mes.value = "";
            if (anio) anio.value = "";

            if (detalle) {
                detalle.classList.remove("visible");
                detalle.textContent = "";
            }

            if (mensaje) {
                mensaje.textContent =
                    "Filtros restablecidos. " +
                    "Los registros se mostrarán cuando " +
                    "conectemos el historial a la base de datos.";
            }
        }

        function verDetalleHistorial(descripcion) {
            const detalle =
                document.getElementById("detalleHistorial");

            if (detalle) {
                detalle.textContent = descripcion;
                detalle.classList.add("visible");
            }
        }
    </script>

    <style>
        .panel-administrativo {
            max-width: 1050px;
            margin: 0 auto;
        }

        .tarjeta-administrativa {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 3px 15px #00000010;
            margin-bottom: 22px;
        }

        .tarjeta-administrativa h2 {
            margin-top: 0;
            color: #153f2b;
        }

        .tabla-administrativa {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabla-administrativa th,
        .tabla-administrativa td {
            padding: 13px;
            border-bottom: 1px solid #e4ece6;
            text-align: left;
        }

        .tabla-administrativa th {
            background: #e4f0e7;
            color: #153f2b;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .mensaje-vacio {
            color: #53675a;
            padding: 12px 0;
        }
    </style>

    <?php if ($seccion === "miembros"): ?>

        <section class="panel-administrativo">
            <div class="tarjeta-administrativa">
                <h2>Miembros de la comunidad</h2>

                <p>
                    Personas registradas en Tejido Comunal.
                </p>

                <?php
                $consulta_miembros = $conn->query(
                    "SELECT nombre_completo, email, contacto
                     FROM usuarios
                     ORDER BY nombre_completo ASC"
                );
                ?>

                <div class="tabla-contenedor">
                    <table class="tabla-administrativa">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Contacto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (
                                $consulta_miembros &&
                                $consulta_miembros->num_rows > 0
                            ): ?>

                                <?php while (
                                    $miembro = $consulta_miembros->fetch_assoc()
                                ): ?>
                                    <tr>
                                        <td>
                                            <?= escapar($miembro["nombre_completo"]) ?>
                                        </td>
                                        <td>
                                            <?= escapar($miembro["email"]) ?>
                                        </td>
                                        <td>
                                            <?= escapar($miembro["contacto"]) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        No hay miembros para mostrar.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    <?php endif; ?>


    <?php if ($seccion === "reuniones"): ?>

        <section class="panel-administrativo">
            <div class="tarjeta-administrativa">
                <h2>Reuniones</h2>

                <p>
                    Aquí organizaremos las reuniones
                    de la Junta de Acción Comunal.
                </p>

                
<button
    type="button"
    class="boton-agenda"
    onclick="document.getElementById('formularioReunion').classList.toggle('visible')"
>
    + Crear reunión
</button>

<form
    id="formularioReunion"
    class="formulario-agenda"
    method="post"
    action="dashboard.php?seccion=reuniones"
>
    <h3>Nueva reunión</h3>

    <div class="campo-agenda">
        <label for="tituloReunion">Nombre de la reunión</label>
        <input
            type="text"
            id="tituloReunion"
            name="titulo_reunion"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="fechaReunion">Fecha</label>
        <input
            type="date"
            id="fechaReunion"
            name="fecha_reunion"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="horaReunion">Hora</label>
        <input
            type="time"
            id="horaReunion"
            name="hora_reunion"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="lugarReunion">Lugar</label>
        <input
            type="text"
            id="lugarReunion"
            name="lugar_reunion"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="descripcionReunion">Descripción</label>
        <textarea
            id="descripcionReunion"
            name="descripcion_reunion"
        ></textarea>
    </div>

    <button type="submit" class="boton-agenda">
        Guardar reunión
    </button>

    <p class="nota-agenda">
        El formulario está preparado.
        Todavía falta conectar el guardado
        con la base de datos.
    </p>
</form>

<?php
$reuniones = $conn->query(
    "SELECT titulo, descripcion, fecha, hora, lugar
     FROM actividades
     WHERE tipo = 'reunion'
     ORDER BY fecha DESC, hora DESC"
);
?>

<h3 style="margin-top: 30px;">Reuniones programadas</h3>

<?php if ($reuniones && $reuniones->num_rows > 0): ?>

    <?php while ($reunion = $reuniones->fetch_assoc()): ?>

        <div class="tarjeta-administrativa" style="margin-top: 15px;">

            <h3><?= escapar($reunion["titulo"]) ?></h3>

            <p>
                <strong>Fecha:</strong>
                <?= escapar($reunion["fecha"]) ?>
            </p>

            <p>
                <strong>Hora:</strong>
                <?= escapar($reunion["hora"]) ?>
            </p>

            <p>
                <strong>Lugar:</strong>
                <?= escapar($reunion["lugar"]) ?>
            </p>

            <p>
                <strong>Descripción:</strong>
                <?= nl2br(escapar($reunion["descripcion"])) ?>
            </p>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <p class="mensaje-vacio">
        Todavía no hay reuniones registradas.
    </p>

<?php endif; ?>

            </div>
        </section>

    <?php endif; ?>


    <?php if ($seccion === "asistencias"): ?>

        <section class="panel-administrativo">
            <div class="tarjeta-administrativa">
                <h2>Asistencias</h2>

                <p>
                    Aquí consultaremos la participación
                    de los miembros en las actividades.
                </p>

                
<button
    type="button"
    class="boton-agenda"
    onclick="document.getElementById('formularioAsistencia').classList.toggle('visible')"
>
    + Registrar asistencia
</button>

<form
    id="formularioAsistencia"
    class="formulario-agenda"
    method="post"
    action="dashboard.php?seccion=asistencias"
>
    <h3>Registrar asistencia</h3>

    <div class="campo-agenda">
        <label for="actividadAsistencia">
            Actividad
        </label>

        <select
            id="actividadAsistencia"
            name="id_actividad"
            required
        >
            <option value="">Seleccionar actividad</option>
            <?php
            $actividades_asistencia = $conn->query(
                "SELECT * FROM actividades"
            );

            if ($actividades_asistencia):
                while ($actividad = $actividades_asistencia->fetch_assoc()):
            ?>
                <option value="<?= (int)$actividad['id_actividad'] ?>">
                    <?= escapar(
                        $actividad['titulo']
                        ?? $actividad['nombre']
                        ?? 'Actividad'
                    ) ?>
                </option>
            <?php
                endwhile;
            endif;
            ?>
        </select>
    </div>

    <div class="campo-agenda">
        <label for="miembroAsistencia">
            Miembro de la comunidad
        </label>

        <select
            id="miembroAsistencia"
            name="id_usuario"
            required
        >
            <option value="">Seleccionar miembro</option>
            <?php
            $miembros_asistencia = $conn->query(
                "SELECT id_usuario, nombre_completo
                 FROM usuarios
                 ORDER BY nombre_completo"
            );

            if ($miembros_asistencia):
                while ($miembro = $miembros_asistencia->fetch_assoc()):
            ?>
                <option value="<?= (int)$miembro['id_usuario'] ?>">
                    <?= escapar($miembro['nombre_completo']) ?>
                </option>
            <?php
                endwhile;
            endif;
            ?>
        </select>
    </div>

    <div class="campo-agenda">
        <label for="fechaAsistencia">
            Fecha de asistencia
        </label>

        <input
            type="date"
            id="fechaAsistencia"
            name="fecha_asistencia"
            required
        >
    </div>

    <button type="submit" class="boton-agenda">
        Guardar asistencia
    </button>

    <p class="nota-agenda">
        El formulario está preparado.
        Todavía falta conectar el guardado
        con la base de datos.
    </p>
</form>

            </div>
        </section>

    <?php endif; ?>


    <?php if ($seccion === "tareas"): ?>

        <section class="panel-administrativo">
            <div class="tarjeta-administrativa">
                <h2>Mis tareas</h2>

                <p>
    Organiza tus tareas personales como administrador.
    Programa tus pendientes y marca cada tarea como
    Por hacer, En proceso o Hecha.
</p>

                
<button
    type="button"
    class="boton-agenda"
    onclick="document.getElementById('formularioTarea').classList.toggle('visible')"
>
    + Crear tarea
</button>

<form
    id="formularioTarea"
    class="formulario-agenda"
    method="post"
    action="dashboard.php?seccion=tareas"
>
    <h3>Nueva tarea</h3>

    <div class="campo-agenda">
        <label for="tituloTarea">Nombre de la tarea</label>
        <input
            type="text"
            id="tituloTarea"
            name="titulo_tarea"
            required
        >
    </div>

    
    <div class="campo-agenda">
        <label for="fechaTarea">Fecha límite</label>
        <input
            type="date"
            id="fechaTarea"
            name="fecha_tarea"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="estadoTarea">Estado</label>
        <select id="estadoTarea" name="estado_tarea">
    <option value="pendiente">Por hacer</option>
    <option value="en_proceso">En proceso</option>
    <option value="completada">Hecha</option>
</select>
    </div>

    <div class="campo-agenda">
        <label for="descripcionTarea">Descripción</label>
        <textarea
            id="descripcionTarea"
            name="descripcion_tarea"
        ></textarea>
    </div>

    <button type="submit" class="boton-agenda">
        Guardar tarea
    </button>
</form>
    <?php
$idAdministradorTareas = (int) $_SESSION["id_usuario"];

$consultaTareas = $conn->prepare(
    "SELECT id_tarea, titulo, descripcion, fecha_entrega, estado
     FROM tareas
     WHERE id_usuario = ? AND estado != 'completada'
ORDER BY fecha_entrega ASC, id_tarea DESC"
);

$tareasAdministrador = [];

if ($consultaTareas) {
    $consultaTareas->bind_param("i", $idAdministradorTareas);
    $consultaTareas->execute();

    $resultadoTareas = $consultaTareas->get_result();

    while ($tarea = $resultadoTareas->fetch_assoc()) {
        $tareasAdministrador[] = $tarea;
    }

    $consultaTareas->close();
}
?>

<h3 style="margin-top: 30px;">Mis tareas programadas</h3>

<?php if (empty($tareasAdministrador)): ?>

    <p>Todavía no tienes tareas programadas.</p>

<?php else: ?>

    <?php foreach ($tareasAdministrador as $tarea): ?>

        <div class="tarjeta-administrativa" style="margin-top: 15px;">

            <h3><?= escapar($tarea["titulo"]) ?></h3>

            <p>
                <strong>Fecha:</strong>
                <?= escapar($tarea["fecha_entrega"]) ?>
            </p>

            <p>
                <?= nl2br(escapar($tarea["descripcion"] ?? "")) ?>
            </p>

            <p>
                <strong>Estado actual:</strong>
                <?php
                $nombresEstados = [
                    "pendiente" => "Por hacer",
                    "en_proceso" => "En proceso",
                    "completada" => "Hecha"
                ];

                echo escapar(
                    $nombresEstados[$tarea["estado"]] ?? "Por hacer"
                );
                ?>
            </p>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">

               <form method="post" action="dashboard.php?seccion=tareas"
      style="display:flex; gap:10px; flex-wrap:wrap;">

    <input type="hidden"
           name="cambiar_estado_tarea"
           value="1">

    <input type="hidden"
           name="id_tarea"
           value="<?= (int) $tarea["id_tarea"] ?>">

    <button type="submit"
            name="nuevo_estado"
            value="pendiente"
            class="boton-agenda">
        Por hacer
    </button>

    <button type="submit"
            name="nuevo_estado"
            value="en_proceso"
            class="boton-agenda">
        En proceso
    </button>

    <button type="submit"
            name="nuevo_estado"
            value="completada"
            class="boton-agenda">
        ✓ Hecha
    </button>

</form>
            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>
            </div>
        </section>

    <?php endif; ?>


    <?php if ($seccion === "comunicados"): ?>

        <section class="panel-administrativo">
            <div class="tarjeta-administrativa">
                <h2>Comunicados</h2>

                <p>
                    Aquí publicaremos los anuncios
                    importantes de la Junta de Acción Comunal.
                </p>

                
<button
    type="button"
    class="boton-agenda"
    onclick="document.getElementById('formularioComunicado').classList.toggle('visible')"
>
    + Crear comunicado
</button>

<form
    id="formularioComunicado"
    class="formulario-agenda"
    method="post"
    action="dashboard.php?seccion=comunicados"
>
    <h3>Nuevo comunicado</h3>

    <div class="campo-agenda">
        <label for="tituloComunicado">
            Título del comunicado
        </label>
        <input
            type="text"
            id="tituloComunicado"
            name="titulo_comunicado"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="fechaComunicado">
            Fecha del comunicado
        </label>
        <input
            type="date"
            id="fechaComunicado"
            name="fecha_comunicado"
            required
        >
    </div>

    <div class="campo-agenda">
        <label for="contenidoComunicado">
            Contenido del comunicado
        </label>
        <textarea
            id="contenidoComunicado"
            name="contenido_comunicado"
            placeholder="Escribe aquí el mensaje para la comunidad"
            required
        ></textarea>
    </div>

    <button
        type="submit"
        class="boton-agenda"
    >
        Guardar comunicado
    </button>

    <p class="nota-agenda">
        El formulario está preparado.
        Todavía falta conectar el guardado
        con la base de datos.
    </p>
</form> 


<?php
$listaComunicados = obtenerComunicadosComunales($conn);
?>

<h3 style="margin-top: 30px; color: #153f2b;">
    Comunicados publicados
</h3>

<?php if (count($listaComunicados) > 0): ?>

    <?php foreach ($listaComunicados as $comunicado): ?>

        <div class="tarjeta-administrativa"
             style="margin-top: 15px; border: 1px solid #e4ece6;">

            <h3>
                <?= escapar($comunicado["titulo"]) ?>
            </h3>

            <p>
                <strong>Fecha:</strong>
                <?= escapar($comunicado["fecha_publicacion"]) ?>
            </p>

            <p>
                <strong>Categoría:</strong>
                <?= escapar($comunicado["categoria"]) ?>
            </p>

            <p>
                <strong>Mensaje:</strong>
            </p>

            <p>
                <?= nl2br(escapar($comunicado["contenido"])) ?>
            </p>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <p class="mensaje-vacio">
        Todavía no hay comunicados publicados.
    </p>

<?php endif; ?>

            </div>
        </section>

    <?php endif; ?>

    <style>
        .panel-codigo {
            max-width: 800px;
            margin: 0 auto;
        }

        .tarjeta-codigo {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 3px 15px #00000010;
            text-align: center;
        }

        .tarjeta-codigo h2 {
            margin-top: 0;
            color: #153f2b;
        }

        .tarjeta-codigo p {
            color: #53675a;
            line-height: 1.6;
        }

        .boton-codigo {
            display: inline-block;
            margin-top: 15px;
            padding: 13px 22px;
            border-radius: 10px;
            background: #1e6840;
            color: white;
            font-weight: bold;
            text-decoration: none;
        }

        .boton-codigo:hover {
            background: #153f2b;
        }
    </style>

    <?php if ($seccion === "codigo"): ?>

        <section class="panel-codigo">

            <div class="tarjeta-codigo">

                <h2>Código de autorización</h2>

                <p>
                    Esta sección es exclusiva para
                    los administradores de Tejido Comunal.
                </p>

                <p>
                    Aquí podrás consultar el código
                    de autorización de seis dígitos
                    utilizado para registrar
                    nuevos administradores.
                </p>

                <a
                    class="boton-codigo"
                    href="ver_codigo_admin.php"
                >
                    Ver código de autorización
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>
</html