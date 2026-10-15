<?php
// HISTORIAL DE ACTIVIDADES - TEJIDO COMUNAL
// Requiere conexion.php con una conexion mysqli llamada $conn.
require_once __DIR__ . '/conexion.php';

function historialEsc($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$anio = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT);
$tipo = trim((string)($_GET['tipo'] ?? ''));
$texto = trim((string)($_GET['buscar'] ?? ''));

if (!$mes || $mes < 1 || $mes > 12) $mes = 0;
if (!$anio || $anio < 1900 || $anio > 2200) $anio = 0;
if (strlen($tipo) > 80) $tipo = substr($tipo, 0, 80);
if (strlen($texto) > 120) $texto = substr($texto, 0, 120);

$registros = [];
$anios = [];
$tipos = [];
$error = '';

if (!isset($conn) || !($conn instanceof mysqli)) {
    $error = 'No fue posible establecer la conexión con la base de datos.';
} else {
    $conn->set_charset('utf8mb4');

    // Consultar los años registrados.
    $resultadoAnios = $conn->query(
        "SELECT DISTINCT YEAR(fecha) AS anio
         FROM actividades
         WHERE fecha <= CURDATE()
         AND fecha IS NOT NULL
         ORDER BY anio DESC"
    );

    if ($resultadoAnios) {
        while ($fila = $resultadoAnios->fetch_assoc()) {
            $anios[] = (int)$fila['anio'];
        }
        $resultadoAnios->free();
    }

    // Consultar los tipos de actividades.
    $resultadoTipos = $conn->query(
        "SELECT DISTINCT tipo
         FROM actividades
         WHERE fecha <= CURDATE()
         AND tipo IS NOT NULL
         AND tipo <> ''
         ORDER BY tipo"
    );

    if ($resultadoTipos) {
        while ($fila = $resultadoTipos->fetch_assoc()) {
            $tipos[] = $fila['tipo'];
        }
        $resultadoTipos->free();
    }

    // Preparar los filtros.
    $condiciones = ['fecha <= CURDATE()'];
    $parametros = [];
    $formatos = '';

    if ($mes) {
        $condiciones[] = 'MONTH(fecha) = ?';
        $parametros[] = $mes;
        $formatos .= 'i';
    }

    if ($anio) {
        $condiciones[] = 'YEAR(fecha) = ?';
        $parametros[] = $anio;
        $formatos .= 'i';
    }

    if ($tipo !== '') {
        $condiciones[] = 'tipo = ?';
        $parametros[] = $tipo;
        $formatos .= 's';
    }

    if ($texto !== '') {
        $condiciones[] = '(titulo LIKE ? OR descripcion LIKE ? OR lugar LIKE ?)';
        $busqueda = '%' . $texto . '%';

        array_push(
            $parametros,
            $busqueda,
            $busqueda,
            $busqueda
        );

        $formatos .= 'sss';
    }

    // Consultar las actividades anteriores.
    $sql = 'SELECT
                id_actividad,
                titulo,
                tipo,
                fecha,
                hora,
                lugar,
                descripcion
            FROM actividades
            WHERE ' . implode(' AND ', $condiciones) . '
            ORDER BY fecha DESC, hora DESC, id_actividad DESC';

    $sentencia = $conn->prepare($sql);

    if (!$sentencia) {
        $error = 'No se pudo preparar la consulta del historial.';
    } else {
        if ($formatos !== '') {
            $sentencia->bind_param($formatos, ...$parametros);
        }

        if ($sentencia->execute()) {
            $resultado = $sentencia->get_result();

            while ($fila = $resultado->fetch_assoc()) {
                $registros[] = $fila;
            }

            $resultado->free();
        } else {
            $error = 'No se pudo consultar el historial.';
        }

        $sentencia->close();
    }
}

$nombreMeses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Historial de actividades | Tejido Comunal</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f7f3;
            color: #213e2d;
            font-family: Arial, Helvetica, sans-serif;
        }

        .contenedor {
            max-width: 1180px;
            margin: 32px auto;
            padding: 0 18px;
        }

        .encabezado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        h1 {
            margin: 0 0 8px;
            color: #174b35;
            font-size: clamp(25px, 4vw, 34px);
        }

        .subtitulo {
            color: #60766a;
            margin: 0;
            line-height: 1.5;
        }

        .volver {
            color: #17643b;
            text-decoration: none;
            font-weight: bold;
            background: #e5f0e7;
            padding: 12px 18px;
            border-radius: 10px;
        }

        .volver:hover {
            background: #d6e9da;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #e1eae2;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 6px 22px rgba(18, 60, 37, 0.07);
        }

        .filtros {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .campo label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
            color: #244c36;
        }

        .campo select,
        .campo input {
            width: 100%;
            height: 44px;
            padding: 10px 12px;
            border: 1px solid #ccdbd1;
            border-radius: 9px;
            background: white;
            color: #244031;
            font: inherit;
        }

        .campo select:focus,
        .campo input:focus {
            outline: 2px solid #8bbda0;
            outline-offset: 1px;
        }

        .acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .boton {
            display: inline-block;
            text-align: center;
            background: #17643b;
            color: white;
            border: 0;
            border-radius: 9px;
            padding: 12px 20px;
            cursor: pointer;
            font: inherit;
            font-weight: bold;
            text-decoration: none;
        }

        .boton:hover {
            background: #124d2e;
        }

        .boton.secundario {
            background: #e6f0e7;
            color: #205539;
        }

        .boton.secundario:hover {
            background: #d8e9dc;
        }

        .resultados {
            margin: 28px 0 14px;
            font-weight: bold;
            color: #385847;
        }

        .tabla-contenedor {
            overflow-x: auto;
            border: 1px solid #e1eae2;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 660px;
        }

        thead {
            background: #e6f1e8;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e8eee9;
            vertical-align: middle;
        }

        th {
            color: #1e5135;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fafdfb;
        }

        .tipo {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #edf5ee;
            color: #245a3b;
            font-size: 13px;
            font-weight: bold;
        }

        .detalles-boton {
            background: transparent;
            border: 1px solid #9cc8aa;
            border-radius: 8px;
            padding: 9px 12px;
            color: #17643b;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .detalles-boton:hover {
            background: #edf7f0;
        }

        .mensaje {
            padding: 24px;
            text-align: center;
            color: #65786b;
        }

        .error {
            padding: 15px;
            border: 1px solid #efc2c2;
            background: #fff3f3;
            color: #8b2a2a;
            border-radius: 10px;
            margin-top: 20px;
        }

        dialog {
            width: min(92vw, 540px);
            border: none;
            border-radius: 16px;
            padding: 26px;
            color: #23412f;
            box-shadow: 0 20px 65px rgba(0, 0, 0, 0.22);
        }

        dialog::backdrop {
            background: rgba(14, 38, 24, 0.55);
        }

        dialog h2 {
            color: #174b35;
            margin: 0 0 18px;
        }

        .detalle-linea {
            margin: 12px 0;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .descripcion {
            white-space: pre-wrap;
        }

        .cerrar {
            float: right;
            margin-top: 12px;
        }

        @media (max-width: 800px) {
            .filtros {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 500px) {
            .panel {
                padding: 16px;
            }

            .filtros {
                grid-template-columns: 1fr;
            }

            .contenedor {
                margin-top: 18px;
            }
        }
    </style>
</head>

<body>

<main class="contenedor">

    <div class="encabezado">

        <div>
            <h1>Historial de actividades</h1>

            <p class="subtitulo">
                Consulta los encuentros y actividades anteriores
                de nuestra Junta de Acción Comunal.
            </p>
        </div>

        <a class="volver" href="dashboard.php">
            ← Volver al panel
        </a>

    </div>

    <section class="panel" aria-label="Consulta de historial">

        <form method="get" action="historial.php">

            <div class="filtros">

                <!-- FILTRO DE MES -->
                <div class="campo">

                    <label for="mes">Seleccionar mes</label>

                    <select name="mes" id="mes">

                        <option value="">Todos los meses</option>

                        <?php foreach ($nombreMeses as $numero => $nombre): ?>

                            <option
                                value="<?= $numero ?>"
                                <?= $mes === $numero ? 'selected' : '' ?>
                            >
                                <?= historialEsc($nombre) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- FILTRO DE AÑO -->
                <div class="campo">

                    <label for="anio">Seleccionar año</label>

                    <select name="anio" id="anio">

                        <option value="">Todos los años</option>

                        <?php foreach ($anios as $opcionAnio): ?>

                            <option
                                value="<?= $opcionAnio ?>"
                                <?= $anio === $opcionAnio ? 'selected' : '' ?>
                            >
                                <?= $opcionAnio ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- FILTRO DE TIPO -->
                <div class="campo">

                    <label for="tipo">Tipo de actividad</label>

                    <select name="tipo" id="tipo">

                        <option value="">Todos los tipos</option>

                        <?php foreach ($tipos as $opcionTipo): ?>

                            <option
                                value="<?= historialEsc($opcionTipo) ?>"
                                <?= $tipo === $opcionTipo ? 'selected' : '' ?>
                            >
                                <?= historialEsc(ucfirst($opcionTipo)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- BUSCADOR -->
                <div class="campo">

                    <label for="buscar">Buscar actividad</label>

                    <input
                        type="text"
                        name="buscar"
                        id="buscar"
                        maxlength="120"
                        value="<?= historialEsc($texto) ?>"
                        placeholder="Título, lugar o descripción"
                    >

                </div>

            </div>

            <!-- BOTONES -->
            <div class="acciones">

                <button type="submit" class="boton">
                    Buscar
                </button>

                <a href="historial.php" class="boton secundario">
                    Ver todo
                </a>

            </div>

        </form>

        <?php if ($error !== ''): ?>

            <div class="error" role="alert">
                <?= historialEsc($error) ?>
            </div>

        <?php else: ?>

            <p class="resultados">
                Resultados encontrados: <?= count($registros) ?>
            </p>

            <!-- TABLA DE HISTORIAL -->
            <div class="tabla-contenedor">

                <table>

                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Actividad</th>
                            <th>Tipo</th>
                            <th>Hora</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!$registros): ?>

                            <tr>
                                <td colspan="5" class="mensaje">
                                    No se encontraron actividades anteriores
                                    con esos filtros.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($registros as $indice => $actividad): ?>

                                <tr>

                                    <td>
                                        <?= historialEsc($actividad['fecha']) ?>
                                    </td>

                                    <td>
                                        <?= historialEsc($actividad['titulo']) ?>
                                    </td>

                                    <td>
                                        <span class="tipo">
                                            <?= historialEsc(ucfirst((string)$actividad['tipo'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= historialEsc($actividad['hora']) ?>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="detalles-boton"
                                            data-indice="<?= $indice ?>"
                                        >
                                            Ver detalles
                                        </button>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- VENTANA DE DETALLES -->
<dialog id="detalleActividad" aria-labelledby="tituloDetalle">

    <h2 id="tituloDetalle">
        Detalles de actividad
    </h2>

    <p class="detalle-linea">
        <strong>Fecha:</strong>
        <span id="dFecha"></span>
    </p>

    <p class="detalle-linea">
        <strong>Tipo:</strong>
        <span id="dTipo"></span>
    </p>

    <p class="detalle-linea">
        <strong>Hora:</strong>
        <span id="dHora"></span>
    </p>

    <p class="detalle-linea">
        <strong>Lugar:</strong>
        <span id="dLugar"></span>
    </p>

    <p class="detalle-linea">
        <strong>Descripción:</strong>
    </p>

    <p class="detalle-linea descripcion" id="dDescripcion"></p>

    <form method="dialog">
        <button class="boton cerrar" type="submit">
            Cerrar
        </button>
    </form>

</dialog>

<!-- FUNCIONAMIENTO DEL BOTON VER DETALLES -->
<script>
const actividades = <?= json_encode(
    $registros,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;

const cuadro = document.getElementById('detalleActividad');

document.querySelectorAll('.detalles-boton').forEach(boton => {

    boton.addEventListener('click', () => {

        const actividad = actividades[Number(boton.dataset.indice)];

        if (!actividad) {
            return;
        }

        document.getElementById('tituloDetalle').textContent =
            actividad.titulo || 'Detalles de actividad';

        document.getElementById('dFecha').textContent =
            actividad.fecha || 'Sin fecha';

        document.getElementById('dTipo').textContent =
            actividad.tipo || 'Sin tipo';

        document.getElementById('dHora').textContent =
            actividad.hora || 'Sin hora';

        document.getElementById('dLugar').textContent =
            actividad.lugar || 'Sin lugar';

        document.getElementById('dDescripcion').textContent =
            actividad.descripcion || 'Sin descripción';

        cuadro.showModal();

    });

});
</script>

</body>
</html>