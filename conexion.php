<?php
$servidor = "localhost";
$usuario = "root";
$contrasena = "";
$base_datos = "conecto_comunal";

$conn = new mysqli(
    $servidor,
    $usuario,
    $contrasena,
    $base_datos
);

if ($conn->connect_error) {
    error_log($conn->connect_error);
    exit("No se pudo conectar a la base de datos.");
}

$conn->set_charset("utf8mb4");