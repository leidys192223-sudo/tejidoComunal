<?php
// Mostrar errores durante las pruebas.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tejido Comunal | Barrio Las Margaritas</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #f5f9f7;
            color: #073e2a;
        }

        a {
            text-decoration: none;
        }

        .barra-superior {
            background: #00482f;
            color: white;
            padding: 13px 5%;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            font-size: 13px;
        }

        .encabezado {
            background: white;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,.05);
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            width: 58px;
            height: 58px;
            background: #e5f3eb;
            border-radius: 16px;
            color: #00653d;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo svg {
            width: 38px;
            height: 38px;
        }

        .marca h1 {
            margin: 0;
            font-size: 25px;
            color: #06462e;
            font-weight: 800;
        }

        .marca p {
            margin: 2px 0 0;
            font-size: 14px;
            color: #77847c;
        }

        .navegacion {
            display: flex;
            gap: 45px;
        }

        .navegacion a {
            color: #073e2a;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 0;
        }

        .navegacion a:first-child {
            border-bottom: 3px solid #009b60;
        }

        .acceso {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .ingresar {
            color: #073e2a;
            font-size: 15px;
            font-weight: 700;
        }

        .boton-verde {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #008c55;
            color: white;
            border-radius: 14px;
            padding: 16px 24px;
            font-size: 15px;
            font-weight: 700;
            transition: .2s;
        }

        .boton-verde:hover {
            background: #006a41;
            transform: translateY(-2px);
        }

        .boton-verde svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: white;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* FOTOGRAFÍA GRANDE DE FONDO */
        .portada {
            min-height: 560px;
            display: flex;
            align-items: center;
            padding: 55px 5%;
            background-color: #164b35;
            background-image:
                linear-gradient(
                    90deg,
                    rgba(0, 40, 25, .96) 0%,
                    rgba(0, 40, 25, .82) 29%,
                    rgba(0, 40, 25, .35) 59%,
                    rgba(0, 40, 25, .04) 100%
                ),
                url('imagenes/las_margaritas.jpg');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .contenido-portada {
            max-width: 690px;
            color: white;
        }

        .etiqueta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 151, 88, .75);
            padding: 11px 18px;
            border-radius: 30px;
            color: white;
            font-size: 13px;
            font-weight: 700;
        }

        .contenido-portada h2 {
            font-size: clamp(38px, 5vw, 67px);
            line-height: 1.07;
            letter-spacing: -2px;
            margin: 22px 0;
            color: white;
            font-weight: 800;
        }

        .contenido-portada h2 span {
            color: #76f1a1;
        }

        .contenido-portada p {
            max-width: 620px;
            font-size: 19px;
            line-height: 1.65;
            color: #f3fff7;
            margin-bottom: 26px;
        }

        .boton-comunidad {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            padding: 19px 28px;
            background: #00a765;
            border-radius: 15px;
            color: white;
            font-size: 17px;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
            transition: .2s;
        }

        .boton-comunidad:hover {
            background: #008650;
            transform: translateY(-2px);
        }

        .boton-comunidad svg {
            width: 25px;
            height: 25px;
            stroke: white;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* TARJETAS INFORMATIVAS */
        .informacion {
            padding: 35px 5% 65px;
            background: #f4f9f7;
        }

        .titulo-seccion {
            text-align: center;
            margin-bottom: 28px;
        }

        .titulo-seccion h2 {
            font-size: 32px;
            color: #06462e;
            margin: 0 0 8px;
            font-weight: 800;
        }

        .titulo-seccion p {
            font-size: 16px;
            color: #74827a;
            margin: 0;
        }

        .tarjetas {
            max-width: 1450px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .tarjeta {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            background: white;
            padding: 28px 22px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 45, 25, .06);
            min-height: 190px;
            transition: .2s;
        }

        .tarjeta:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(0, 45, 25, .1);
        }

        .icono-tarjeta {
            flex-shrink: 0;
            width: 78px;
            height: 78px;
            background: #005437;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icono-tarjeta svg {
            width: 40px;
            height: 40px;
            fill: none;
            stroke: white;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .tarjeta h3 {
            font-size: 17px;
            color: #06462e;
            margin: 16px 0 8px;
            font-weight: 800;
        }

        .tarjeta p {
            color: #68766e;
            font-size: 14px;
            line-height: 1.7;
            margin: 0;
        }

        footer {
            background: #00482f;
            color: #d8ebe0;
            text-align: center;
            padding: 25px;
            font-size: 13px;
        }

        @media (max-width: 1050px) {
            .encabezado {
                flex-wrap: wrap;
            }

            .tarjetas {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .barra-superior {
                flex-direction: column;
            }

            .navegacion,
            .acceso {
                width: 100%;
                justify-content: center;
            }

            .portada {
                min-height: 540px;
            }

            .contenido-portada h2 {
                font-size: 40px;
            }

            .contenido-portada p {
                font-size: 16px;
            }

            .tarjeta {
                padding: 22px;
            }

            .icono-tarjeta {
                width: 65px;
                height: 65px;
            }

            .icono-tarjeta svg {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>

<body>

    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <span>📍 Medellín, Colombia — Barrio Las Margaritas</span>
        <span>● Junta de Acción Comunal de Las Margaritas</span>
    </div>

    <!-- ENCABEZADO -->
    <header class="encabezado">

        <div class="marca">
            
<div class="logo" style="background: transparent; width: 70px; height: 70px;">
    <img
        src="imagenes/logo_comunal.png"
        alt="Logo de la Junta de Acción Comunal"
        style="width: 70px; height: 70px; object-fit: contain;"
    >
</div>

            <div>
                <h1>Tejido Comunal</h1>
                <p>Barrio Las Margaritas</p>
            </div>
        </div>

        <nav class="navegacion">
            <a href="#inicio">Inicio</a>
            <a href="#sectores">Explora</a>

        </nav>

        <div class="acceso">
            <a class="ingresar" href="login.php">Ingresar</a>
            <a class="boton-verde" href="registro.php">
                Unirme al Barrio
            </a>
        </div>

    </header>

    <!-- PORTADA CON FOTOGRAFÍA -->
    <main>

        <section class="portada" id="inicio">

            <div class="contenido-portada">

                <div class="etiqueta">
                    Conectando vecinos y comunidad
                </div>

                <h2>
                    Las Margaritas,<br>
                    una comunidad<br>
                    <span>que avanza unida.</span>
                </h2>

                <p>
                    Bienvenido a Tejido Comunal, el espacio digital
                    de nuestro barrio para mantenernos informados,
                    fortalecer la participación ciudadana y estar
                    más cerca de nuestra Junta de Acción Comunal.
                </p>

                <a href="registro.php" class="boton-comunidad">

                    <svg viewBox="0 0 24 24">
                        <circle cx="9" cy="7" r="3"/>
                        <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
                        <path d="M17 11a3 3 0 1 0-1-6"/>
                        <path d="M18 14a5 5 0 0 1 3 5v1"/>
                    </svg>

                    Únete a nuestra comunidad
                    <span>→</span>

                </a>

            </div>

        </section>

        <!-- INFORMACIÓN DEL PROYECTO -->
        <section class="informacion" id="informacion">

            <div class="titulo-seccion">
                <h2>¿Qué puedes hacer en Tejido Comunal?</h2>
                <p>Un espacio para mantenernos conectados e informados.</p>
            </div>

            <div class="tarjetas">

                <!-- ACTIVIDADES -->
                <article class="tarjeta">

                    <div class="icono-tarjeta">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M7 3v4M17 3v4M3 10h18"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Gestión de Actividades</h3>
                        <p>
                            Infórmate sobre reuniones, jornadas
                            comunitarias y actividades organizadas
                            para nuestro barrio.
                        </p>
                    </div>

                </article>

                <!-- SECTORES -->
                <article class="tarjeta" id="sectores">

                    <div class="icono-tarjeta">
                        <svg viewBox="0 0 24 24">
                            <circle cx="9" cy="8" r="3"/>
                            <path d="M3 20v-2a6 6 0 0 1 12 0v2"/>
                            <path d="M17 11a3 3 0 1 0-1-6"/>
                            <path d="M18 14a5 5 0 0 1 3 5v1"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Sectores Organizados</h3>
                        <p>
                            Conoce cómo se organiza nuestra comunidad
                            y fortalece la comunicación entre vecinos
                            y representantes de cada sector.
                        </p>
                    </div>

                </article>

                <!-- INFORMACIÓN -->
                <article class="tarjeta">

                    <div class="icono-tarjeta">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="m3 7 9 7 9-7"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Información Comunitaria</h3>
                        <p>
                            Encuentra comunicados oficiales,
                            avisos de interés general y novedades
                            compartidas por nuestra Junta de Acción Comunal.
                        </p>
                    </div>

                </article>

            </div>

        </section>

    </main>

    <footer>
        © 2026 Tejido Comunal — Junta de Acción Comunal
        Barrio Las Margaritas, Medellín.
    </footer>

</body>
</html>