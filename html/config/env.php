<?php
/**
 * Carregador de Variaveis de Ambiente
 *
 * Sistema de Consulta de Processos
 * Carrega e processa ficheiro .env, definindo constantes da aplicacao.
 *
 * @author @mjvmst
 * @version 3.1
 */

// Carregar ficheiro .env se existir
$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse linha KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remover aspas
            $value = trim($value, '"\'');

            // Definir variavel de ambiente se ainda nao existe
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

/**
 * Obtem variavel de ambiente com valor padrao e conversao automatica de tipos
 *
 * Suporta conversao automatica de valores booleanos e null:
 * - "true", "(true)" -> boolean true
 * - "false", "(false)" -> boolean false
 * - "null", "(null)" -> null
 *
 * @param string $key Nome da variavel de ambiente
 * @param mixed $default Valor padrao se a variavel nao existir
 * @return mixed Valor da variavel (string, bool, null) ou valor padrao
 */
function env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    // Converter valores booleanos
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
    }

    return $value;
}

// Definir constantes da aplicacao
define('APP_NAME', env('APP_NAME', 'Consulta de Processos'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_URL', env('APP_URL', 'https://www.exemplo.pt'));

// Configuracao de sessao
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 1800)); // 30 minutos
define('SESSION_REGENERATE_INTERVAL', (int) env('SESSION_REGENERATE_INTERVAL', 900)); // 15 minutos

// Rate Limiting
define('RATE_LIMIT_SEARCH_ATTEMPTS', (int) env('RATE_LIMIT_SEARCH_ATTEMPTS', 5));
define('RATE_LIMIT_SEARCH_WINDOW', (int) env('RATE_LIMIT_SEARCH_WINDOW', 300)); // 5 minutos
define('RATE_LIMIT_DOWNLOAD_ATTEMPTS', (int) env('RATE_LIMIT_DOWNLOAD_ATTEMPTS', 10));
define('RATE_LIMIT_DOWNLOAD_WINDOW', (int) env('RATE_LIMIT_DOWNLOAD_WINDOW', 60)); // 1 minuto

// Logging
define('LOG_LEVEL', env('LOG_LEVEL', 'info'));
define('LOG_ACCESS', env('LOG_ACCESS', true));

// Diretorios
define('EXP_DIR_PATH', env('EXP_DIR_PATH', '/mnt/documentos-processos'));

// Seguranca - Ficheiros Permitidos
define('ALLOWED_FILE_EXTENSIONS', env('ALLOWED_FILE_EXTENSIONS', 'zip,pdf'));
define('ALLOWED_MIME_TYPES', env('ALLOWED_MIME_TYPES', 'application/zip,application/pdf'));

// Interface e Branding - Cores
define('THEME_PRIMARY_COLOR', env('THEME_PRIMARY_COLOR', '#004080'));
define('THEME_SECONDARY_COLOR', env('THEME_SECONDARY_COLOR', '#0066cc'));
define('THEME_SUCCESS_COLOR', env('THEME_SUCCESS_COLOR', '#28a745'));
define('THEME_DANGER_COLOR', env('THEME_DANGER_COLOR', '#dc3545'));
define('THEME_WARNING_COLOR', env('THEME_WARNING_COLOR', '#ffc107'));
define('THEME_INFO_COLOR', env('THEME_INFO_COLOR', '#17a2b8'));
define('THEME_BACKGROUND_COLOR', env('THEME_BACKGROUND_COLOR', '#f8f9fa'));

// Interface e Branding - Logotipo
define('LOGO_URL', env('LOGO_URL', 'https://www.empresa.pt/logo.svg'));
define('LOGO_ALT', env('LOGO_ALT', 'Municipio'));
define('LOGO_HEIGHT', env('LOGO_HEIGHT', '50px'));

// Interface e Branding - Footer
define('FOOTER_DEVELOPER', env('FOOTER_DEVELOPER', '@mjvmst'));
define('FOOTER_VERSION', env('FOOTER_VERSION', 'v3.1'));
define('FOOTER_ORGANIZATION', env('FOOTER_ORGANIZATION', 'Municipio'));
define('FOOTER_YEAR', env('FOOTER_YEAR', date('Y')));
define('FOOTER_EMAIL', env('FOOTER_EMAIL', 'geral@empresa.pt'));

// Interface e Branding - Titulo
define('APP_TITLE', env('APP_TITLE', 'Consulta de Processos'));

// Configuracao de erro em modo debug
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
