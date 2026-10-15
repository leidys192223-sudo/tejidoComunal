<?php
/*
 * TEJIDO COMUNAL
 * Archivo central para compartir información
 * entre el administrador y los usuarios.
 */

require_once __DIR__ . "/conexion.php";

/*
 * Consultar reuniones registradas.
 */
function obtenerReunionesComunales($conn) {
    $reuniones = [];

    $sql = "SELECT id_actividad, titulo, descripcion,
                   fecha, hora, lugar
            FROM actividades
            WHERE tipo = 'reunion'
            ORDER BY fecha DESC, hora DESC";

    $resultado = $conn->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $reuniones[] = $fila;
        }
    }

    return $reuniones;
}

/*
 * Consultar comunicados publicados.
 */
function obtenerComunicadosComunales($conn) {
    $comunicados = [];

    $sql = "SELECT id_comunicado, titulo, contenido,
                   categoria, fecha_publicacion, importante
            FROM comunicados
            ORDER BY fecha_publicacion DESC";

    $resultado = $conn->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $comunicados[] = $fila;
        }
    }

    return $comunicados;
} 


/*
 * Guardar comunicados publicados
 * por el administrador.
 */
function guardarComunicadoComunal($conn, $titulo, $contenido, $fecha, $idUsuario) {

    $titulo = trim($titulo);
    $contenido = trim($contenido);

    if ($titulo === "" || $contenido === "") {
        return false;
    }

    $fechaValida = DateTime::createFromFormat("!Y-m-d", $fecha);

    if (
        !$fechaValida ||
        $fechaValida->format("Y-m-d") !== $fecha
    ) {
        return false;
    }

    $fechaPublicacion = $fecha . " 00:00:00";
    $categoria = "General";

    $sql = "INSERT INTO comunicados
            (titulo, contenido, categoria,
             id_usuario, fecha_publicacion)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "sssis",
        $titulo,
        $contenido,
        $categoria,
        $idUsuario,
        $fechaPublicacion
    );

    $guardado = $stmt->execute();

    $stmt->close();

    return $guardado;
}




/*
 * TEJIDO COMUNAL
 * Guardar tareas en la base de datos.
 */
function guardarTareaComunal(
    $conn,
    $titulo,
    $descripcion,
    $fecha,
    $estado,
    $idUsuario
) {
    $titulo = trim($titulo);
    $descripcion = trim($descripcion);

    if ($titulo === "" || $fecha === "") {
        return false;
    }

    $estadosPermitidos = [
        "pendiente",
        "en_proceso",
        "completada"
    ];

    if (!in_array($estado, $estadosPermitidos, true)) {
        return false;
    }

    $fechaValida = DateTime::createFromFormat("!Y-m-d", $fecha);

    if (
        !$fechaValida ||
        $fechaValida->format("Y-m-d") !== $fecha
    ) {
        return false;
    }

    $sql = "INSERT INTO tareas
            (titulo, descripcion, estado, fecha_entrega, id_usuario)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "ssssi",
        $titulo,
        $descripcion,
        $estado,
        $fecha,
        $idUsuario
    );

    $guardado = $stmt->execute();

    $stmt->close();

    return $guardado;
}
