<?php
/**
 * ========================================================================
 * MÓDULO: BÚSQUEDA SEMÁNTICA POR EMBEDDINGS
 * ========================================================================
 * Complementa (no reemplaza) la búsqueda por SQL de core/historial.php.
 * La búsqueda SQL (MATCH/LIKE) encuentra coincidencias de texto; esta
 * encuentra coincidencias de SIGNIFICADO — útil cuando el usuario describe
 * el mismo servicio/equipo con palabras distintas a como quedó capturado
 * en el histórico.
 *
 * Requiere la tabla embeddings_historial:
 *
 *   CREATE TABLE embeddings_historial (
 *       cdmess VARCHAR(50) NOT NULL PRIMARY KEY,
 *       descripcion TEXT NOT NULL,
 *       embedding LONGTEXT NOT NULL,   -- JSON: arreglo de floats
 *       actualizado_en DATETIME NOT NULL,
 *       KEY idx_actualizado (actualizado_en)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * Y se llena corriendo `php embeddings_worker.php` (una vez, y de ahí en
 * adelante cada que quieras que absorba cotizaciones nuevas).
 */

/**
 * Pide a Ollama el vector de embedding para un texto. Devuelve null si el
 * modelo de embeddings no está disponible/instalado o si hubo timeout —
 * en ese caso el llamador debe simplemente no usar candidatos semánticos,
 * nunca tronar la búsqueda por esto.
 */
function obtenerEmbedding($texto) {
    $texto = trim((string) $texto);
    if ($texto === '') return null;

    $ch = curl_init(EMBEDDING_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => EMBEDDING_MODEL,
        'prompt' => $texto
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, EMBEDDING_TIMEOUT);

    $res = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        $motivo = ($curl_errno === CURLE_OPERATION_TIMEDOUT)
            ? "TIMEOUT (límite: " . EMBEDDING_TIMEOUT . "s)"
            : "error de conexión (errno $curl_errno): $curl_error";
        error_log("Embeddings [" . EMBEDDING_MODEL . "] $motivo");
        return null;
    }

    if ($httpcode != 200) {
        error_log("Embeddings [" . EMBEDDING_MODEL . "] HTTP $httpcode | body: " . substr((string) $res, 0, 300)
            . " -- ¿instalaste el modelo? Corre: ollama pull " . EMBEDDING_MODEL);
        return null;
    }

    $data = json_decode($res, true);
    return $data['embedding'] ?? null;
}

/**
 * Similitud coseno entre dos vectores (rango -1 a 1; 1 = idénticos en
 * significado, 0 = sin relación).
 */
function similitudCoseno(array $a, array $b) {
    $n = min(count($a), count($b));
    if ($n === 0) return 0.0;

    $dot = 0.0; $magA = 0.0; $magB = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $dot += $a[$i] * $b[$i];
        $magA += $a[$i] * $a[$i];
        $magB += $b[$i] * $b[$i];
    }
    if ($magA <= 0 || $magB <= 0) return 0.0;

    return $dot / (sqrt($magA) * sqrt($magB));
}

/**
 * Busca semánticamente entre los embeddings ya calculados y guardados.
 * Devuelve resultados en el MISMO formato que las consultas SQL de
 * historial.php (CDMESS, descripcion, precio_promedio), listos para
 * mezclarse con esos resultados antes de pasarlos a filtrarOpcionesConOllama().
 *
 * $umbral_minimo filtra ruido: por debajo de ~0.5-0.55 de similitud coseno
 * el "parecido" ya no suele ser confiable con nomic-embed-text.
 */
function buscarSemanticamente($busqueda, $conn, $top_n = 10, $umbral_minimo = 0.55) {
    try {
        $emb_busqueda = obtenerEmbedding($busqueda);
        if ($emb_busqueda === null) {
            return [];
        }

        $res = $conn->query("SELECT cdmess, descripcion, embedding FROM embeddings_historial");
        if (!$res) return [];

        $candidatos = [];
        while ($row = $res->fetch_assoc()) {
            $vector = json_decode($row['embedding'], true);
            if (!is_array($vector)) continue;

            $sim = similitudCoseno($emb_busqueda, $vector);
            if ($sim >= $umbral_minimo) {
                $candidatos[] = [
                    'cdmess' => $row['cdmess'],
                    'descripcion' => $row['descripcion'],
                    'similitud' => $sim
                ];
            }
        }

        if (empty($candidatos)) return [];

        usort($candidatos, fn($a, $b) => $b['similitud'] <=> $a['similitud']);
        $candidatos = array_slice($candidatos, 0, $top_n);

        // La tabla de embeddings no guarda precio (cambia con el tiempo y
        // no queremos que se desactualice ahí); lo traemos fresco de
        // cotizaciones_items para los CDMESS que sí encontramos por significado.
        $cdmess_list = array_column($candidatos, 'cdmess');
        $placeholders = implode(',', array_fill(0, count($cdmess_list), '?'));
        $types = str_repeat('s', count($cdmess_list));

        $sql = "SELECT TRIM(CDMESS) as CDMESS, ROUND(AVG(PRECIO_VENTA/CANT), 2) as precio_promedio
                FROM cotizaciones_items
                WHERE TRIM(CDMESS) IN ($placeholders) AND PRECIO_VENTA > 0 AND CANT > 0
                GROUP BY TRIM(CDMESS)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$cdmess_list);
        $stmt->execute();

        $precios = [];
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $precios[$row['CDMESS']] = $row['precio_promedio'];
        }
        $stmt->close();

        $resultado = [];
        foreach ($candidatos as $c) {
            $cd = trim($c['cdmess']);
            // Si ya no tiene historial de precio vigente (item descontinuado,
            // etc.), lo descartamos: de nada sirve sugerir algo sin precio real.
            if (!isset($precios[$cd])) continue;
            $resultado[] = [
                'CDMESS' => $cd,
                'descripcion' => $c['descripcion'],
                'precio_promedio' => $precios[$cd]
            ];
        }

        return $resultado;
    } catch (Throwable $e) {
        // Típicamente: la tabla embeddings_historial todavía no existe
        // porque no se ha corrido la migración/backfill. No debe tronar
        // la búsqueda normal por esto.
        error_log('buscarSemanticamente: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mezcla candidatos de SQL con candidatos semánticos, sin duplicar CDMESS
 * (si SQL ya lo encontró, se queda con esa versión — tiene el precio más
 * fresco calculado en la misma consulta).
 */
function mezclarCandidatos(array $resultadosSql, array $resultadosSemanticos) {
    $vistos = [];
    $combinados = [];

    foreach ($resultadosSql as $fila) {
        $cd = trim($fila['CDMESS']);
        if ($cd === '' || isset($vistos[$cd])) continue;
        $vistos[$cd] = true;
        $combinados[] = $fila;
    }
    foreach ($resultadosSemanticos as $fila) {
        $cd = trim($fila['CDMESS']);
        if ($cd === '' || isset($vistos[$cd])) continue;
        $vistos[$cd] = true;
        $combinados[] = $fila;
    }

    return $combinados;
}

/**
 * Calcula embeddings para los CDMESS del histórico que aún no tienen uno
 * guardado (o cuya descripción cambió). Pensado para correrse por lotes
 * desde el script CLI embeddings_worker.php — NO en cada búsqueda, porque
 * pedirle un embedding a Ollama tarda; se hace una sola vez por CDMESS y
 * se reutiliza siempre desde la tabla.
 *
 * @return array{procesados:int, fallidos:int, restantes:int}
 */
function regenerarEmbeddingsFaltantes($conn, $lote = 25) {
    $sql = "SELECT TRIM(ci.CDMESS) as CDMESS, MAX(ci.DESCRIPCION) as descripcion
            FROM cotizaciones_items ci
            LEFT JOIN embeddings_historial e ON TRIM(ci.CDMESS) = e.cdmess
            WHERE e.cdmess IS NULL
              AND ci.CDMESS IS NOT NULL AND ci.CDMESS != ''
              AND ci.DESCRIPCION IS NOT NULL AND ci.DESCRIPCION != ''
            GROUP BY TRIM(ci.CDMESS)
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $lote);
    $stmt->execute();
    $pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $procesados = 0;
    $fallidos = 0;

    $insert = $conn->prepare(
        "INSERT INTO embeddings_historial (cdmess, descripcion, embedding, actualizado_en)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), embedding = VALUES(embedding), actualizado_en = NOW()"
    );

    foreach ($pendientes as $row) {
        $vector = obtenerEmbedding($row['descripcion']);
        if ($vector === null) {
            $fallidos++;
            continue;
        }
        $json_vector = json_encode($vector);
        $insert->bind_param('sss', $row['CDMESS'], $row['descripcion'], $json_vector);
        $insert->execute();
        $procesados++;
    }
    $insert->close();

    $res = $conn->query(
        "SELECT COUNT(DISTINCT TRIM(ci.CDMESS)) as total
         FROM cotizaciones_items ci
         LEFT JOIN embeddings_historial e ON TRIM(ci.CDMESS) = e.cdmess
         WHERE e.cdmess IS NULL AND ci.CDMESS IS NOT NULL AND ci.CDMESS != ''"
    );
    $restantes = $res ? (int) $res->fetch_assoc()['total'] : 0;

    return ['procesados' => $procesados, 'fallidos' => $fallidos, 'restantes' => $restantes];
}
