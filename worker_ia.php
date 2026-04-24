<?php
include 'conexion.php';
set_time_limit(0);
// Evitar que el script se detenga por tiempo en el servidor
set_time_limit(0);

echo "====================================================\n";
echo "   MOTOR IA MESS - RESTAURADO Y OPTIMIZADO          \n";
echo "   Soportando: Min-Max, Promedio y Coincidencias    \n";
echo "====================================================\n";

while (true) {
    $res = $conn->query("SELECT id, entrada_usuario FROM cola_procesamiento WHERE estatus = 'pendiente' LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $item = $res->fetch_assoc();
        $id = $item['id'];
        $entrada = trim($item['entrada_usuario']);
        
        $conn->query("UPDATE cola_procesamiento SET estatus = 'procesando' WHERE id = $id");

        $stats = obtenerHistorialMESS($entrada);
        $respuesta_ia = preguntarOllamaConPrecios($stats, $entrada);

        if (preg_match('/\{.*\}/s', $respuesta_ia, $matches)) {
            $data = json_decode($matches[0], true);
            
            // RED DE SEGURIDAD: Si la IA omitió campos, PHP los restaura del array $stats
            if (!isset($data['precio_min'])) $data['precio_min'] = $stats['min'];
            if (!isset($data['precio_max'])) $data['precio_max'] = $stats['max'];
            if (!isset($data['precio_promedio'])) $data['precio_promedio'] = $stats['avg'];
            if (empty($data['coincidencias'])) $data['coincidencias'] = $stats['alternativas'];
            if (empty($data['cdmess']) || $data['cdmess'] == "N/A") $data['cdmess'] = $stats['cdmess'];

            $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            // Fallback total
            $json_final = json_encode([
                "cdmess" => $stats['cdmess'],
                "desc" => $entrada,
                "precio_min" => $stats['min'],
                "precio_max" => $stats['max'],
                "precio_promedio" => $stats['avg'],
                "precio_ia" => $stats['avg'],
                "notas" => "Recuperado automáticamente por el sistema.",
                "coincidencias" => $stats['alternativas']
            ]);
        }

        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia = ?, estatus = 'completado' WHERE id = ?");
        $stmt->bind_param("si", $json_final, $id);
        $stmt->execute();
        echo "[OK] Procesado ID $id\n";
    } else {
        sleep(3);
    }
}