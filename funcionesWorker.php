<?php
/**
 * ========================================================================
 * BOOTSTRAP DE FUNCIONES DEL COTIZADOR MESS (MessIAs)
 * ========================================================================
 * Este archivo ya NO contiene la lógica directamente: se reorganizó en
 * módulos dentro de core/ para que cada función viva agrupada con las de
 * su misma responsabilidad, en vez de las ~535 líneas mezcladas que había
 * antes en un solo archivo.
 *
 *   core/config.php     -> constantes de BD, Ollama y la app
 *   core/helpers.php    -> limpieza de texto y validación de CDMESS
 *   core/historial.php  -> consultas de historial y aprendizaje humano
 *   core/ia_ollama.php  -> integración con Ollama
 *   core/embeddings.php -> búsqueda semántica (embeddings)
 *   core/sistema.php    -> estado del worker y utilidades AJAX
 *
 * Se deja este archivo como punto de entrada único porque todo el sistema
 * (index.php, procesar_carga.php, acciones_monitor_precios_v2.php, etc.)
 * ya hace `include 'funcionesWorker.php'`. Así no hay que tocar esos
 * includes: con incluir este archivo, las funciones de todos los módulos
 * quedan disponibles igual que antes.
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/ia_ollama.php';
require_once __DIR__ . '/core/embeddings.php';
require_once __DIR__ . '/core/historial.php';
require_once __DIR__ . '/core/sistema.php';
