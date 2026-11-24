<?php
/**
 * ========================================================================
 * ACTUALIZAR TARIFARIO VALIDADO — refresco manual desde el tarifario
 * ========================================================================
 * El tarifario se actualiza cada tanto (semana, mes, cada tercer día) y
 * tarifario_validado es solo una tabla de consulta con los precios ya
 * validados, normalizados a USD. Este script la refresca a mano:
 *
 *   1. Sube los códigos nuevos y actualiza los precios de los que ya
 *      están (solo STATUS = 'ACTIVE', PRECIO > 0, Divisa USD/MXN/EUR).
 *   2. Desactiva (activo = 0, sin borrar) los códigos que ya no están
 *      ACTIVE en el tarifario.
 *   3. NO reactiva nada: un código des-validado a mano (activo = 0)
 *      sigue en 0 aunque siga ACTIVE en el tarifario; reactivarlo es
 *      manual.
 *
 * Los tipos de cambio se leen de core/config.php (mismo archivo que usa
 * el resto del cotizador) y NO tienen valor por defecto: si faltan o
 * son <= 0 el script no hace nada y dice cómo definirlos.
 *
 * Uso: abrir esta página con sesión activa (misma protección que el
 * monitor y el cargador). El refresco corre al abrirla y muestra el
 * reporte. Volver a correrlo sin cambios no hace nada (idempotente).
 * ========================================================================
 */

/**
 * Refresca tarifario_validado desde el tarifario, en una transacción.
 *
 * @param object $conn        Conexión mysqli (o compatible en pruebas).
 * @param float  $tipoMxnUsd Pesos por 1 dólar (de core/config.php).
 * @param float  $tipoEurUsd Dólares por 1 euro (de core/config.php).
 * @return array Reporte: nuevos, actualizados, sin_cambio, desactivados,
 *               omitidos (lista de ['cdmess','motivo']), total,
 *               tipo_mxn_usd, tipo_eur_usd.
 * @throws InvalidArgumentException si algún tipo de cambio falta o <= 0.
 * @throws Throwable si falla la BD (la transacción se revierte).
 */
function refrescarTarifarioValidado($conn, $tipoMxnUsd, $tipoEurUsd)
{
    if (!is_numeric($tipoMxnUsd) || (float) $tipoMxnUsd <= 0) {
        throw new InvalidArgumentException(
            'TIPO_CAMBIO_MXN_USD falta o no es válido (debe ser un número mayor que 0).'
        );
    }
    if (!is_numeric($tipoEurUsd) || (float) $tipoEurUsd <= 0) {
        throw new InvalidArgumentException(
            'TIPO_CAMBIO_EUR_USD falta o no es válido (debe ser un número mayor que 0).'
        );
    }
    $tipoMxnUsd = (float) $tipoMxnUsd;
    $tipoEurUsd = (float) $tipoEurUsd;

    $reporte = [
        'nuevos' => 0,
        'actualizados' => 0,
        'sin_cambio' => 0,
        'desactivados' => 0,
        'omitidos' => [],
        'total' => 0,
        'tipo_mxn_usd' => $tipoMxnUsd,
        'tipo_eur_usd' => $tipoEurUsd,
    ];

    $conn->begin_transaction();
    try {
        // 1. Candidatos ACTIVE del tarifario (se clasifica en PHP para
        //    poder listar los omitidos con su motivo).
        $stmt = $conn->prepare(
            "SELECT TRIM(CDMESS) AS cdmess, PRECIO, Divisa FROM tarifario WHERE STATUS = 'ACTIVE'"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        $candidatos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        // 2. Estado actual de tarifario_validado (para clasificar cada
        //    fila como nueva, actualizada o sin cambio).
        $stmt = $conn->prepare(
            'SELECT cdmess, precio_validado_usd, precio_validado_mxn, divisa_original, activo FROM tarifario_validado'
        );
        $stmt->execute();
        $res = $stmt->get_result();
        $actuales = [];
        foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
            $actuales[$fila['cdmess']] = $fila;
        }
        $stmt->close();

        // 3. Upsert. El ON DUPLICATE KEY UPDATE solo toca las columnas
        //    de precio y divisa: NUNCA toca `activo`, para que una
        //    des-validación manual (activo = 0) se respete.
        $upsert = $conn->prepare(
            'INSERT INTO tarifario_validado (cdmess, precio_validado_usd, precio_validado_mxn, divisa_original, activo) ' .
            'VALUES (?, ?, ?, ?, 1) ' .
            'ON DUPLICATE KEY UPDATE precio_validado_usd = VALUES(precio_validado_usd), ' .
            'precio_validado_mxn = VALUES(precio_validado_mxn), ' .
            'divisa_original = VALUES(divisa_original)'
        );
        foreach ($candidatos as $c) {
            $codigo = trim((string) ($c['cdmess'] ?? ''));
            $precio = $c['PRECIO'] ?? null;
            $divisa = strtoupper(trim((string) ($c['Divisa'] ?? '')));
            if ($codigo === '') {
                continue; // Sin clave: no hay nada que sincronizar.
            }
            if (!is_numeric($precio) || (float) $precio <= 0) {
                $reporte['omitidos'][] = ['cdmess' => $codigo, 'motivo' => 'precio inválido o en cero'];
                continue;
            }
            if (!in_array($divisa, ['USD', 'MXN', 'EUR'], true)) {
                $reporte['omitidos'][] = ['cdmess' => $codigo, 'motivo' => 'divisa no soportada (' . $divisa . ')'];
                continue;
            }
            $precio = (float) $precio;
            if ($divisa === 'USD') {
                $usd = round($precio, 2);
                $mxn = null;
            } elseif ($divisa === 'MXN') {
                $usd = round($precio / $tipoMxnUsd, 2);
                $mxn = round($precio, 2);
            } else { // EUR
                $usd = round($precio * $tipoEurUsd, 2);
                $mxn = null;
            }

            if (!isset($actuales[$codigo])) {
                $reporte['nuevos']++;
            } elseif (tarifarioPrecioIgual($actuales[$codigo], $usd, $mxn, $divisa)) {
                $reporte['sin_cambio']++;
            } else {
                $reporte['actualizados']++;
            }

            // bind_param exige referencias: se pasan variables, no
            // expresiones (en PHP 8 un cast directo truena).
            $upsert->bind_param('sdss', $codigo, $usd, $mxn, $divisa);
            $upsert->execute();
        }
        $upsert->close();

        // 4. Desactivar (sin borrar) los códigos que ya no están ACTIVE
        //    en el tarifario. Solo toca los que hoy están en 1: nada se
        //    reactiva. El subquery excluye CDMESS nulos o vacíos para que
        //    un NULL no bloquee el NOT IN.
        $desactivar = $conn->prepare(
            'UPDATE tarifario_validado SET activo = 0 ' .
            "WHERE activo = 1 AND cdmess NOT IN (SELECT TRIM(CDMESS) FROM tarifario WHERE STATUS = 'ACTIVE' AND CDMESS IS NOT NULL AND TRIM(CDMESS) != '')"
        );
        $desactivar->execute();
        $reporte['desactivados'] = (int) $desactivar->affected_rows;
        $desactivar->close();

        // 5. Total en la tabla tras el refresco.
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM tarifario_validado');
        $stmt->execute();
        $res = $stmt->get_result();
        $fila = $res ? $res->fetch_assoc() : null;
        $reporte['total'] = (int) ($fila['total'] ?? 0);
        $stmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return $reporte;
}

/**
 * Compara una fila existente con los valores calculados, con 2 decimales.
 * NULL se compara con NULL (el precio en MXN solo se guarda para MXN).
 */
function tarifarioPrecioIgual($fila, $usd, $mxn, $divisa)
{
    $fmt = static function ($v) {
        return $v === null ? null : number_format((float) $v, 2, '.', '');
    };
    return $fmt($fila['precio_validado_usd']) === $fmt($usd)
        && $fmt($fila['precio_validado_mxn']) === $fmt($mxn)
        && strtoupper(trim((string) $fila['divisa_original'])) === $divisa;
}

/** ¿Existe la tabla tarifario_validado? */
function existeTablaTarifarioValidado($conn)
{
    $res = $conn->query("SHOW TABLES LIKE 'tarifario_validado'");
    if ($res === false) {
        return false;
    }
    return $res->num_rows > 0;
}

// ========================================================================
// ENTRADA WEB (en CLI el archivo solo define las funciones, para pruebas).
// ========================================================================
if (php_sapi_name() !== 'cli') {
    session_start();
    if (empty($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit;
    }

    // conexion.php incluye core/db.php (que carga core/config.php) y
    // deja la conexión en $conn, igual que en el resto del cotizador.
    require_once __DIR__ . '/conexion.php';
    set_time_limit(0);

    $error = null;
    $reporte = null;
    try {
        if (!existeTablaTarifarioValidado($conn)) {
            throw new RuntimeException(
                'La tabla tarifario_validado no existe. Ejecuta primero ' .
                'tarifario_validado.sql en la base de datos y vuelve a abrir esta página.'
            );
        }
        if (!defined('TIPO_CAMBIO_MXN_USD') || !is_numeric(TIPO_CAMBIO_MXN_USD) || (float) TIPO_CAMBIO_MXN_USD <= 0
            || !defined('TIPO_CAMBIO_EUR_USD') || !is_numeric(TIPO_CAMBIO_EUR_USD) || (float) TIPO_CAMBIO_EUR_USD <= 0) {
            throw new RuntimeException(
                'Faltan los tipos de cambio en core/config.php. Define antes de refrescar: ' .
                "define('TIPO_CAMBIO_MXN_USD', 17.20); // pesos por 1 dólar. " .
                "define('TIPO_CAMBIO_EUR_USD', 1.08); // dólares por 1 euro."
            );
        }
        $reporte = refrescarTarifarioValidado($conn, (float) TIPO_CAMBIO_MXN_USD, (float) TIPO_CAMBIO_EUR_USD);
    } catch (Throwable $e) {
        error_log('Refresco tarifario_validado: ' . $e->getMessage());
        $error = $e->getMessage();
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/x-icon" href="fav.ico">
        <title>Actualizar tarifario validado | MessIAs</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-4" style="max-width: 720px;">
        <h1 class="h4 mb-3">Actualizar tarifario validado</h1>
        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert">
                <strong>No se pudo refrescar.</strong><br>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php elseif ($reporte !== null): ?>
            <div class="alert alert-success" role="alert">
                Refresco completado.
            </div>
            <table class="table table-bordered bg-white">
                <tbody>
                <tr><th scope="row">Nuevos</th><td><?php echo (int) $reporte['nuevos']; ?></td></tr>
                <tr><th scope="row">Actualizados</th><td><?php echo (int) $reporte['actualizados']; ?></td></tr>
                <tr><th scope="row">Sin cambio</th><td><?php echo (int) $reporte['sin_cambio']; ?></td></tr>
                <tr><th scope="row">Desactivados</th><td><?php echo (int) $reporte['desactivados']; ?></td></tr>
                <tr><th scope="row">Omitidos</th><td><?php echo count($reporte['omitidos']); ?></td></tr>
                <tr><th scope="row">Total en tarifario_validado</th><td><?php echo (int) $reporte['total']; ?></td></tr>
                <tr><th scope="row">Tipo de cambio MXN/USD</th><td><?php echo htmlspecialchars((string) $reporte['tipo_mxn_usd'], ENT_QUOTES, 'UTF-8'); ?> pesos por dólar</td></tr>
                <tr><th scope="row">Tipo de cambio EUR/USD</th><td><?php echo htmlspecialchars((string) $reporte['tipo_eur_usd'], ENT_QUOTES, 'UTF-8'); ?> dólares por euro</td></tr>
                </tbody>
            </table>
            <?php if (!empty($reporte['omitidos'])): ?>
                <h2 class="h6 mt-4">Omitidos (siguen sin sembrarse)</h2>
                <table class="table table-bordered table-sm bg-white">
                    <thead><tr><th>CDMESS</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($reporte['omitidos'], 0, 100) as $o): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $o['cdmess'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $o['motivo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($reporte['omitidos']) > 100): ?>
                    <p class="text-muted">... y <?php echo count($reporte['omitidos']) - 100; ?> más.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <a href="monitor_precios_v2.php" class="btn btn-outline-secondary mt-3">Volver al monitor</a>
    </div>
    </body>
    </html>
    <?php
}
