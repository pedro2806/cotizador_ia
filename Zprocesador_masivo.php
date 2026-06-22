
while(true) {
    // Busca el siguiente ítem que no tenga propuesta
    $item = $conn->query("SELECT * FROM cotizacion_pendiente WHERE estado = 'pendiente' LIMIT 1")->fetch_assoc();
    
    if($item) {
        // Marcamos como procesando para que otros procesos no lo agarren
        $conn->query("UPDATE cotizacion_pendiente SET estado = 'procesando' WHERE id = " . $item['id']);
        
        // Ejecutamos la lógica de búsqueda y Llama 3 que ya tenemos
        $propuesta = generarPropuestaIA($historico, $item['busqueda_usuario']);
        
        // Guardamos y liberamos
        $conn->query("UPDATE cotizacion_pendiente SET propuesta_generada = '$propuesta', estado = 'listo' WHERE id = " . $item['id']);
    }
    sleep(1); // Pausa de 1 segundo para no quemar la GPU
}