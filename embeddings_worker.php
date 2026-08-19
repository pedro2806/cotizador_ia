<?php
/**
 * ========================================================================
 * GENERADOR DE EMBEDDINGS DEL HISTÓRICO (MessIAs)
 * ========================================================================
 * Corre esto UNA VEZ para llenar la tabla embeddings_historial con todo
 * el histórico existente, y de ahí en adelante cada que quieras que
 * absorba cotizaciones nuevas que se hayan cargado (es seguro volver a
 * correrlo cuantas veces quieras: solo procesa los CDMESS que todavía no
 * tienen embedding guardado).
 *
 * Requisitos antes de correrlo:
 *   1. Que exista la tabla embeddings_historial (ver el CREATE TABLE al
 *      inicio de core/embeddings.php).
 *   2. Tener el modelo de embeddings instalado en Ollama:
 *        ollama pull nomic-embed-text
 *      (o el que hayas puesto en EMBEDDING_MODEL en core/config.php)
 *
 * Uso (desde la carpeta del proyecto):
 *   php embeddings_worker.php
 *
 * Se puede dejar como tarea programada (Task Scheduler de Windows) para
 * que corra, por ejemplo, cada noche y así el histórico de embeddings
 * nunca se quede muy atrás de las cotizaciones nuevas.
 */

include 'conexion.php';
include 'funcionesWorker.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1'); // este SÍ es un script de consola: aquí sí queremos ver errores en pantalla
ini_set('log_errors', '1');

echo "=== Generador de embeddings de MessIAs ===\n";
echo "Modelo de embeddings: " . EMBEDDING_MODEL . "\n";
echo "Si esto falla desde el primer lote, probablemente falte: ollama pull " . EMBEDDING_MODEL . "\n\n";

$total_procesados = 0;
$total_fallidos = 0;
$resultado = ['procesados' => 0, 'fallidos' => 0, 'restantes' => 1]; // valor inicial solo para entrar al ciclo

do {
    try {
        $resultado = regenerarEmbeddingsFaltantes($conn, 25);
    } catch (Throwable $e) {
        echo "\nERROR FATAL: " . $e->getMessage() . "\n";
        echo "¿Ya creaste la tabla embeddings_historial? Revisa el CREATE TABLE en core/embeddings.php.\n";
        exit(1);
    }

    $total_procesados += $resultado['procesados'];
    $total_fallidos += $resultado['fallidos'];

    echo sprintf(
        "[%s] Lote: %d ok, %d fallidos | Restantes: %d\n",
        date('H:i:s'),
        $resultado['procesados'],
        $resultado['fallidos'],
        $resultado['restantes']
    );

    // Si un lote no logró avanzar nada (ni éxitos ni fallos), no hay nada
    // más que hacer por ahora: cortamos para no dejar el script en un
    // ciclo infinito.
    if ($resultado['procesados'] === 0 && $resultado['fallidos'] === 0) {
        break;
    }

    usleep(200000); // pequeña pausa entre lotes para no saturar Ollama
} while ($resultado['restantes'] > 0);

echo "\n=== Listo ===\n";
echo "Total procesados: $total_procesados | Fallidos: $total_fallidos\n";

if ($total_fallidos > 0) {
    echo "\nNota: los fallidos normalmente son por timeout o porque el modelo\n";
    echo "'" . EMBEDDING_MODEL . "' no está instalado en Ollama. Revisa el log de\n";
    echo "PHP para el detalle exacto, y vuelve a correr este script después —\n";
    echo "solo reintentará lo que sigue faltando.\n";
}
