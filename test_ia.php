<?php
// Eliminar límites de tiempo
set_time_limit(0); 
ignore_user_abort(true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

function preguntarOllama($pregunta) {
    $url = "http://localhost:11434/api/generate";
    
    $data = [
    "model" => "llama3",
    "stream" => false,
    "options" => [
        "temperature" => 0.7 // Para que sea creativo pero profesional
    ],
    "system" => "Eres el asistente inteligente de MESS (Metrología Especializada). 
                Tu función es ayudar a generar cotizaciones. 
                REGLA CRÍTICA: Responde siempre en ESPAÑOL. 
                Usa términos técnicos de metrología adecuados.",
    "prompt" => $pregunta 
];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600); 

    $response = curl_exec($ch);
    
    // DEBUG: Vamos a ver qué llegó exactamente antes de decodificar
    if (empty($response)) {
        return "La respuesta del servidor está totalmente vacía.";
    }

    $json = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Error al decodificar JSON: " . json_last_error_msg() . "<br>Respuesta recibida: " . $response;
    }

    return $json['response'] ?? "JSON recibido pero sin campo 'response'. Data: " . print_r($json, true);
}

echo "<h2>Iniciando Motor de IA MESS...</h2>";
echo "Solicitud enviada a la GPU. No cierres esta pestaña...<br>";
flush(); // Forza a PHP a mostrar el texto anterior mientras espera

$test_prompt = "cotiza L1-20 Medición con requerimientos específicos. Servicio con Acreditación EMA.";
echo "<strong>Resultado:</strong> " . preguntarOllama($test_prompt);