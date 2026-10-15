<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}




if (

    !isset($_SESSION['id_usuario']) ||

    (int)($_SESSION['id_rol'] ?? 0) !== 1

) {

    header("Location: login.php");

    exit;

}



require_once __DIR__ . "/conexion.php";



function e($valor) {

    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');

}



$menu = [

    'inicio' => ['Inicio', 'house'],

    'miembros' => ['Miembros', 'users-round'],

    'agenda' => ['Agenda', 'calendar-days'],

    'reuniones' => ['Reuniones', 'messages-square'],

    'asistencias' => ['Asistencias', 'clipboard-check'],

    'tareas' => ['Tareas', 'list-checks'],

    'comunicados' => ['Comunicados', 'megaphone'],

    'novedades' => ['Novedades', 'bell'],

    'historial' => ['Historial', 'history'],

    'codigo' => ['Código de autorización', 'shield-check']

];



$seccion = $_GET['seccion'] ?? 'inicio';



if (!isset($menu[$seccion])) {

    $seccion = 'inicio';

}



$nombre = $_SESSION['nombre_completo'] ?? 'Administrador';

$mensaje = '';

$error = false;



if (empty($_SESSION['csrf_dashboard'])) {

    $_SESSION['csrf_dashboard'] = bin2hex(random_bytes(32));

}



/* GUARDAR INFORMACIÓN */



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    $csrf = $_POST['csrf'] ?? '';



    if (!hash_equals($_SESSION['csrf_dashboard'], $csrf)) {

        $mensaje = 'La sesión del formulario venció. Actualiza la página.';

        $error = true;

    } elseif ($accion === 'crear_reunion') {

        $titulo = trim($_POST['titulo'] ?? '');

        $descripcion = trim($_POST['descripcion'] ?? '');

        $fecha = trim($_POST['fecha'] ?? '');

        $hora = trim($_POST['hora'] ?? '');

        $lugar = trim($_POST['lugar'] ?? '');



        $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);



        if (

            $titulo === '' ||

            $lugar === '' ||

            !$fechaValida ||

            $fechaValida->format('Y-m-d') !== $fecha ||

            !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)

        ) {

            $mensaje = 'Completa correctamente los datos de la reunión.';

            $error = true;

        } else {

            $idUsuario = (int)$_SESSION['id_usuario'];

            $tipo = 'Reunión';

            $estado = 'Programada';



            $stmt = $conn->prepare(

                "INSERT INTO actividades

                (titulo, descripcion, fecha, hora, lugar, id_usuario, tipo, estado)

                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"

            );



            if ($stmt) {

                $stmt->bind_param(

                    'sssssiss',

                    $titulo,

                    $descripcion,

                    $fecha,

                    $hora,

                    $lugar,

                    $idUsuario,

                    $tipo,

                    $estado

                );



                if ($stmt->execute()) {

                    $stmt->close();

                    header("Location: dashboard.php?seccion=reuniones&guardado=1");

                    exit;

                }



                $stmt->close();

            }



            $mensaje = 'No se pudo guardar la reunión.';

            $error = true;

        }

    } elseif ($accion === 'crear_evento') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $lugar = trim($_POST['lugar'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'Actividad');
        $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);
        if ($titulo === '' || $lugar === '' || !$fechaValida ||
            $fechaValida->format('Y-m-d') !== $fecha ||
            !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) ||
            !in_array($tipo, ['Actividad', 'Alquiler'], true)) {
            $mensaje = 'Completa correctamente los datos del evento.';
            $error = true;
        } else {
            // El salón no puede alquilarse dos veces en la misma fecha.
            $disponible = true;
            if ($tipo === 'Alquiler') {
                $verificar = $conn->prepare("SELECT COUNT(*) FROM actividades WHERE fecha = ? AND tipo = 'Alquiler' AND estado <> 'Cancelada'");
                if ($verificar) {
                    $verificar->bind_param('s', $fecha);
                    $verificar->execute();
                    $verificar->bind_result($ocupadas);
                    $verificar->fetch();
                    $disponible = ((int)$ocupadas === 0);
                    $verificar->close();
                } else {
                    $disponible = false;
                }
            }
            if (!$disponible) {
                $mensaje = 'El salón ya está reservado para esa fecha o no se pudo comprobar su disponibilidad.';
                $error = true;
            } else {
                $idUsuario = (int)$_SESSION['id_usuario'];
                $estado = 'Programada';
                $stmt = $conn->prepare("INSERT INTO actividades (titulo, descripcion, fecha, hora, lugar, id_usuario, tipo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sssssiss', $titulo, $descripcion, $fecha, $hora, $lugar, $idUsuario, $tipo, $estado);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: dashboard.php?seccion=agenda&guardado=1');
                        exit;
                    }
                    $stmt->close();
                }
                $mensaje = 'No se pudo guardar el evento.';
                $error = true;
            }
        }
    } elseif ($accion === 'crear_novedad') {
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        if ($titulo === '' || $contenido === '') {
            $mensaje = 'Escribe el título y el contenido de la novedad.';
            $error = true;
        } else {
            $categoria = 'Novedad';
            $importante = isset($_POST['importante']) ? 1 : 0;
            $stmt = $conn->prepare('INSERT INTO comunicados (titulo, contenido, categoria, fecha_publicacion, importante) VALUES (?, ?, ?, NOW(), ?)');
            if ($stmt) {
                $stmt->bind_param('sssi', $titulo, $contenido, $categoria, $importante);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: dashboard.php?seccion=novedades&guardado=1');
                    exit;
                }
                $stmt->close();
            }
            $mensaje = 'No se pudo publicar la novedad.';
            $error = true;
        }
    } elseif ($accion === 'crear_comunicado') {

        $titulo = trim($_POST['titulo'] ?? '');

        $contenido = trim($_POST['contenido'] ?? '');

        $categoria = trim($_POST['categoria'] ?? 'General');

        $importante = isset($_POST['importante']) ? 1 : 0;



        if ($titulo === '' || $contenido === '') {

            $mensaje = 'Escribe el título y el contenido del comunicado.';

            $error = true;

        } else {

            $stmt = $conn->prepare(

                "INSERT INTO comunicados

                (titulo, contenido, categoria, fecha_publicacion, importante)

                VALUES (?, ?, ?, NOW(), ?)"

            );



            if ($stmt) {

                $stmt->bind_param(

                    'sssi',

                    $titulo,

                    $contenido,

                    $categoria,

                    $importante

                );



                if ($stmt->execute()) {

                    $stmt->close();

                    header("Location: dashboard.php?seccion=comunicados&guardado=1");

                    exit;

                }



                $stmt->close();

            }



            $mensaje = 'No se pudo publicar el comunicado.';

            $error = true;

        }

    }

}



if (isset($_GET['guardado'])) {

    $mensaje = '¡Información guardada correctamente!';

}



/* CONSULTAS */



$miembros = [];

$reuniones = [];

$comunicados = [];



if ($seccion === 'miembros') {

    $resultado = $conn->query(

        "SELECT nombre_completo, email, contacto, fecha_registro

         FROM usuarios

         WHERE id_rol = 2

         ORDER BY nombre_completo"

    );



    if ($resultado) {

        $miembros = $resultado->fetch_all(MYSQLI_ASSOC);

    }

}



$actividades = [];
if (in_array($seccion, ['agenda', 'reuniones', 'historial'], true)) {
    $resultado = $conn->query("SELECT titulo, descripcion, fecha, hora, lugar, estado, tipo FROM actividades ORDER BY fecha DESC, hora DESC");
    if ($resultado) {
        $actividades = $resultado->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($actividades as $actividad) {
        if (in_array($actividad['tipo'], ['Reunión', 'Reunion', 'reunión', 'reunion'], true)) {
            $reuniones[] = $actividad;
        }
    }
}

$mesFiltro = $_GET['mes'] ?? '';
$anioFiltro = $_GET['anio'] ?? '';
if ($mesFiltro !== '' && (!ctype_digit((string)$mesFiltro) || (int)$mesFiltro < 1 || (int)$mesFiltro > 12)) $mesFiltro = '';
if ($anioFiltro !== '' && (!preg_match('/^\d{4}$/', (string)$anioFiltro))) $anioFiltro = '';
$historial = [];
if ($seccion === 'historial') {
    $hoy = date('Y-m-d');
    foreach ($actividades as $actividad) {
        if ($actividad['fecha'] >= $hoy) continue;
        if ($mesFiltro !== '' && (int)date('n', strtotime($actividad['fecha'])) !== (int)$mesFiltro) continue;
        if ($anioFiltro !== '' && date('Y', strtotime($actividad['fecha'])) !== (string)$anioFiltro) continue;
        $historial[] = $actividad;
    }
}

if (in_array($seccion, ['comunicados', 'novedades'], true)) {

    $resultado = $conn->query(

        "SELECT titulo, contenido, categoria,

                fecha_publicacion, importante

         FROM comunicados

         ORDER BY fecha_publicacion DESC, id_comunicado DESC"

    );



    if ($resultado) {

        $comunicados = $resultado->fetch_all(MYSQLI_ASSOC);

    }

}



$colores = ['menta', 'azul', 'crema', 'lila', 'rosado', 'durazno'];

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Administrador | Tejido Comunal</title>



<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">



<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>



<style>

:root {

    --verde: #153f2b;

    --verde-claro: #17643b;

    --fondo: #f8faf8;

    --texto: #304637;

    --menta: #dff2e5;

    --azul: #e3eefa;

    --crema: #fff0d8;

    --lila: #f0e6f8;

    --rosado: #fce6ec;

    --durazno: #ffe9da;

}



* {

    box-sizing: border-box;

}



body {

    margin: 0;

    background: var(--fondo);

    color: var(--texto);

    font-family: 'Nunito', Arial, sans-serif;

}



button, input, textarea, select {

    font-family: inherit;

}



.contenedor {

    display: flex;

    min-height: 100vh;

}



.sidebar {

    position: fixed;

    top: 0;

    left: 0;

    bottom: 0;

    width: 275px;

    padding: 25px 15px;

    background: var(--verde);

    color: white;

    overflow-y: auto;

}



.marca {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 8px 10px 28px;

}



.logo {

    display: grid;

    place-items: center;

    width: 50px;

    height: 50px;

    flex-shrink: 0;

    border-radius: 13px;

    background: #e7f1e7;

    color: var(--verde);

}



.logo svg {

    width: 29px;

    height: 29px;

}



.marca h2 {

    margin: 0;

    font-size: 19px;

    font-weight: 900;

}



.marca small {

    color: #c6dcce;

}



.menu {

    display: flex;

    flex-direction: column;

    gap: 7px;

}



.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 14px;

    border-radius: 10px;

    color: white;

    text-decoration: none;

    font-size: 15px;

    font-weight: 800;

    transition: background .2s;

}



.menu a:hover,

.menu a.activo {

    background: #30744e;

}



.menu .icono {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 24px;

    min-width: 24px;

    height: 24px;

}



.menu .icono svg {

    width: 21px;

    height: 21px;

    stroke-width: 1.9;

}



.menu .salir {

    margin-top: 18px;

    padding-top: 21px;

    border-top: 1px solid #47785a;

    border-radius: 0;

}



.principal {

    margin-left: 275px;

    width: calc(100% - 275px);

    min-width: 0;

    padding: 27px 30px;

}



.encabezado {

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 15px;

    padding-bottom: 20px;

    margin-bottom: 28px;

    border-bottom: 1px solid #dfe8e0;

}



.encabezado h2 {

    margin: 0;

    color: var(--verde);

    font-size: 25px;

    font-weight: 900;

}



.encabezado p {

    margin: 5px 0 0;

    color: #718575;

    font-size: 14px;

}



.identidad { display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
.rol-admin { color:var(--verde-claro); font-size:12px; font-weight:900; letter-spacing:1px; }
.nombre {

    padding: 12px 17px;

    border-radius: 25px;

    background: #eef3ef;

    color: #111;

    font-weight: 900;

    overflow-wrap: anywhere;

}



.titulo {

    margin: 0 0 10px;

    color: var(--verde);

    font-size: 32px;

    font-weight: 900;

}



.subtitulo {

    margin: 0 0 25px;

    color: #607967;

    line-height: 1.6;

}



.menta { background: var(--menta); }

.azul { background: var(--azul); }

.crema { background: var(--crema); }

.lila { background: var(--lila); }

.rosado { background: var(--rosado); }

.durazno { background: var(--durazno); }



.columnas {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 20px;

}



.panel {

    padding: 25px;

    border-radius: 17px;

    margin-bottom: 22px;

    line-height: 1.7;

}



.panel h2 {

    margin-top: 0;

    color: var(--verde);

}



.tarjeta {

    overflow: hidden;

    border: 1px solid #e0e8e1;

    border-radius: 17px;

    background: white;

    box-shadow: 0 5px 18px rgba(20, 60, 35, .05);

}



.tarjeta-cabecera {

    min-height: 125px;

    padding: 23px;

}



.tarjeta-cabecera h3 {

    margin: 12px 0 0;

    color: #244b36;

    font-size: 21px;

    font-weight: 900;

}



.tarjeta-cuerpo {

    padding: 22px;

    line-height: 1.7;

    overflow-wrap: anywhere;

}



.tarjeta-cuerpo p {

    margin: 0 0 12px;

}



.etiqueta {

    display: inline-block;

    padding: 7px 12px;

    border-radius: 8px;

    background: white;

    font-size: 13px;

    font-weight: 900;

}



.boton {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 12px 17px;

    border: none;

    border-radius: 10px;

    background: var(--verde-claro);

    color: white;

    font-size: 15px;

    font-weight: 900;

    text-decoration: none;

    cursor: pointer;

}



.boton:hover {

    background: #104d2d;

}



.boton svg {

    width: 18px;

    height: 18px;

}



.filtros-historial { display:flex; align-items:flex-end; flex-wrap:wrap; gap:14px; background:#fff; padding:22px; border-radius:16px; margin-bottom:22px; }
.filtros-historial .campo { flex:1 1 170px; margin:0; }
.boton-secundario { background:#e4f0e7; color:#153f2b; }
.boton-secundario:hover { background:#d1e5d6; }
.detalle-historial { margin-top:14px; padding:12px; background:#f4faf5; border-radius:10px; }
.formulario {

    display: none;

    max-width: 800px;

    margin: 20px 0;

    padding: 25px;

    border-radius: 17px;

    background: var(--lila);

}



.formulario.visible {

    display: block;

}



.campo {

    margin-bottom: 17px;

}



.campo label {

    display: block;

    margin-bottom: 7px;

    font-weight: 900;

}



.campo input:not([type="checkbox"]),

.campo textarea,

.campo select {

    width: 100%;

    padding: 12px;

    border: 1px solid #cad8cd;

    border-radius: 10px;

    background: white;

    font-size: 16px;

}



.campo textarea {

    min-height: 120px;

    resize: vertical;

}



.alerta {

    margin-bottom: 20px;

    padding: 17px;

    border-radius: 12px;

    background: var(--menta);

}



.alerta.error {

    background: #ffe0e0;

}



.aviso {

    margin-top: 20px;

    padding: 25px;

    border-radius: 15px;

    background: var(--crema);

    line-height: 1.7;

}



.tabla-contenedor {

    overflow-x: auto;

    border-radius: 15px;

}



.tabla {

    width: 100%;

    border-collapse: collapse;

    background: white;

}



.tabla th,

.tabla td {

    padding: 16px;

    border-bottom: 1px solid #e3eae4;

    text-align: left;

}



.tabla th {

    background: var(--menta);

}



.tabla tbody tr:nth-child(6n + 1) { background: #f4faf5; }

.tabla tbody tr:nth-child(6n + 2) { background: #f2f7fe; }

.tabla tbody tr:nth-child(6n + 3) { background: #fff9ed; }

.tabla tbody tr:nth-child(6n + 4) { background: #f9f3fd; }

.tabla tbody tr:nth-child(6n + 5) { background: #fff3f6; }

.tabla tbody tr:nth-child(6n + 6) { background: #fff5ee; }



.carrusel {

    overflow: hidden;

    border-radius: 20px;

    background: white;

    box-shadow: 0 12px 35px rgba(20, 60, 35, .1);

}



.foto {

    display: block;

    width: 100%;

    height: clamp(390px, 56vh, 680px);

    object-fit: cover;

}



.carrusel-pie {

    padding: 20px;

    text-align: center;

}



.carrusel-pie h3 {

    margin: 0 0 17px;

    color: var(--verde);

}



.controles {

    display: flex;

    align-items: center;

    justify-content: space-between;

}



.flecha {

    width: 45px;

    height: 45px;

    border: 1px solid #dce6de;

    border-radius: 50%;

    background: white;

    color: var(--verde);

    font-size: 20px;

    cursor: pointer;

}



.puntos {

    display: flex;

    gap: 10px;

}



.punto {

    width: 13px;

    height: 13px;

    border: none;

    border-radius: 50%;

    background: #cad8cd;

    cursor: pointer;

}



.punto.activo {

    background: var(--verde-claro);

}



.lema {

    margin-top: 25px;

    color: var(--verde);

    font-size: 20px;

    font-weight: 900;

    text-align: center;

}



.codigo-panel {

    max-width: 850px;

    padding: 28px;

    border-radius: 18px;

    background: var(--lila);

    line-height: 1.7;

}



.codigo-caja {

    margin: 20px 0;

    padding: 25px;

    border-radius: 15px;

    background: white;

    text-align: center;

}



.codigo-estado {

    color: var(--verde);

    font-size: 21px;

    font-weight: 900;

}



@media (max-width: 1100px) {

    .columnas {

        grid-template-columns: repeat(2, minmax(0, 1fr));

    }

}



@media (max-width: 700px) {

    .contenedor {

        display: block;

    }



    .sidebar {

        position: relative;

        width: 100%;

        padding: 15px;

    }



    .marca {

        padding-bottom: 12px;

    }



    .menu {

        flex-direction: row;

        overflow-x: auto;

    }



    .menu a {

        flex-shrink: 0;

        padding: 12px;

        white-space: nowrap;

    }



    .menu .salir {

        margin-top: 0;

        padding-top: 12px;

        border: none;

    }



    .principal {

        width: 100%;

        margin-left: 0;

        padding: 20px 15px;

    }



    .columnas {

        grid-template-columns: 1fr;

    }



    .foto {

        height: 290px;

    }



    .titulo {

        font-size: 27px;

    }

}

</style>

</head>



<body>

<div class="contenedor">



<aside class="sidebar">

    <div class="marca">

        <div class="logo">

            <i data-lucide="sprout"></i>

        </div>

        <div>

            <h2>Tejido Comunal</h2>

            <small>Las Margaritas</small>

        </div>

    </div>



    <nav class="menu">

        <?php foreach ($menu as $clave => $datos): ?>

            <a

                href="dashboard.php?seccion=<?= e($clave) ?>"

                class="<?= $seccion === $clave ? 'activo' : '' ?>"

            >

                <span class="icono">

                    <i data-lucide="<?= e($datos[1]) ?>"></i>

                </span>

                <?= e($datos[0]) ?>

            </a>

        <?php endforeach; ?>



        <a href="cerrar_sesion.php" class="salir">

            <span class="icono">

                <i data-lucide="log-out"></i>

            </span>

            Cerrar sesión

        </a>

    </nav>

</aside>



<main class="principal">

    <header class="encabezado">

        <div>

            <h2><?= e($menu[$seccion][0]) ?></h2>

            <p>Junta de Acción Comunal · Barrio Las Margaritas</p>

        </div>

        <div class="identidad"><span class="rol-admin">ADMINISTRADOR</span><div class="nombre"><?= e($nombre) ?></div></div>

    </header>



    <?php if ($mensaje !== ''): ?>

        <div class="alerta <?= $error ? 'error' : '' ?>">

            <?= e($mensaje) ?>

        </div>

    <?php endif; ?>



    <?php if ($seccion === 'inicio'): ?>



        <h1 class="titulo" style="text-align:center">

            ¡Bienvenidos a Tejido Comunal!

        </h1>



        <p class="subtitulo" style="text-align:center">

            Panel de Administración del Barrio Las Margaritas

        </p>



        <div class="carrusel">

            <img

                id="fotoCarrusel"

                class="foto"

                src="imagenes/comunidad1.png"

                alt="Nuestra comunidad"

            >



            <div class="carrusel-pie">

                <h3 id="textoCarrusel">Nuestra comunidad</h3>



                <div class="controles">

                    <button type="button" class="flecha" onclick="cambiarFoto(-1)" aria-label="Foto anterior">❮</button>



                    <div class="puntos">

                        <button type="button" class="punto activo" onclick="irFoto(0)" aria-label="Foto 1"></button>

                        <button type="button" class="punto" onclick="irFoto(1)" aria-label="Foto 2"></button>

                        <button type="button" class="punto" onclick="irFoto(2)" aria-label="Foto 3"></button>

                    </div>



                    <button type="button" class="flecha" onclick="cambiarFoto(1)" aria-label="Foto siguiente">❯</button>

                </div>

            </div>

        </div>



        <p class="lema">Unidos construimos comunidad.</p>



        <div class="columnas" style="margin-top:30px">

            <div class="panel menta">

                <h2>Miembros</h2>

                <p>Consulta las personas registradas en la comunidad.</p>

                <a class="boton" href="dashboard.php?seccion=miembros">Ver miembros</a>

            </div>



            <div class="panel azul">

                <h2>Reuniones</h2>

                <p>Programa los próximos encuentros comunitarios.</p>

                <a class="boton" href="dashboard.php?seccion=reuniones">Ver reuniones</a>

            </div>



            <div class="panel rosado">

                <h2>Comunicados</h2>

                <p>Publica información para los vecinos.</p>

                <a class="boton" href="dashboard.php?seccion=comunicados">Ver comunicados</a>

            </div>

        </div>



    <?php elseif ($seccion === 'miembros'): ?>



        <h1 class="titulo">Miembros de la comunidad</h1>

        <p class="subtitulo">Personas registradas en Tejido Comunal.</p>



        <div class="tabla-contenedor">

            <table class="tabla">

                <thead>

                    <tr>

                        <th>Nombre completo</th>

                        <th>Correo</th>

                        <th>Contacto</th>

                        <th>Registro</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($miembros as $miembro): ?>

                        <tr>

                            <td><?= e($miembro['nombre_completo']) ?></td>

                            <td><?= e($miembro['email']) ?></td>

                            <td><?= e($miembro['contacto']) ?></td>

                            <td><?= e($miembro['fecha_registro']) ?></td>

                        </tr>

                    <?php endforeach; ?>



                    <?php if (empty($miembros)): ?>

                        <tr>

                            <td colspan="4">Todavía no hay miembros registrados.</td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>



    <?php elseif ($seccion === 'agenda'): ?>
        <h1 class="titulo">Agenda comunitaria</h1>
        <p class="subtitulo">Organiza las actividades del barrio y las reservas del salón comunal.</p>
        <button type="button" class="boton" onclick="mostrarFormulario('formEvento')"><i data-lucide="plus"></i> Crear evento o reserva</button>
        <div id="formEvento" class="formulario">
            <h2>Nuevo evento o alquiler del salón</h2>
            <form method="POST" action="dashboard.php?seccion=agenda">
                <input type="hidden" name="accion" value="crear_evento">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_dashboard']) ?>">
                <div class="campo"><label for="tipoEvento">Tipo</label><select id="tipoEvento" name="tipo"><option value="Actividad">Actividad comunitaria</option><option value="Alquiler">Alquiler del salón comunal</option></select></div>
                <div class="campo"><label for="tituloEvento">Nombre del evento</label><input id="tituloEvento" type="text" name="titulo" maxlength="200" placeholder="Ej. Fiesta de 15 años" required></div>
                <div class="campo"><label for="descripcionEvento">Descripción</label><textarea id="descripcionEvento" name="descripcion" placeholder="Detalles de la actividad o reserva"></textarea></div>
                <div class="campo"><label for="fechaEvento">Fecha</label><input id="fechaEvento" type="date" name="fecha" required></div>
                <div class="campo"><label for="horaEvento">Hora de inicio</label><input id="horaEvento" type="time" name="hora" required></div>
                <div class="campo"><label for="lugarEvento">Lugar</label><input id="lugarEvento" type="text" name="lugar" value="Salón comunal" maxlength="200" required></div>
                <p>Para evitar reservas duplicadas, cada fecha admite un solo alquiler del salón.</p>
                <button type="submit" class="boton">Guardar evento</button>
            </form>
        </div>
        <?php if (!empty($actividades)): ?>
            <div class="columnas" style="margin-top:25px">
                <?php foreach (array_reverse($actividades) as $i => $actividad): ?>
                    <article class="tarjeta">
                        <div class="tarjeta-cabecera <?= $colores[$i % 6] ?>">
                            <span class="etiqueta"><?= e($actividad['tipo']) ?></span>
                            <h3><?= e($actividad['titulo']) ?></h3>
                        </div>
                        <div class="tarjeta-cuerpo">
                            <p><strong>Fecha:</strong> <?= e($actividad['fecha']) ?></p>
                            <p><strong>Hora:</strong> <?= e($actividad['hora']) ?></p>
                            <p><strong>Lugar:</strong> <?= e($actividad['lugar']) ?></p>
                            <p><strong>Estado:</strong> <?= e($actividad['estado']) ?></p>
                            <p><?= nl2br(e($actividad['descripcion'])) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="aviso">Todavía no hay eventos registrados. Usa «Crear evento o reserva» para agregar el primero.</div>
        <?php endif; ?>

    <?php elseif ($seccion === 'reuniones'): ?>



        <h1 class="titulo">Reuniones</h1>

        <p class="subtitulo">Crea y consulta los encuentros comunitarios.</p>



        <button type="button" class="boton" onclick="mostrarFormulario('formReunion')">

            <i data-lucide="plus"></i> Crear reunión

        </button>



        <div id="formReunion" class="formulario">

            <h2>Nueva reunión</h2>



            <form method="POST" action="dashboard.php?seccion=reuniones">

                <input type="hidden" name="accion" value="crear_reunion">

                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_dashboard']) ?>">



                <div class="campo">

                    <label for="tituloReunion">Título</label>

                    <input id="tituloReunion" type="text" name="titulo" maxlength="200" required>

                </div>



                <div class="campo">

                    <label for="descripcionReunion">Descripción</label>

                    <textarea id="descripcionReunion" name="descripcion"></textarea>

                </div>



                <div class="campo">

                    <label for="fechaReunion">Fecha</label>

                    <input id="fechaReunion" type="date" name="fecha" required>

                </div>



                <div class="campo">

                    <label for="horaReunion">Hora</label>

                    <input id="horaReunion" type="time" name="hora" required>

                </div>



                <div class="campo">

                    <label for="lugarReunion">Lugar</label>

                    <input id="lugarReunion" type="text" name="lugar" maxlength="200" required>

                </div>



                <button type="submit" class="boton">Guardar reunión</button>

            </form>

        </div>



        <?php if (!empty($reuniones)): ?>

            <div class="columnas" style="margin-top:25px">

                <?php foreach ($reuniones as $i => $reunion): ?>

                    <article class="tarjeta">

                        <div class="tarjeta-cabecera <?= $colores[$i % 6] ?>">

                            <span class="etiqueta">Reunión comunitaria</span>

                            <h3><?= e($reunion['titulo']) ?></h3>

                        </div>

                        <div class="tarjeta-cuerpo">

                            <p><strong>Fecha:</strong> <?= e($reunion['fecha']) ?></p>

                            <p><strong>Hora:</strong> <?= e($reunion['hora']) ?></p>

                            <p><strong>Lugar:</strong> <?= e($reunion['lugar']) ?></p>

                            <p><strong>Estado:</strong> <?= e($reunion['estado']) ?></p>

                            <p><?= nl2br(e($reunion['descripcion'])) ?></p>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="aviso">Todavía no hay reuniones registradas.</div>

        <?php endif; ?>



    <?php elseif ($seccion === 'asistencias'): ?>



        <h1 class="titulo">Asistencias</h1>

        <p class="subtitulo">Control de participación de los vecinos.</p>



        <div class="columnas">

            <div class="panel menta">

                <h2>Personas presentes</h2>

                <p>Aquí aparecerán las personas que registren su asistencia a las reuniones.</p>

            </div>



            <div class="panel rosado">

                <h2>Personas ausentes</h2>

                <p>Aquí aparecerán las personas ausentes cuando tengamos información suficiente para identificarlas.</p>

            </div>



            <div class="panel azul">

                <h2>Código QR</h2>

                <p>Aquí configuraremos el registro de asistencia mediante código QR.</p>

            </div>

        </div>



        <div class="aviso">

            Este módulo está pendiente de conectar con los registros reales de asistencia.

        </div>



    <?php elseif ($seccion === 'tareas'): ?>



        <h1 class="titulo">Tareas comunitarias</h1>

        <p class="subtitulo">Organización de actividades y responsabilidades.</p>



        <div class="columnas">

            <article class="tarjeta">

                <div class="tarjeta-cabecera menta">

                    <span class="etiqueta">Ejemplo</span>

                    <h3>Pintemos nuestro mural</h3>

                </div>

                <div class="tarjeta-cuerpo">

                    <p>Espacio para organizar la participación de los vecinos en el mural comunitario.</p>

                </div>

            </article>



            <article class="tarjeta">

                <div class="tarjeta-cabecera azul">

                    <span class="etiqueta">Ejemplo</span>

                    <h3>Limpieza de las sillas</h3>

                </div>

                <div class="tarjeta-cuerpo">

                    <p>Espacio para coordinar el cuidado del salón comunal.</p>

                </div>

            </article>



            <article class="tarjeta">

                <div class="tarjeta-cabecera durazno">

                    <span class="etiqueta">Ejemplo</span>

                    <h3>Organización de la oficina</h3>

                </div>

                <div class="tarjeta-cuerpo">

                    <p>Espacio para distribuir las tareas de organización de la oficina.</p>

                </div>

            </article>

        </div>



        <div class="aviso">

            Estas tarjetas son ejemplos. La asignación de tareas se conectará con la tabla

            <strong>tareas</strong> cuando revisemos sus columnas.

        </div>



    <?php elseif ($seccion === 'comunicados'): ?>



        <h1 class="titulo">Comunicados</h1>

        <p class="subtitulo">Publica avisos importantes para la comunidad.</p>



        <button type="button" class="boton" onclick="mostrarFormulario('formComunicado')">

            <i data-lucide="plus"></i> Crear comunicado

        </button>



        <div id="formComunicado" class="formulario">

            <h2>Nuevo comunicado</h2>



            <form method="POST" action="dashboard.php?seccion=comunicados">

                <input type="hidden" name="accion" value="crear_comunicado">

                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_dashboard']) ?>">



                <div class="campo">

                    <label for="tituloComunicado">Título</label>

                    <input id="tituloComunicado" type="text" name="titulo" maxlength="200" required>

                </div>



                <div class="campo">

                    <label for="contenidoComunicado">Contenido</label>

                    <textarea id="contenidoComunicado" name="contenido" required></textarea>

                </div>



                <div class="campo">

                    <label for="categoriaComunicado">Categoría</label>

                    <select id="categoriaComunicado" name="categoria">

                        <option value="General">General</option>

                        <option value="Importante">Importante</option>

                        <option value="Comunidad">Comunidad</option>

                    </select>

                </div>



                <div class="campo">

                    <label>

                        <input type="checkbox" name="importante" value="1">

                        Marcar como importante

                    </label>

                </div>



                <button type="submit" class="boton">Publicar comunicado</button>

            </form>

        </div>



        <?php if (!empty($comunicados)): ?>

            <div class="columnas" style="margin-top:25px">

                <?php foreach ($comunicados as $i => $comunicado): ?>

                    <article class="tarjeta">

                        <div class="tarjeta-cabecera <?= $colores[$i % 6] ?>">

                            <span class="etiqueta">

                                <?= !empty($comunicado['importante']) ? 'Importante' : 'Comunicado' ?>

                            </span>

                            <h3><?= e($comunicado['titulo']) ?></h3>

                        </div>

                        <div class="tarjeta-cuerpo">

                            <p><strong>Categoría:</strong> <?= e($comunicado['categoria']) ?></p>

                            <p><strong>Publicado:</strong> <?= e($comunicado['fecha_publicacion']) ?></p>

                            <p><?= nl2br(e($comunicado['contenido'])) ?></p>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="aviso">Todavía no hay comunicados publicados.</div>

        <?php endif; ?>



    <?php elseif ($seccion === 'novedades'): ?>



        <h1 class="titulo">Novedades</h1>

        <p class="subtitulo">Últimas noticias y comunicados de la comunidad.</p>
        <button type="button" class="boton" onclick="mostrarFormulario('formNovedad')"><i data-lucide="plus"></i> Crear novedad</button>
        <div id="formNovedad" class="formulario">
            <h2>Publicar nueva novedad</h2>
            <form method="POST" action="dashboard.php?seccion=novedades">
                <input type="hidden" name="accion" value="crear_novedad">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_dashboard']) ?>">
                <div class="campo"><label for="tituloNovedad">Título</label><input id="tituloNovedad" type="text" name="titulo" maxlength="200" required></div>
                <div class="campo"><label for="contenidoNovedad">¿Qué pasó en el barrio?</label><textarea id="contenidoNovedad" name="contenido" required></textarea></div>
                <div class="campo"><label><input type="checkbox" name="importante" value="1"> Marcar como importante</label></div>
                <button type="submit" class="boton">Publicar novedad</button>
            </form>
        </div>




        <?php if (!empty($comunicados)): ?>

            <div class="columnas">

                <?php foreach ($comunicados as $i => $comunicado): ?>

                    <article class="tarjeta">

                        <div class="tarjeta-cabecera <?= $colores[$i % 6] ?>">

                            <span class="etiqueta">Novedad</span>

                            <h3><?= e($comunicado['titulo']) ?></h3>

                        </div>

                        <div class="tarjeta-cuerpo">

                            <p><?= e($comunicado['fecha_publicacion']) ?></p>

                            <p><?= nl2br(e($comunicado['contenido'])) ?></p>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="aviso">Todavía no hay novedades publicadas.</div>

        <?php endif; ?>



    <?php elseif ($seccion === 'historial'): ?>
        <h1 class="titulo">Historial comunitario</h1>
        <p class="subtitulo">Consulta las actividades y los alquileres que ya ocurrieron.</p>
        <form method="GET" action="dashboard.php" class="filtros-historial">
            <input type="hidden" name="seccion" value="historial">
            <div class="campo"><label for="mesHistorial">Mes</label>
                <select id="mesHistorial" name="mes">
                    <option value="">Todos los meses</option>
                    <?php foreach (['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $indice => $mesNombre): ?>
                        <option value="<?= $indice + 1 ?>" <?= (string)$mesFiltro === (string)($indice + 1) ? 'selected' : '' ?>><?= e($mesNombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="anioHistorial">Año</label>
                <select id="anioHistorial" name="anio">
                    <option value="">Todos los años</option>
                    <?php for ($a = (int)date('Y') + 1; $a >= 2020; $a--): ?>
                        <option value="<?= $a ?>" <?= (string)$anioFiltro === (string)$a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="boton"><i data-lucide="search"></i> Buscar</button>
            <a href="dashboard.php?seccion=historial" class="boton boton-secundario">Ver todo</a>
        </form>
        <p><strong><?= count($historial) ?></strong> registros encontrados.</p>
        <?php if (!empty($historial)): ?>
            <div class="columnas">
                <?php foreach ($historial as $i => $actividad): ?>
                    <article class="tarjeta">
                        <div class="tarjeta-cabecera <?= $colores[$i % 6] ?>">
                            <span class="etiqueta"><?= e($actividad['tipo']) ?></span>
                            <h3><?= e($actividad['titulo']) ?></h3>
                        </div>
                        <div class="tarjeta-cuerpo">
                            <p><strong>Fecha:</strong> <?= e($actividad['fecha']) ?></p>
                            <p><strong>Estado:</strong> <?= e($actividad['estado']) ?></p>
                            <button type="button" class="boton boton-secundario" onclick="mostrarDetalles(this)">Ver detalles</button>
                            <div class="detalle-historial" hidden>
                                <p><strong>Hora:</strong> <?= e($actividad['hora']) ?></p>
                                <p><strong>Lugar:</strong> <?= e($actividad['lugar']) ?></p>
                                <p><?= nl2br(e($actividad['descripcion'])) ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="aviso">No hay registros anteriores para el mes y año seleccionados.</div>
        <?php endif; ?>

    <?php elseif ($seccion === 'codigo'): ?>



        <h1 class="titulo">Código de autorización</h1>

        <p class="subtitulo">Espacio privado para autorizar nuevos administradores.</p>



        <div class="codigo-panel">

            <h2>Autorización de administradores</h2>



            <p>

                Aquí podrás consultar el código de autorización que se renovará

                automáticamente cada 24 horas.

            </p>



            <div class="codigo-caja">

                <i

                    data-lucide="shield-check"

                    style="width:40px;height:40px;color:#17643b"

                ></i>



                <p class="codigo-estado">Pendiente de configuración</p>



                <p>

                    El código todavía no está activado.

                </p>

            </div>



            <p>

                Una vez conectado con el registro, únicamente los administradores

                que hayan iniciado sesión podrán consultar el código vigente.

            </p>



            <p>

                Los administradores ya registrados seguirán entrando con su

                correo y contraseña, aunque el código cambie.

            </p>

        </div>



        <div class="panel menta" style="max-width:850px;margin-top:20px">

            <h2>¿Cómo funcionará?</h2>



            <p>

                Un administrador autorizado consultará el código vigente y

                lo compartirá con la persona que necesite registrarse

                como nuevo administrador.

            </p>



            <p>

                El sistema verificará el código durante el registro y limitará

                los intentos incorrectos.

            </p>

        </div>



    <?php endif; ?>



</main>

</div>



<script>

document.addEventListener('DOMContentLoaded', function () {

    if (window.lucide) {

        lucide.createIcons();

    }

});



function mostrarDetalles(boton) {
    const detalle = boton.nextElementSibling;
    if (!detalle) return;
    detalle.hidden = !detalle.hidden;
    boton.textContent = detalle.hidden ? 'Ver detalles' : 'Ocultar detalles';
}

function mostrarFormulario(id) {

    const formulario = document.getElementById(id);



    if (formulario) {

        formulario.classList.toggle('visible');

    }

}



const fotos = [

    'imagenes/comunidad1.png',

    'imagenes/comunidad2.png',

    'imagenes/comunidad3.png'

];



const textos = [

    'Nuestra comunidad',

    'Juntos somos más fuertes',

    'Construimos un mejor barrio'

];



let fotoActual = 0;

let temporizador = null;



function mostrarFoto() {

    const imagen = document.getElementById('fotoCarrusel');

    const titulo = document.getElementById('textoCarrusel');



    if (!imagen || !titulo) {

        return;

    }



    imagen.src = fotos[fotoActual];

    titulo.textContent = textos[fotoActual];



    document.querySelectorAll('.punto').forEach(function (punto, indice) {

        punto.classList.toggle('activo', indice === fotoActual);

    });

}



function avanzarFoto(direccion) {

    fotoActual = (fotoActual + direccion + fotos.length) % fotos.length;

    mostrarFoto();

}



function cambiarFoto(direccion) {

    avanzarFoto(direccion);

    reiniciarCarrusel();

}



function irFoto(indice) {

    fotoActual = indice;

    mostrarFoto();

    reiniciarCarrusel();

}



function iniciarCarrusel() {

    if (!document.getElementById('fotoCarrusel')) {

        return;

    }



    temporizador = setInterval(function () {

        if (!document.hidden) {

            avanzarFoto(1);

        }

    }, 5000);

}



function reiniciarCarrusel() {

    clearInterval(temporizador);

    iniciarCarrusel();

}



iniciarCarrusel();

</script>



</body>

</html>