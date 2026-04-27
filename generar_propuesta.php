<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Conexión a la base de datos
$conn = new mysqli("localhost", "root", "", "cotizador_ia");
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);

// 2. Función para hablar con Ollama (ya configurada para Llama 3 y Español)
function preguntarOllama($contexto_historico, $servicio) {
    $url = "http://localhost:11434/api/generate";
    
    $prompt = "A continuación te entrego el historial de cotizaciones pasadas para el servicio: $servicio.\n";
    $prompt .= "DATOS HISTÓRICOS:\n$contexto_historico\n";
    $prompt .= "\nBasado en esta información, redacta una descripción técnica y profesional para una NUEVA cotización. ";
    $prompt .= "Asegúrate de que el tono sea experto y en español de México.";

    $data = [
        "model" => "llama3",
        "system" => "Eres el experto técnico de MESS. Tu objetivo es ayudar a los vendedores a redactar descripciones de servicios basadas en el historial de la empresa. Responde siempre en ESPAÑOL.",
        "prompt" => $prompt,
        "stream" => false,
        "options" => ["temperature" => 0.3] // Menos 'creatividad', más precisión técnica
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

    return $json['response'] ?? "Error al generar la propuesta.";
}

// 3. Lógica de búsqueda (Ejemplo con el servicio L1-20)
$servicio_a_consultar = 'L1-20'; // Aquí puedes poner cualquier CDMESS de tu tabla

$sql = "SELECT DESCRIPCION, PRECIO_VENTA FROM cotizaciones_items 
        WHERE CDMESS = '$servicio_a_consultar' LIMIT 5";
$result = $conn->query($sql);

$historico_texto = "";
while($row = $result->fetch_assoc()) {
    $historico_texto .= "- Descripción usada: " . $row['DESCRIPCION'] . " | Precio: $" . $row['PRECIO_VENTA'] . "\n";
}

// 4. Mostrar resultado
echo "<h2>Generador de Propuestas MESS</h2>";
echo "<strong>Servicio consultado:</strong> $servicio_a_consultar <br><hr>";

if ($historico_texto != "") {
    echo "<em>La IA está analizando el historial...</em><br><br>";
    flush();
    
    $propuesta_ai = preguntarOllama($historico_texto, $servicio_a_consultar);
    
    echo "<strong>Propuesta sugerida por Llama 3:</strong><br>";
    echo "<div style='background: #eef; padding: 20px; border-left: 5px solid #007bff;'>";
    echo nl2br($propuesta_ai);
    echo "</div>";
} else {
    echo "No se encontró historial para este servicio.";
}

$conn->close();
?>