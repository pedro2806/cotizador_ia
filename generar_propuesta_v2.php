<?php
// 1. Configuración de límites y errores
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Conexión a la base de datos
$conn = new mysqli("localhost", "root", "", "cotizador_ia");
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// 3. Procesamiento de la búsqueda (Limpieza de comas y símbolos)
$busqueda_original = isset($_GET['q']) ? $_GET['q'] : '';
$busqueda_limpia = str_replace([',', '.', ';', ':', '-', '_'], ' ', $busqueda_original);
$busqueda_limpia = preg_replace('/\s+/', ' ', trim($busqueda_limpia));

$historico_texto = "";

if (!empty($busqueda_limpia)) {
    // Separamos en palabras para buscar cada una
    $palabras = explode(" ", $busqueda_limpia);
    $condiciones = [];
    $params = [];
    $tipos = "";

    foreach ($palabras as $p) {
        if (strlen($p) > 2) { // Ignorar palabras cortas como "de", "el"
            $condiciones[] = "(CDMESS LIKE ? OR DESCRIPCION LIKE ?)";
            $termino = "%$p%";
            $params[] = $termino;
            $params[] = $termino;
            $tipos .= "ss";
        }
    }

    if (!empty($condiciones)) {
        $sql = "SELECT CDMESS, DESCRIPCION, PRECIO_VENTA 
                FROM cotizaciones_items 
                WHERE " . implode(" AND ", $condiciones) . " 
                ORDER BY id_item DESC LIMIT 10";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($tipos, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while($row = $result->fetch_assoc()) {
            $historico_texto .= "Código: " . $row['CDMESS'] . " | Servicio: " . $row['DESCRIPCION'] . " | Precio ref: $" . $row['PRECIO_VENTA'] . "\n";
        }
    }
}

// 4. Función para hablar con Llama 3
function generarPropuestaIA($historico, $consulta) {
    $url = "http://localhost:11434/api/generate";
    
    $prompt = "El usuario solicita: '$consulta'.\n\n";
    $prompt .= "Basado en los siguientes registros históricos de MESS:\n$historico\n\n";
    $prompt .= "TAREA: Redacta una descripción técnica formal para una nueva cotización. ";
    $prompt .= "Si identificas un código CDMESS específico, menciónalo al principio.";

    $data = [
        "model" => "llama3",
        "system" => "Eres el Asistente Técnico de MESS, experto en metrología y calibración. Tu lenguaje es profesional, técnico y SIEMPRE respondes en español de México.",
        "prompt" => $prompt,
        "stream" => false,
        "options" => ["temperature" => 0.3]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    $response = curl_exec($ch);
    $json = json_decode($response, true);
    curl_close($ch);

    return $json['response'] ?? "La IA no pudo procesar la solicitud.";
}

// 5. Interfaz de Usuario (Bootstrap básico para que se vea bien)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotizador Inteligente MESS</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f4f7f6; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input[type="text"] { width: 70%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #004a99; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .resultado { margin-top: 25px; padding: 20px; background: #e9f5ff; border-left: 5px solid #004a99; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Buscador de Servicios MESS (IA)</h2>
        <p>Escribe lo que necesitas cotizar (ej: "Durómetro, Vickers" o "Mantenimiento multímetro"):</p>
        
        <form method="GET">
            <input type="text" name="q" value="<?php echo htmlspecialchars($busqueda_original); ?>" placeholder="Ej: calibración de presión...">
            <button type="submit">Consultar Historial e IA</button>
        </form>

        <?php if (!empty($busqueda_original)): ?>
            <div class="resultado">
                <?php 
                if ($historico_texto != "") {
                    echo "<strong>Propuesta Generada por Llama 3:</strong><br><br>";
                    echo generarPropuestaIA($historico_texto, $busqueda_original);
                } else {
                    echo "No se encontraron servicios relacionados en el historial.";
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>