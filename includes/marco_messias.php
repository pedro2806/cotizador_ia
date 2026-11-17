<?php
/**
 * Marco messIAs de las páginas del cotizador (diseño "MessIAs Login.pptx").
 * Estilos en css/marco_messias.css; el <body> lleva class="mx-marco".
 * agentesIA tiene su propia versión (agentesIA/includes/marco_messias.php).
 *
 *   mxEncabezado(?$volver)       encabezado azul marino con el logo messIAs; si
 *                                $volver trae una URL, pinta a la izquierda el
 *                                botón naranja de regreso.
 *   mxPie()                      pie con el logo grupo mess | B1.
 *   mxAbrirHoja($titulo, $volver) encabezado + banda azul + hoja blanca con el
 *                                título gris a la derecha. Cerrar con mxCerrarHoja().
 */
if (!function_exists('mxEncabezado')) {

    function mxEncabezado(?string $volver = null): void {
        $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        echo '<header class="mx-header">';
        if ($volver !== null) {
            echo '<a class="mx-volver" href="' . $h($volver) . '" aria-label="Volver al inicio de messIAs" title="Volver">'
               .   '<img src="img/messias/volver.png" alt="" width="160" height="160">'
               . '</a>';
        }
        echo   '<a class="mx-logo" href="index.php" aria-label="messIAs, inicio">'
           .     '<img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI">'
           .   '</a>'
           . '</header>';
    }

    function mxPie(): void {
        echo '<footer class="mx-footer">'
           .   '<img src="img/messias/logo-mess-b1.png" alt="Grupo mess, all about metrology · B1 Business Intelligence">'
           . '</footer>';
    }

    function mxAbrirHoja(string $titulo, string $volver = 'index.php'): void {
        mxEncabezado($volver);
        echo '<main class="mx-main">'
           .   '<section class="mx-hoja" aria-labelledby="mxTituloHoja">'
           .     '<h1 class="mx-hoja-titulo" id="mxTituloHoja">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>';
    }

    function mxCerrarHoja(): void {
        echo '</section></main>';
        mxPie();
    }
}
