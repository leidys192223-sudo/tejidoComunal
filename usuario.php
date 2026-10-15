<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

require_once "conexion.php";
require_once __DIR__ . "/gestion_comunal.php";

function e($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$idUsuario = (int) $_SESSION['id_usuario'];

$stmt = $conn->prepare(
    "SELECT u.nombre_completo, u.email, u.contacto,
            u.direccion_hogar, s.nombre_sector
     FROM usuarios u
     LEFT JOIN sectores s ON u.id_sector = s.id_sector
     WHERE u.id_usuario = ? AND u.id_rol = 2
     LIMIT 1"
);

$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: login.php");
    exit;
}

$nombre = $usuario['nombre_completo'];


$reuniones = [];

$resultado = $conn->query(
    "SELECT id_actividad, titulo, descripcion, fecha, hora, lugar
     FROM actividades
     WHERE tipo = 'reunion'
     ORDER BY fecha DESC, hora DESC
     LIMIT 30"
);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $reuniones[] = $fila;
    }
}


$comunicados = [];
$resultado = $conn->query(
    "SELECT id_comunicado, titulo, contenido, categoria,
            fecha_publicacion, importante
     FROM comunicados
     ORDER BY fecha_publicacion DESC
     LIMIT 30"
);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $comunicados[] = $fila;
    }
}

/*
 * Asistencias:
 * Consultamos registros del usuario únicamente si las
 * columnas necesarias existen en la base de datos.
 */
$asistencias = [];
$asistenciasDisponibles = false;

$columnasAsistencia = [];
$resultadoColumnas = $conn->query(
    "SHOW COLUMNS FROM asistencia_actividades"
);

if ($resultadoColumnas) {
    while ($columna = $resultadoColumnas->fetch_assoc()) {
        $columnasAsistencia[] = $columna['Field'];
    }

    if (
        in_array('id_usuario', $columnasAsistencia, true) &&
        in_array('id_actividad', $columnasAsistencia, true)
    ) {
        $asistenciasDisponibles = true;

        $stmt = $conn->prepare(
            "SELECT a.titulo, a.fecha, a.hora, a.lugar
             FROM asistencia_actividades aa
             INNER JOIN actividades a
                ON a.id_actividad = aa.id_actividad
             WHERE aa.id_usuario = ?
             ORDER BY a.fecha DESC"
        );

        if ($stmt) {
            $stmt->bind_param("i", $idUsuario);
            $stmt->execute();
            $resultado = $stmt->get_result();

            while ($fila = $resultado->fetch_assoc()) {
                $asistencias[] = $fila;
            }

            $stmt->close();
        }
    }
}



$eventosAgenda = [];

$sqlAgendaUsuario = "
    SELECT id_actividad, titulo, tipo, fecha,
           hora, lugar, descripcion
    FROM actividades
    ORDER BY fecha ASC, hora ASC
";

$resultadoAgendaUsuario = $conn->query($sqlAgendaUsuario);

if ($resultadoAgendaUsuario) {
    while ($actividad = $resultadoAgendaUsuario->fetch_assoc()) {
        $eventosAgenda[] = $actividad;
    }

    $resultadoAgendaUsuario->free();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Usuario | Tejido Comunal</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root {
    --verde-oscuro: #153f2b;
    --verde: #17643b;
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
    font-size: 16px;
}

button {
    font-family: inherit;
    cursor: pointer;
}

.contenedor {
    display: flex;
    min-height: 100vh;
}

/* MENÚ */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 275px;
    background: var(--verde-oscuro);
    padding: 25px 15px;
    overflow-y: auto;
    color: white;
}

.marca {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px 30px;
}

.logo {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    border-radius: 13px;
    background: #e7f1e7;
    font-size: 27px;
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
    gap: 22px;
}


.menu button,
.menu a {
    display: block;
    width: 100%;
    border: none;
    background: transparent;
    color: white;
    padding: 15px 14px;
    border-radius: 10px;
    text-align: left;
    text-decoration: none;
    font-size: 16px;
    font-weight: 800;
}

.menu button:hover,
.menu a:hover,
.menu .activo {
    background: #30744e;
}

.icono {
    display: inline-block;
    width: 30px;
}

/* CONTENIDO PRINCIPAL */

.principal {
    width: calc(100% - 275px);
    min-width: 0;
    margin-left: 275px;
    padding: 27px 30px;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dfe8e0;
    margin-bottom: 28px;
}

.encabezado h2 {
    margin: 0;
    color: var(--verde-oscuro);
    font-size: 25px;
    font-weight: 900;
}

.encabezado p {
    margin: 5px 0 0;
    color: #718575;
    font-size: 14px;
}

.nombre-usuario {
    background: #eef3ef;
    color: #111;
    border-radius: 25px;
    padding: 12px 17px;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.seccion {
    display: none;
}

.seccion.visible {
    display: block;
}

.titulo {
    margin: 0 0 10px;
    color: var(--verde-oscuro);
    font-size: 32px;
    font-weight: 900;
}

.subtitulo {
    margin: 0 0 25px;
    color: #607967;
    font-size: 17px;
    line-height: 1.6;
}

/* COLORES PASTEL */

.menta {
    background: var(--menta);
}

.azul {
    background: var(--azul);
}

.crema {
    background: var(--crema);
}

.lila {
    background: var(--lila);
}

.rosado {
    background: var(--rosado);
}

.durazno {
    background: var(--durazno);
}

.columnas {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.tarjeta {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 17px;
    border: 1px solid #e0e8e1;
    background: white;
    box-shadow: 0 5px 18px rgba(20,60,35,.05);
}

.tarjeta-cabecera {
    padding: 23px;
    min-height: 125px;
}

.tarjeta-cabecera h3 {
    margin: 12px 0 0;
    color: #244b36;
    font-size: 21px;
    font-weight: 900;
}

.tarjeta-cuerpo {
    padding: 22px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 13px;
}

.tarjeta-cuerpo p {
    margin: 0;
    line-height: 1.7;
}

.etiqueta {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 8px;
    background: white;
    color: #244b36;
    font-size: 13px;
    font-weight: 900;
}

.boton {
    display: inline-block;
    margin-top: 12px;
    border: none;
    border-radius: 10px;
    background: var(--verde);
    color: white;
    padding: 12px 17px;
    font-weight: 900;
    font-size: 15px;
}

.boton:hover {
    background: #104d2d;
}

.detalle {
    display: none;
    padding: 15px;
    margin-top: 12px;
    border-radius: 10px;
    background: #f4f8f5;
    line-height: 1.7;
}

.detalle.abierto {
    display: block;
}

.aviso {
    padding: 24px;
    border-radius: 15px;
    background: var(--crema);
    line-height: 1.7;
}

/* CARRUSEL */

.inicio {
    width: 100%;
}

.inicio .titulo,
.inicio .subtitulo {
    text-align: center;
}

.carrusel {
    width: 100%;
    overflow: hidden;
    background: white;
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(20,60,35,.1);
}

.foto-carrusel {
    display: block;
    width: 100%;
    height: clamp(390px, 56vh, 680px);
    object-fit: cover;
}

.carrusel-pie {
    padding: 20px 25px;
    text-align: center;
}

.carrusel-pie h3 {
    margin: 0 0 17px;
    color: var(--verde-oscuro);
    font-size: 22px;
}

.controles {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.flecha {
    width: 45px;
    height: 45px;
    border: 1px solid #dce6de;
    border-radius: 50%;
    background: white;
    color: var(--verde);
    font-size: 20px;
}

.puntos {
    display: flex;
    gap: 10px;
}

.punto {
    width: 13px;
    height: 13px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: #cad8cd;
}

.punto.activo {
    background: var(--verde);
}

.lema {
    margin-top: 25px;
    text-align: center;
    color: var(--verde);
    font-size: 20px;
    font-weight: 900;
}

/* CALENDARIO */

.calendario {
    max-width: 850px;
    padding: 24px;
    border-radius: 17px;
    background: var(--azul);
}

.calendario-superior {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendario-superior h3 {
    margin: 0;
    color: var(--verde-oscuro);
    font-size: 22px;
}

.calendario-superior button {
    border: none;
    border-radius: 9px;
    padding: 10px 15px;
    background: white;
    font-size: 18px;
}

.calendario-dias {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
}

.nombre-dia {
    padding: 10px 0;
    text-align: center;
    font-weight: 900;
}

.dia {
    min-height: 55px;
    border: none;
    border-radius: 10px;
    background: white;
    color: #24573a;
    font-weight: 800;
}

.dia.evento {
    background: var(--menta);
    border: 2px solid #8dcaa1;
}

.dia.seleccionado {
    background: var(--verde);
    color: white;
}

.dia.vacio {
    background: transparent;
    cursor: default;
}

.agenda-detalle {
    max-width: 850px;
    margin-top: 18px;
    padding: 22px;
    border-radius: 15px;
    background: var(--lila);
    line-height: 1.7;
}

/* ASISTENCIAS Y PERFIL */

.panel-pastel {
    max-width: 850px;
    padding: 28px;
    border-radius: 17px;
}

.registro {
    padding: 17px;
    margin-top: 12px;
    border-radius: 12px;
    background: white;
}

.registro strong {
    color: var(--verde-oscuro);
}

.perfil-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.perfil-dato {
    padding: 22px;
    border-radius: 15px;
    overflow-wrap: anywhere;
}

.perfil-dato strong {
    display: block;
    margin-bottom: 8px;
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

    .menu button,
    .menu a {
        width: auto;
        flex-shrink: 0;
        white-space: nowrap;
        font-size: 14px;
        padding: 12px;
    }

    .principal {
        margin-left: 0;
        width: 100%;
        padding: 20px 15px;
    }

    .encabezado {
        flex-wrap: wrap;
    }

    .columnas,
    .perfil-grid {
        grid-template-columns: 1fr;
    }

    .foto-carrusel {
        height: 290px;
    }

    .titulo {
        font-size: 27px;
    }

    .calendario {
        padding: 12px;
    }

    .dia {
        min-height: 40px;
    }
}
</style>
</head>


<style>
/* REUNIONES: ICONOS CLASICOS VERDES */

#reuniones .reunion-dato svg {
    width: 22px !important;
    height: 22px !important;
    min-width: 22px !important;
    max-width: 22px !important;
    min-height: 22px !important;
    max-height: 22px !important;
    flex: 0 0 22px !important;
    display: block !important;
    color: #1b6041;
}

#reuniones .reunion-icono-grande svg {
    width: 48px !important;
    height: 48px !important;
    max-width: 48px !important;
    max-height: 48px !important;
    display: block !important;
}

/* TARJETAS ALARGADAS: UNA REUNION POR FILA */

#reuniones .columnas {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) !important;
    gap: 20px !important;
}

/* TARJETA BLANCA */

#reuniones .reunion-horizontal {
    display: flex !important;
    flex-direction: row !important;
    width: 100% !important;
    min-height: 155px;
    background: #ffffff !important;
    border: 1px solid #d5e3d8 !important;
    border-radius: 20px !important;
    overflow: hidden !important;
    box-shadow: 0 6px 20px rgba(21, 63, 43, 0.09) !important;
}

/* FRANJA VERDE IZQUIERDA */

#reuniones .reunion-ilustracion {
    display: flex !important;
    align-items: center;
    justify-content: center;
    flex: 0 0 24%;
    padding: 12px;
    background: linear-gradient(145deg, #d4e5ce, #eaf3e6);
}

#reuniones .reunion-icono-grande {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: rgba(255,255,255,0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #174b35;
}

/* INFORMACION A LA DERECHA */

#reuniones .reunion-contenido {
    flex: 1;
    min-width: 0;
    padding: 16px 22px;
}

#reuniones .reunion-etiqueta {
    display: inline-block;
    background: #e1eedc;
    color: #174b35;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
}

#reuniones .reunion-contenido h3 {
    margin: 14px 0;
    color: #153f2b;
    font-size: 24px;
    line-height: 1.3;
}

#reuniones .reunion-informacion {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 18px;
}

#reuniones .reunion-dato {
    display: flex !important;
    align-items: center !important;
    gap: 12px;
    color: #475e50;
    font-size: 15px;
}

/* BOTON VER DETALLES */

#reuniones .reunion-ver-detalles {
    display: inline-block;
    background: #17643b;
    color: #ffffff;
    padding: 12px 22px;
    border: none;
    border-radius: 11px;
    font-weight: 700;
    cursor: pointer;
}

#reuniones .reunion-ver-detalles:hover {
    background: #124c30;
}

/* DESCRIPCION DESPLEGABLE */

#reuniones .reunion-contenido .detalle.abierto {
    margin-top: 15px;
    padding: 15px;
    background: #f2f8f3;
    border-radius: 12px;
    line-height: 1.6;
}

/* ADAPTACION PARA CELULARES */

@media (max-width: 650px) {
    #reuniones .reunion-horizontal {
        flex-direction: column !important;
    }

    #reuniones .reunion-ilustracion {
        flex: none;
        width: 100%;
        padding: 20px;
    }

    #reuniones .reunion-contenido {
        padding: 22px;
    }
}
</style>


<style>
/* REUNIONES: CORREGIR ICONOS GIGANTES */
#reuniones .reunion-dato svg {
    width: 22px !important;
    height: 22px !important;
    min-width: 22px !important;
    max-width: 22px !important;
    min-height: 22px !important;
    max-height: 22px !important;
    flex: 0 0 22px !important;
    display: block !important;
    color: #1b6041;
}

/* ICONO PRINCIPAL DE COMUNIDAD */
#reuniones .reunion-icono-grande svg {
    width: 75px !important;
    height: 75px !important;
    max-width: 75px !important;
    max-height: 75px !important;
    display: block !important;
}

/* UNA TARJETA ALARGADA POR FILA */
#reuniones .columnas {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) !important;
    gap: 20px !important;
}
</style>


<body>

<div class="contenedor">

<aside class="sidebar">

    <div class="marca">
        
<div class="logo" style="background: transparent; padding: 0; width: 60px; height: 60px;">
    <img
        src="imagenes/logo_comunal.png"
        alt="Logo de la Junta de Acción Comunal"
        style="width: 100%; height: 100%; object-fit: contain; display: block;"
    >
</div>

        <div>
            <h2>Tejido Comunal</h2>
            <small>Las Margaritas</small>
        </div>
    </div>

    <nav class="menu">

        <button type="button" class="activo" data-seccion="inicio">
         
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <path d="m3 10 9-7 9 7"/>
        <path d="M5 9v12h14V9"/>
        <path d="M9 21v-8h6v8"/>
    </svg>
</span> Inicio

        </button>

        <button type="button" data-seccion="agenda">
            
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M7 3v4M17 3v4M3 10h18"/>
        <path d="M8 14h3M8 17h3M15 14h2"/>
    </svg>
</span> Agenda

        </button>

        <button type="button" data-seccion="reuniones">
            
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
</span> Reuniones

        </button>

        <button type="button" data-seccion="asistencias">
            
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <path d="M8 12l3 3 5-6"/>
    </svg>
</span> Mis asistencias

        </button>

        <button type="button" data-seccion="comunicados">
            
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <path d="m3 11 18-5v12L3 14z"/>
        <path d="M7 15l2 5h4l-2-6"/>
        <path d="M21 9a3 3 0 0 1 0 6"/>
    </svg>
</span> Comunicados

        </button>

        <button type="button" data-seccion="novedades">
           
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <rect x="4" y="3" width="16" height="18" rx="2"/>
        <path d="M8 7h8M8 11h8M8 15h3M14 15h2"/>
    </svg>
</span> Novedades

        </button>

        <button type="button" data-seccion="perfil">
            
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
    </svg>
</span> Mi perfil

        </button>

        <a href="cerrar_sesion.php">
           
<span class="icono">
    <svg width="22" height="22" viewBox="0 0 24 24"
         fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align: middle;">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <path d="M16 17l5-5-5-5"/>
        <path d="M21 12H9"/>
    </svg>
</span>

        </a>

    </nav>

</aside>

<main class="principal">

    <header class="encabezado">
        <div>
            <h2 id="tituloActual">Inicio</h2>
            <p>Junta de Acción Comunal · Barrio Las Margaritas</p>
        </div>

        
<div class="nombre-usuario" style="
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    border: 1px solid #dcefe5;
    border-radius: 40px;
    padding: 8px 12px;
    box-shadow: 0 5px 18px rgba(0, 70, 40, 0.08);
    max-width: 100%;
">

    <div style="
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2bbf79, #005c38);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    ">
        <svg width="26" height="26" viewBox="0 0 24 24"
             fill="none" stroke="white" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
        </svg>
    </div>

    <div style="
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    ">
        <span style="
            color: #778c81;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
        ">
            USUARIO
        </span>

        <span style="
            color: #103c2a;
            font-size: 15px;
            font-weight: 800;
            overflow-wrap: anywhere;
        ">
            <?php echo e($nombre); ?>
        </span>
    </div>

    <div style="
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #dcf6e7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 8px;
        flex-shrink: 0;
        color: #075c38;
    ">
        <svg width="17" height="17" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </div>

</div>

    </header>

    <!-- INICIO -->

    <section id="inicio" class="seccion visible">

        <div class="inicio">

            <h1 class="titulo">
                ¡Bienvenidos a Tejido Comunal!
            </h1>

            <p class="subtitulo">
                Junta de Acción Comunal del Barrio Las Margaritas
            </p>

            <div class="carrusel">

                <img
                    id="fotoCarrusel"
                    class="foto-carrusel"
                    src="imagenes/comunidad1.png"
                    alt="Nuestra comunidad"
                >

                <div class="carrusel-pie">

                    <h3 id="textoCarrusel">Nuestra comunidad</h3>

                    <div class="controles">

                        <button
                            class="flecha"
                            type="button"
                            onclick="cambiarFoto(-1)"
                        >❮</button>

                        <div class="puntos">
                            <button class="punto activo" type="button" onclick="irFoto(0)"></button>
                            <button class="punto" type="button" onclick="irFoto(1)"></button>
                            <button class="punto" type="button" onclick="irFoto(2)"></button>
                        </div>

                        <button
                            class="flecha"
                            type="button"
                            onclick="cambiarFoto(1)"
                        >❯</button>

                    </div>

                </div>

            </div>

            <p class="lema">
                Unidos construimos comunidad. 🌿
            </p>

        </div>

    </section>

    <!-- AGENDA -->

    <section id="agenda" class="seccion">

        <h1 class="titulo">Agenda comunitaria</h1>

        <p class="subtitulo">
            Nuestro calendario de actividades y encuentros.
        </p>

        <div class="calendario">

            <div class="calendario-superior">

                <button type="button" onclick="moverMes(-1)">❮</button>

                <h3 id="mesCalendario"></h3>

                <button type="button" onclick="moverMes(1)">❯</button>

            </div>

            <div id="diasCalendario" class="calendario-dias"></div>

        </div>

        <div id="detalleAgenda" class="agenda-detalle">
            Selecciona un día para consultar las actividades.
        </div>

    </section>

    <!-- REUNIONES -->

    <section id="reuniones" class="seccion">

        <h1 class="titulo">Reuniones de la comunidad</h1>
        <p class="subtitulo">
            Participa y conoce los encuentros de nuestra comunidad.
        </p>

        <?php if (count($reuniones) > 0): ?>

        <div class="columnas">

            <?php
            $colores = ['menta', 'azul', 'crema', 'lila', 'rosado', 'durazno'];
            ?>

            <?php foreach ($reuniones as $indice => $reunion): ?>

                
<article class="reunion-horizontal">

    <div class="reunion-ilustracion">
        <div class="reunion-icono-grande">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="7" r="3"/>
                <path d="M5.5 20v-2a6.5 6.5 0 0 1 13 0v2"/>
                <circle cx="4.5" cy="9" r="2"/>
                <path d="M1 19v-2a4 4 0 0 1 4-4"/>
                <circle cx="19.5" cy="9" r="2"/>
                <path d="M23 19v-2a4 4 0 0 0-4-4"/>
            </svg>
        </div>
    </div>

    <div class="reunion-contenido">

        <span class="reunion-etiqueta">
            Reunión comunitaria
        </span>

        <h3><?php echo e($reunion['titulo']); ?></h3>

        <div class="reunion-informacion">

            <div class="reunion-dato">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M7 3v4M17 3v4M3 10h18"/>
                </svg>
                <span><?php echo e($reunion['fecha']); ?></span>
            </div>

            <div class="reunion-dato">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <span><?php echo e($reunion['hora']); ?></span>
            </div>

            <div class="reunion-dato">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/>
                    <circle cx="12" cy="10" r="2.5"/>
                </svg>
                <span><?php echo e($reunion['lugar']); ?></span>
            </div>

        </div>

        <button
            type="button"
            class="boton reunion-ver-detalles"
            onclick="alternarDetalle('r<?php echo (int)$reunion['id_actividad']; ?>')"
        >
            Ver detalles
        </button>

        <div
            class="detalle"
            id="r<?php echo (int)$reunion['id_actividad']; ?>"
        >
            <?php echo nl2br(e($reunion['descripcion'])); ?>
        </div>

    </div>

</article>


            <?php endforeach; ?>

        </div>
        
        <?php else: ?>

            <div class="aviso">
                Por ahora no hay reuniones próximas registradas.
            </div>

        <?php endif; ?>

    </section>

    <!-- MIS ASISTENCIAS -->

    <section id="asistencias" class="seccion">

        <h1 class="titulo">Mis asistencias</h1>

        <p class="subtitulo">
            Consulta tu participación en las actividades comunitarias.
        </p>

<!-- AVISO DE ASISTENCIA ACTIVA -->

<?php
$reunionActiva = null;

$sqlAsistenciaActiva = "
    SELECT
        a.id_actividad,
        a.titulo,
        a.fecha,
        a.hora,
        a.lugar
    FROM control_asistencias c
    INNER JOIN actividades a
        ON a.id_actividad = c.id_actividad
    WHERE c.estado = 'en_curso'
      AND a.tipo = 'reunion'
    ORDER BY c.fecha_inicio DESC, c.id_control DESC
    LIMIT 1
";

$resultadoAsistenciaActiva = $conn->query($sqlAsistenciaActiva);

if ($resultadoAsistenciaActiva) {
    $reunionActiva = $resultadoAsistenciaActiva->fetch_assoc();
    $resultadoAsistenciaActiva->free();
}
?>

<div class="panel-pastel menta"
     style="margin-bottom: 25px;">

    <?php if ($reunionActiva): ?>

        <h3 style="color: #075c38;">
            ✅ Asistencia habilitada
        </h3>

        <p>
            La Junta de Acción Comunal ha iniciado
            el registro de asistencia de esta reunión.
        </p>

        <p>
            <strong>Reunión:</strong>
            <?php echo e($reunionActiva['titulo']); ?>
        </p>

        <p>
            <strong>Fecha:</strong>
            <?php echo e($reunionActiva['fecha']); ?>
        </p>

        <p>
            <strong>Hora:</strong>
            <?php echo e($reunionActiva['hora']); ?>
        </p>

        <p>
            <strong>Lugar:</strong>
            <?php echo e($reunionActiva['lugar']); ?>
        </p>

        <div style="
            background: white;
            padding: 18px;
            border-radius: 14px;
            margin-top: 18px;
            border: 1px solid #cde5d5;
        ">

            <strong style="color: #075c38;">
                📱 Registro mediante código QR
            </strong>

            <p>
                Escanea el código QR que está
                proyectando el administrador
                durante la reunión.
            </p>

            <p style="font-size: 13px; color: #577565;">
                Por seguridad, el código cambiará
                automáticamente cada 3 minutos
                cuando habilitemos el sistema QR.
            </p>

        </div>

    <?php else: ?>

        <h3 style="color: #075c38;">
            🕒 Esperando inicio de asistencia
        </h3>

        <p>
            Actualmente no hay ninguna reunión
            con el registro de asistencia habilitado.
        </p>

        <p>
            Cuando el administrador inicie
            la asistencia de una reunión,
            aparecerá aquí su información.
        </p>

    <?php endif; ?>

</div>

<!-- FIN DEL AVISO DE ASISTENCIA -->

        <div class="panel-pastel menta">

            <h3>👤 <?php echo e($nombre); ?></h3>

            <p>
                Aquí puedes consultar tus registros de participación.
            </p>

            <?php if (count($asistencias) > 0): ?>

                <?php foreach ($asistencias as $asistencia): ?>

                    <div class="registro">

                        <strong>
                            <?php echo e($asistencia['titulo']); ?>
                        </strong>

                        <p>
                            📅 <?php echo e($asistencia['fecha']); ?>
                        </p>

                        <p>
                            🕒 <?php echo e($asistencia['hora']); ?>
                        </p>

                        <p>
                            📍 <?php echo e($asistencia['lugar']); ?>
                        </p>

                        <span class="etiqueta menta">
                            Registro encontrado
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="registro">
                    <?php if ($asistenciasDisponibles): ?>
                        Todavía no aparecen registros de asistencia
                        asociados a tu cuenta.
                    <?php else: ?>
                        El historial de asistencias todavía está
                        pendiente de configuración.
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>

        <div class="panel-pastel azul" style="margin-top:18px;">

            <h3>▦ Registro mediante código QR</h3>

            <p>
                Más adelante podrás registrar tu asistencia
                escaneando el código QR de cada reunión.
            </p>

        </div>

    </section>

    <!-- COMUNICADOS -->

    <section id="comunicados" class="seccion">

        <h1 class="titulo">Comunicados</h1>

        <p class="subtitulo">
            Información importante publicada por nuestra Junta.
        </p>

        <?php if (count($comunicados) > 0): ?>

            <div class="columnas">

                <?php
                $colores = ['azul', 'rosado', 'menta', 'crema', 'lila', 'durazno'];
                ?>

                <?php foreach ($comunicados as $indice => $comunicado): ?>

                    <article class="tarjeta">

                        <div class="tarjeta-cabecera <?php echo $colores[$indice % 6]; ?>">

                            <span class="etiqueta">
                                <?php echo !empty($comunicado['importante'])
                                    ? ' Importante'
                                    : ' Comunicado'; ?>
                            </span>

                            <h3>
                                <?php echo e($comunicado['titulo']); ?>
                            </h3>

                        </div>

                        <div class="tarjeta-cuerpo">

                            <p>
                                <strong>Categoría:</strong>
                                <?php echo e($comunicado['categoria']); ?>
                            </p>

                            <p>
                                <strong>Fecha:</strong>
                                <?php echo e($comunicado['fecha_publicacion']); ?>
                            </p>

                            <p>
                                <?php echo nl2br(e($comunicado['contenido'])); ?>
                            </p>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="aviso">
                Todavía no hay comunicados publicados.
            </div>

        <?php endif; ?>

    </section>

    <!-- NOVEDADES -->

    <section id="novedades" class="seccion">

        <h1 class="titulo">Novedades del barrio</h1>

        <p class="subtitulo">
            Cambios, avisos y actualizaciones de nuestra comunidad.
        </p>

        <div class="columnas">

            <article class="tarjeta">

                <div class="tarjeta-cabecera rosado">
                    <span class="etiqueta"> ultima noticia</span>
                    <h3>Cambios de reuniones</h3>
                </div>

                <div class="tarjeta-cuerpo">
                    <p>
                        Aquí se informarán cambios de fechas
                        y horarios de las reuniones.
                    </p>
                </div>

            </article>

            <article class="tarjeta">

                <div class="tarjeta-cabecera lila">
                    <span class="etiqueta"> ultima noticia</span>
                    <h3>Cambios de lugar</h3>
                </div>

                <div class="tarjeta-cuerpo">
                    <p>
                        Aquí se anunciarán cambios en los lugares
                        de los encuentros comunitarios.
                    </p>
                </div>

            </article>

            <article class="tarjeta">

                <div class="tarjeta-cabecera crema">
                    <span class="etiqueta"> ultima noticia</span>
                    <h3>Noticias comunitarias</h3>
                </div>

                <div class="tarjeta-cuerpo">
                    <p>
                        Aquí compartiremos novedades
                        de interés para los vecinos.
                    </p>
                </div>

            </article>

        </div>

    </section>

    <!-- MI PERFIL -->

    <section id="perfil" class="seccion">

        <h1 class="titulo">Mi perfil</h1>

        <p class="subtitulo">
            Tus datos de registro en Tejido Comunal.
        </p>

        <div class="perfil-grid">

            <div class="perfil-dato menta">
                <strong>👤 Nombre completo</strong>
                <?php echo e($usuario['nombre_completo']); ?>
            </div>

            <div class="perfil-dato azul">
                <strong>✉ Correo electrónico</strong>
                <?php echo e($usuario['email']); ?>
            </div>

            <div class="perfil-dato crema">
                <strong>☎ Contacto</strong>
                <?php echo e($usuario['contacto']); ?>
            </div>

            <div class="perfil-dato lila">
                <strong>⌂ Dirección</strong>
                <?php echo e($usuario['direccion_hogar']); ?>
            </div>

            <div class="perfil-dato rosado">
                <strong>🌿 Sector</strong>
                <?php echo e($usuario['nombre_sector'] ?? 'Sin sector'); ?>
            </div>

            <div class="perfil-dato durazno">
                <strong>✓ Tipo de cuenta</strong>
                Usuario de la comunidad
            </div>

        </div>

    </section>

</main>
</div>

<script>
// NAVEGACIÓN: BOTONES DEL MENÚ

const botonesMenu = document.querySelectorAll(
    ".menu button[data-seccion]"
);

const secciones = document.querySelectorAll(".seccion");
const tituloActual = document.getElementById("tituloActual");

botonesMenu.forEach(function(boton) {

    boton.addEventListener("click", function() {

        const destino = boton.getAttribute("data-seccion");

        secciones.forEach(function(seccion) {
            seccion.classList.toggle(
                "visible",
                seccion.id === destino
            );
        });

        botonesMenu.forEach(function(item) {
            item.classList.remove("activo");
        });

        boton.classList.add("activo");

        tituloActual.textContent = boton.textContent.trim();

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    });

});

// CARRUSEL DE FOTOGRAFÍAS

const fotografias = [
    "imagenes/comunidad1.png",
    "imagenes/comunidad2.png",
    "imagenes/comunidad3.png"
];

const textosFotos = [
    "Nuestra comunidad",
    "Juntos somos más fuertes",
    "Construimos un mejor barrio"
];

let fotoActual = 0;
let temporizadorCarrusel = null;

const imagenCarrusel = document.getElementById("fotoCarrusel");
const textoCarrusel = document.getElementById("textoCarrusel");
const puntos = document.querySelectorAll(".punto");

function mostrarFoto() {

    imagenCarrusel.src = fotografias[fotoActual];
    textoCarrusel.textContent = textosFotos[fotoActual];

    puntos.forEach(function(punto, indice) {
        punto.classList.toggle("activo", indice === fotoActual);
    });

}

function avanzarFoto(direccion) {

    fotoActual = (
        fotoActual + direccion + fotografias.length
    ) % fotografias.length;

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

    temporizadorCarrusel = window.setInterval(function() {

        if (!document.hidden) {
            avanzarFoto(1);
        }

    }, 5000);

}

function reiniciarCarrusel() {
    window.clearInterval(temporizadorCarrusel);
    iniciarCarrusel();
}

iniciarCarrusel();

// DETALLES DE REUNIONES

function alternarDetalle(id) {

    const detalle = document.getElementById(id);

    if (detalle) {
        detalle.classList.toggle("abierto");
    }

}

// DATOS DEL CALENDARIO

const reunionesAgenda = <?php
echo json_encode(
    $eventosAgenda,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
);
?>;

let fechaCalendario = new Date();
let diaSeleccionado = null;

const nombresMeses = [
    "Enero", "Febrero", "Marzo", "Abril",
    "Mayo", "Junio", "Julio", "Agosto",
    "Septiembre", "Octubre", "Noviembre", "Diciembre"
];

function formatoFecha(anio, mes, dia) {

    return anio + "-" +
        String(mes + 1).padStart(2, "0") + "-" +
        String(dia).padStart(2, "0");

}

function mostrarCalendario() {

    const anio = fechaCalendario.getFullYear();
    const mes = fechaCalendario.getMonth();

    document.getElementById("mesCalendario").textContent =
        nombresMeses[mes] + " " + anio;

    const contenedor = document.getElementById("diasCalendario");

    contenedor.replaceChildren();

    ["L", "M", "M", "J", "V", "S", "D"].forEach(function(nombre) {

        const etiqueta = document.createElement("div");

        etiqueta.className = "nombre-dia";
        etiqueta.textContent = nombre;

        contenedor.appendChild(etiqueta);

    });

    const primerDia = new Date(anio, mes, 1);
    const espacios = (primerDia.getDay() + 6) % 7;
    const totalDias = new Date(anio, mes + 1, 0).getDate();

    for (let i = 0; i < espacios; i++) {

        const vacio = document.createElement("div");

        vacio.className = "dia vacio";

        contenedor.appendChild(vacio);

    }

    for (let dia = 1; dia <= totalDias; dia++) {

        const fecha = formatoFecha(anio, mes, dia);

        const tieneEvento = reunionesAgenda.some(function(reunion) {
            return reunion.fecha === fecha;
        });

        const boton = document.createElement("button");

        boton.type = "button";
        boton.className = "dia";
        boton.textContent = dia;

        if (tieneEvento) {
            boton.classList.add("evento");
        }

        if (diaSeleccionado === fecha) {
            boton.classList.add("seleccionado");
        }

        boton.addEventListener("click", function() {

            diaSeleccionado = fecha;

            mostrarCalendario();
            mostrarEventosDia(fecha);

        });

        contenedor.appendChild(boton);

    }

}

function mostrarEventosDia(fecha) {

    const contenedor = document.getElementById("detalleAgenda");

    const eventos = reunionesAgenda.filter(function(reunion) {
        return reunion.fecha === fecha;
    });

    contenedor.replaceChildren();

    const titulo = document.createElement("strong");

    titulo.textContent = "Programación del " + fecha;

    contenedor.appendChild(titulo);

    if (eventos.length === 0) {

        const mensaje = document.createElement("p");

        mensaje.textContent =
            "No hay reuniones registradas para este día.";

        contenedor.appendChild(mensaje);

        return;

    }

    
eventos.forEach(function (evento) {

    const tarjeta = document.createElement("div");

    tarjeta.style.cssText =
        "background:#ffffff;" +
        "border:1px solid #dce9e0;" +
        "border-left:5px solid #17643b;" +
        "border-radius:14px;" +
        "padding:22px 25px;" +
        "margin-top:15px;" +
        "box-shadow:0 4px 15px rgba(21,63,43,0.07);" +
        "color:#153f2b;";

    const titulo = document.createElement("h3");

    titulo.textContent = evento.titulo;

    titulo.style.cssText =
        "margin:0 0 15px;" +
        "font-size:19px;color:#153f2b;";

    tarjeta.appendChild(titulo);

    function crearIcono(tipo) {

        const svg = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "svg"
        );

        svg.setAttribute("width", "23");
        svg.setAttribute("height", "23");
        svg.setAttribute("viewBox", "0 0 24 24");
        svg.setAttribute("fill", "none");
        svg.setAttribute("stroke", "#24573d");
        svg.setAttribute("stroke-width", "2");
        svg.setAttribute("stroke-linecap", "round");
        svg.setAttribute("stroke-linejoin", "round");

        const dibujos = {
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

        svg.innerHTML = dibujos[tipo];

        return svg;
    }

    function agregarDato(tipo, texto) {

        const fila = document.createElement("div");

        fila.style.cssText =
            "display:flex;align-items:center;" +
            "gap:12px;margin:10px 0;" +
            "font-size:16px;";

        fila.appendChild(crearIcono(tipo));

        const contenido = document.createElement("span");
        contenido.textContent = texto;

        fila.appendChild(contenido);
        tarjeta.appendChild(fila);
    }

    agregarDato("calendario", "Actividad programada");
    agregarDato("reloj", "Hora: " + (evento.hora || "No definida"));
    agregarDato("ubicacion", "Lugar: " + (evento.lugar || "No especificado"));

    contenedor.appendChild(tarjeta);
});


}

function moverMes(cambio) {

    fechaCalendario.setDate(1);

    fechaCalendario.setMonth(
        fechaCalendario.getMonth() + cambio
    );

    diaSeleccionado = null;

    document.getElementById("detalleAgenda").textContent =
        "Selecciona un día para consultar las actividades.";

    mostrarCalendario();

}

mostrarCalendario();

// INSCRIPCIÓN A TAREAS: PENDIENTE

function avisoParticipacion() {

    alert(
        "Pronto podrás inscribirte en las convocatorias " +
        "publicadas por la Junta."
    );

}
</script>

</body>
</html>