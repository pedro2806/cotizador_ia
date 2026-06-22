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
    // Calcular el consecutivo del proyecto de forma dinámica
    $res = $conn->query("SELECT MAX(id) as max_id FROM cola_procesamiento");
    $row = $res->fetch_assoc(); 
    $ultimoP = $row['max_id'] + 1;

    $id_proyecto = "PROY-" . date("Ymd") . "-" . str_pad($ultimoP, 4, "0", STR_PAD_LEFT);
    $lineas = explode("\n", $_POST['lista_excel']);
    $insertados = 0;
    $reporte_errores = [];
    $tipoBusqueda = $_POST['tipoBusqueda'] ?? 'todas';

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (empty($linea)) continue;

        $opciones = obtenerOpcionesUnicasHistoricas($linea, $tipoBusqueda, $conn);
        
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

                $stmt = $conn->prepare("INSERT INTO cola_procesamiento
                    (id_proyecto, entrada_usuario, cdmess_historico, descripcion_historica, precio_historico, estatus, es_sugerencia, id_us_registro)
                    VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?)");

                if ($stmt) {
                    $stmt->bind_param("ssssdii",
                        $id_proyecto, $linea, $clave, $opcion['descripcion'],
                        $opcion['precio_promedio'], $es_sugerencia, $_SESSION['usuario_id']
                    );
                    $stmt->execute();
                    $stmt->close();
                    $insertados++;
                    $claves_insertadas[] = $clave;
                }
            }
        } else {
            $reporte_errores[] = "⚠️ Sin historial: <b>$linea</b>";
        }
    }

    // Retornar respuesta JSON exitosa
    echo json_encode([
        'success'         => ($insertados > 0),
        'insertados'      => $insertados,
        'reporte_errores' => $reporte_errores,
        'id_proyecto'     => $id_proyecto,
        'tipoBusqueda'    => $tipoBusqueda
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno en el procesamiento: ' . $e->getMessage()
    ]);
}
exit;