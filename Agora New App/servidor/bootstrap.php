<?php
declare(strict_types=1);
spl_autoload_register(function(string $name){if(str_starts_with($name,'Agora\\')){$path=__DIR__.'/app/src/'.str_replace('\\','/',substr($name,6)).'.php';if(is_file($path))require $path;}});
require_once __DIR__.'/app/src/Core/Http.php';
/*
 * Configuración (sin Docker; servidor AlmaLinux 9 + CyberPanel + PHP 8.3):
 *  1. AGORA_CONFIG (ruta a un PHP que devuelve el arreglo), solo para pruebas locales.
 *  2. servidor/config/config.php, copiado de config.example.php (producción, MySQL/MariaDB).
 *  3. Sin config.php: MODO PRUEBAS con SQLite, ver app/src/Core/Pruebas.php.
 */
$configFile=getenv('AGORA_CONFIG')?:__DIR__.'/config/config.php';
if(is_file($configFile))$config=require $configFile;
else $config=\Agora\Core\Pruebas::config(); // Sin config.php = MODO PRUEBAS (SQLite + datos sintéticos, admin / 123456789).
if(!empty($config['modo_pruebas'])&&!defined('AGORA_MODO_PRUEBAS'))define('AGORA_MODO_PRUEBAS',true);
if(!is_array($config)||!preg_match('/^[a-f0-9]{64}$/D',$config['app_key']??''))throw new \Agora\Core\HttpError('Configuración de seguridad pendiente.',503);
date_default_timezone_set('UTC');
