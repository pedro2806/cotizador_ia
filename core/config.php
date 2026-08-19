<?php
/**
 * ========================================================================
 * CONFIGURACIÓN GLOBAL DEL COTIZADOR MESS (MessIAs)
 * ------------------------------------------------------------------------
 * Punto único de configuración. Antes estos valores vivían repetidos
 * dentro de conexion.php y funcionesWorker.php (la URL/modelo de Ollama
 * aparecía duplicada en dos funciones distintas). Ahora todo el sistema
 * lee de aquí, así que un cambio de servidor/modelo se hace en un solo
 * lugar.
 *
 * TODO producción: mover estos valores a variables de entorno (getenv)
 * en vez de dejarlos en código fuente, sobre todo las credenciales de BD.
 * ========================================================================
 */

// --- Base de datos ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cotizador_ia');
define('DB_CHARSET', 'utf8mb4');

// --- Motor de IA (Ollama) ---
define('OLLAMA_URL', 'http://localhost:11434/api/generate');
// Debe coincidir EXACTO con el nombre que muestra `ollama list` en la
// máquina donde corre Ollama. Un nombre distinto (aunque sea una versión
// parecida) da HTTP 404 "model not found" en cada llamada, y como esa
// respuesta es casi instantánea, se puede confundir con "no pasó nada" en
// vez de un error real — por eso conviene revisar el log si el veredicto
// sale siempre en fallback.
define('OLLAMA_MODEL', 'llama3:latest');
define('OLLAMA_TIMEOUT_FILTRO', 20);  // seg. para filtrarOpcionesConOllama()
// Confirmado por log real del usuario: con llama3:latest (8B, corriendo en
// CPU) generar el veredicto se pasaba de 25s ("TIMEOUT tras 25s"). No era
// bug de código, era que el modelo simplemente necesita más tiempo en ese
// hardware. Se sube a 60s. El worker corre en background (no bloquea al
// usuario en el navegador), así que esperar más aquí es seguro — solo
// alarga cuánto tarda ESE ítem en pasar de 'procesando' a 'completado'.
// Si sigue sin alcanzar, revisa cuánto tarda un "OK" exitoso en el log
// (Ollama [...] OK en X.Xs) y ajusta con ese dato real.
define('OLLAMA_TIMEOUT_PRECIO', 60);  // seg. para preguntarOllamaConPrecios()

// --- Worker (motor de procesamiento en background) ---
// worker_ia.php escribe su "latido" aquí en cada vuelta del bucle.
// index.php lo usa para saber si el motor sigue vivo, en vez de
// depender de "wmic" (Windows-only y obsoleto desde Windows 11).
define('WORKER_HEARTBEAT_FILE', __DIR__ . '/../worker_heartbeat.txt');
define('WORKER_HEARTBEAT_TTL', 15); // seg. de margen antes de considerarlo caído

// --- Búsqueda semántica (embeddings) ---
// 'nomic-embed-text' es un modelo dedicado a embeddings: chico (~274MB),
// rápido incluso en CPU, y da mejores resultados de similitud semántica
// que pedirle un embedding a un modelo de chat como llama3. Hay que
// instalarlo aparte: `ollama pull nomic-embed-text`. Si no está instalado,
// buscarSemanticamente() simplemente no aporta candidatos extra (falla
// "silencioso" con log) — la búsqueda por SQL sigue funcionando normal.
define('EMBEDDING_MODEL', 'nomic-embed-text');
define('EMBEDDING_URL', 'http://localhost:11434/api/embeddings');
define('EMBEDDING_TIMEOUT', 15);

// --- Aplicación ---
define('APP_NOMBRE', 'MessIAs');
define('APP_EMPRESA_DOMINIO', 'mess.com.mx');
