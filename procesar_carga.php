<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Validar sesión activa
if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada o no válida.']);
    exit;
}

// Inclusiones en la misma raíz
include 'conexion.php';
include 'funcionesWorker.php';

// 2. Validar método y datos mínimos
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['lista_excel'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos válidos para procesar.']);
    exit;
}

try {
        // 1. Iniciamos la transacción para proteger los datos
        $conn->begin_transaction();

        // Calcular el consecutivo del proyecto de forma dinámica
        $res = $conn->query("SELECT MAX(id) as max_id FROM cola_procesamiento");
        $row = $res->fetch_assoc(); 
        $ultimoP = $row['max_id'] + 1;

        $id_proyecto = "PROY-" . date("Ymd") . "-" . str_pad($ultimoP, 4, "0", STR_PAD_LEFT);
        $lineas = explode("\n", $_POST['lista_excel']);
        $insertados = 0;
        $reporte_errores = [];
        $tipoBusqueda = $_POST['tipoBusqueda'] ?? 'todas';
        $cliente = $_POST['cliente'] ?? null;

        // 2. Preparamos la consulta UNA SOLA VEZ fuera del bucle (Optimización de rendimiento)
        $query_insert = "INSERT INTO cola_procesamiento 
            (id_proyecto, entrada_usuario, cdmess_historico, descripcion_historica, precio_historico, estatus, es_sugerencia, id_us_registro) 
            VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?)";
        $stmt = $conn->prepare($query_insert);

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            $opciones = obtenerOpcionesUnicasHistoricas($linea, $tipoBusqueda, $cliente, $conn);
            
            if ($opciones === 'noValido') {
                $reporte_errores[] = "❌ Clave no activa: <b>$linea</b>";
                continue; 
            }

            if (is_array($opciones) && count($opciones) > 0) {
                $claves_insertadas = [];
                $es_primera = true;

                foreach ($opciones as $opcion) {
                    $clave = trim($opcion['CDMESS']);
                    if (in_array($clave, $claves_insertadas)) continue;

                    $es_sugerencia = $es_primera ? 0 : 1;
                    $es_primera = false;

                    // 3. Solo vinculamos parámetros y ejecutamos de forma rápida
                    $stmt->bind_param("ssssdii",
                        $id_proyecto, $linea, $clave, $opcion['descripcion'],
                        $opcion['precio_promedio'], $es_sugerencia, $_SESSION['usuario_id']
                    );
                    $stmt->execute();
                    
                    $insertados++;
                    $claves_insertadas[] = $clave;
                }
            } else {
                $reporte_errores[] = "⚠️ Sin historial: <b>$linea</b>";
            }
        }

        // Cerramos la sentencia después de procesar todas las líneas
        if ($stmt) $stmt->close();

        // 4. Si todo salió perfecto, confirmamos el guardado masivo
        $conn->commit();

        // Retornar respuesta JSON exitosa
        echo json_encode([
            'success'         => ($insertados > 0),
            'insertados'      => $insertados,
            'reporte_errores' => $reporte_errores,
            'id_proyecto'     => $id_proyecto,
            'tipoBusqueda'    => $tipoBusqueda
        ]);

    } catch (Exception $e) {
        // 5. Si algo falla a nivel de código o base de datos, abortamos todo el lote
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'error'   => 'Error interno en el procesamiento: ' . $e->getMessage()
        ]);
    }
exit;