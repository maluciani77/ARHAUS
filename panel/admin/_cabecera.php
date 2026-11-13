<?php
/**
 * Abre la pantalla del panel del estudio: <head>, la barra lateral y el
 * <main>. Cada página la incluye y después escribe su contenido; al
 * final incluye _pie.php.
 *
 * Antes de incluirlo hay que definir:
 *   $raiz          ruta hasta la raíz del sitio ('../../')
 *   $usuario       el que está logueado
 *   $tituloPagina  lo que va en la pestaña del navegador
 * Y opcionalmente:
 *   $navActiva     clave de ADMIN_MENU_GENERAL que queda marcada
 *   $obra          si se está adentro de una obra: muestra sus secciones
 *   $grupoActivo   clave de ADMIN_MENU_OBRA marcada
 *   $cuentas       [clave de hoja => cuántas cosas hay], para los numeritos
 */

require_once __DIR__ . '/_menu.php';

$obraActual = $obra ?? null;
$grupoActivo = $grupoActivo ?? null;
$navActiva = $navActiva ?? null;
$cuentas = $cuentas ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($tituloPagina ?? 'Panel del estudio') ?> - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f5f4f0">
<link rel="icon" href="<?= e($raiz) ?>favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<?php /* panel.css trae los componentes; admin.css los repinta y agrega
         la barra lateral. El orden importa. */ ?>
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/panel.css')) ?>">
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/admin.css')) ?>">
</head>
<body class="panel-body">
<div class="adm-app">
    <aside class="adm-lateral">
        <a class="adm-lateral__logo" href="index.php">
            <img src="<?= e($raiz) ?>images/logo-dark.svg" alt="ARHAUS">
        </a>

        <?php if ($obraActual): ?>
            <div class="adm-obra">
                <p class="adm-etiqueta">Obra</p>
                <p class="adm-obra__nombre"><?= e($obraActual['nombre']) ?></p>
                <span class="adm-estado"><?= e(nombre_estado($obraActual['estado'])) ?></span>
                <a class="adm-obra__volver" href="index.php"><?= icono_admin('volver') ?>Todas las obras</a>
            </div>

            <nav class="adm-nav" aria-label="Secciones de la obra">
                <?php foreach (ADMIN_MENU_OBRA as $clave => [$nombre, $icono, $hojas]): ?>
                    <?php
                    // El botón lleva a la primera hoja del grupo.
                    $primera = (string)array_key_first($hojas);
                    // El numerito: la suma de lo que hay en todas sus hojas.
                    $total = 0;
                    foreach (array_keys($hojas) as $hoja) {
                        $total += (int)($cuentas[$hoja] ?? 0);
                    }
                    ?>
                    <a class="adm-nav__item<?= $clave === $grupoActivo ? ' is-activa' : '' ?>"
                       href="obra.php?id=<?= (int)$obraActual['id'] ?>&amp;seccion=<?= e($primera) ?>"
                       <?= $clave === $grupoActivo ? 'aria-current="page"' : '' ?>>
                        <?= icono_admin($icono) ?>
                        <span><?= e($nombre) ?></span>
                        <?php if ($total > 0): ?>
                            <span class="adm-nav__cuenta"><?= $total ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php else: ?>
            <nav class="adm-nav" aria-label="Secciones del panel">
                <?php foreach (ADMIN_MENU_GENERAL as $clave => [$nombre, $href, $icono]): ?>
                    <a class="adm-nav__item<?= $clave === $navActiva ? ' is-activa' : '' ?>"
                       href="<?= e($href) ?>"
                       <?= $clave === $navActiva ? 'aria-current="page"' : '' ?>>
                        <?= icono_admin($icono) ?>
                        <span><?= e($nombre) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="adm-lateral__pie">
            <span class="adm-lateral__usuario"><?= e($usuario['nombre']) ?> &middot; <?= e(nombre_rol($usuario['rol'])) ?></span>
            <a href="<?= e($raiz) ?>index.html">Ver sitio</a>
            <a class="adm-lateral__salir" href="<?= e($raiz) ?>logout.php">Salir</a>
        </div>
    </aside>

    <main class="adm-main">
