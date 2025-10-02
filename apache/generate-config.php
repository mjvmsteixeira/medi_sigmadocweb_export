#!/usr/bin/env php
<?php
/**
 * Gerador de Configuracao Apache Dinamica
 *
 * Le variaveis do ficheiro .env e gera o ficheiro de configuracao Apache
 * baseado no template www.exemplo.pt.conf.template
 *
 * Uso: php generate-config.php [--output=/caminho/saida.conf]
 *
 * @author @mjvmst
 * @version v3.0
 */

// Configuracao
$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
$templateFile = __DIR__ . '/website.conf.template';
$defaultOutputFile = null; // Sera determinado a partir do .env

// Cores para output no terminal
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'red' => "\033[31m",
    'blue' => "\033[34m",
    'bold' => "\033[1m",
];

/**
 * Exibe mensagem colorida no terminal
 */
function printColor(string $message, string $color = 'reset'): void {
    global $colors;
    echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
}

/**
 * Le e parseia o ficheiro .env
 */
function loadEnv(string $envFile): array {
    if (!file_exists($envFile)) {
        printColor("ERRO: Ficheiro .env nao encontrado: $envFile", 'red');
        exit(1);
    }

    $env = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsear linha KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (!empty($key)) {
                $env[$key] = $value;
            }
        }
    }

    return $env;
}

/**
 * Substitui placeholders no template com valores do .env
 */
function processTemplate(string $template, array $env): string {
    $config = $template;

    // Substituir todas as variaveis {{VAR_NAME}}
    preg_match_all('/\{\{([A-Z_]+)\}\}/', $template, $matches);

    foreach ($matches[1] as $varName) {
        $value = $env[$varName] ?? '';
        $config = str_replace("{{" . $varName . "}}", $value, $config);
    }

    return $config;
}

/**
 * Processa blocos condicionais {{#IF VAR}}...{{/IF}}
 */
function processConditionals(string $config, array $env): string {
    // Processar blocos {{#IF VAR}}...{{/IF}}
    $pattern = '/\{\{#IF ([A-Z_]+)\}\}(.*?)\{\{\/IF\}\}/s';

    $config = preg_replace_callback($pattern, function($matches) use ($env) {
        $varName = $matches[1];
        $block = $matches[2];

        // Se a variavel existir e nao estiver vazia, incluir o bloco
        if (!empty($env[$varName])) {
            return $block;
        }

        return '';
    }, $config);

    // Processar blocos {{#UNLESS VAR}}...{{/UNLESS}}
    $pattern = '/\{\{#UNLESS ([A-Z_]+)\}\}(.*?)\{\{\/UNLESS\}\}/s';

    $config = preg_replace_callback($pattern, function($matches) use ($env) {
        $varName = $matches[1];
        $block = $matches[2];

        // Se a variavel NAO existir ou estiver vazia, incluir o bloco
        if (empty($env[$varName])) {
            return $block;
        }

        return '';
    }, $config);

    return $config;
}

/**
 * Remove linhas vazias consecutivas
 */
function cleanupConfig(string $config): string {
    // Remover mais de 2 linhas vazias consecutivas
    $config = preg_replace("/\n{3,}/", "\n\n", $config);

    return $config;
}

// ==============================================================================
// MAIN
// ==============================================================================

printColor("\n+======================================================+", 'blue');
printColor("|  Gerador de Configuracao Apache Dinamica            |", 'blue');
printColor("+======================================================+\n", 'blue');

// 1. Carregar .env primeiro para determinar nome do ficheiro
printColor("-> A carregar variaveis de ambiente...", 'yellow');
$env = loadEnv($envFile);
printColor("  OK " . count($env) . " variaveis carregadas", 'green');

// Parsear argumentos de linha de comando
$outputFile = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--output=') === 0) {
        $outputFile = substr($arg, 9);
    }
}

// Determinar nome do ficheiro de saida a partir do .env se nao especificado
if (is_null($outputFile)) {
    $configName = $env['WEBSITE_CONFIG_NAME'] ?? $env['SERVER_NAME'] ?? 'website';
    $outputFile = __DIR__ . '/' . $configName . '.conf';
    printColor("  -> Ficheiro de saida: " . basename($outputFile), 'blue');
}

// 2. Validar variaveis obrigatorias
$required = ['SERVER_NAME', 'DOCUMENT_ROOT'];
$missing = [];

foreach ($required as $var) {
    if (empty($env[$var])) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    printColor("\nERRO: Variaveis obrigatorias em falta no .env:", 'red');
    foreach ($missing as $var) {
        printColor("  - $var", 'red');
    }
    exit(1);
}

// 3. Ler template
printColor("\n-> A ler template...", 'yellow');
if (!file_exists($templateFile)) {
    printColor("ERRO: Template nao encontrado: $templateFile", 'red');
    exit(1);
}

$template = file_get_contents($templateFile);
printColor("  OK Template carregado", 'green');

// 4. Processar template
printColor("\n-> A processar configuracao...", 'yellow');
$config = processTemplate($template, $env);
$config = processConditionals($config, $env);
$config = cleanupConfig($config);
printColor("  OK Configuracao gerada", 'green');

// 5. Escrever ficheiro de saida
printColor("\n-> A escrever ficheiro de configuracao...", 'yellow');
$result = file_put_contents($outputFile, $config);

if ($result === false) {
    printColor("ERRO: Nao foi possivel escrever ficheiro: $outputFile", 'red');
    exit(1);
}

printColor("  OK Ficheiro escrito: $outputFile", 'green');

// 6. Resumo
printColor("\n+======================================================+", 'green');
printColor("|  Configuracao Apache gerada com sucesso!            |", 'green');
printColor("+======================================================+\n", 'green');

printColor("Configuracao:", 'bold');
printColor("  * Servidor: " . $env['SERVER_NAME'], 'blue');
printColor("  * DocumentRoot: " . $env['DOCUMENT_ROOT'], 'blue');
printColor("  * HTTP Port: " . ($env['HTTP_PORT'] ?? '80'), 'blue');

if (!empty($env['SSL_CERT_FILE']) && !empty($env['SSL_KEY_FILE'])) {
    printColor("  * HTTPS: Ativado (porta " . ($env['HTTPS_PORT'] ?? '443') . ")", 'green');
    printColor("    - Certificado: " . $env['SSL_CERT_FILE'], 'blue');
    printColor("    - Chave: " . $env['SSL_KEY_FILE'], 'blue');
} else {
    printColor("  * HTTPS: Desativado (modo desenvolvimento)", 'yellow');
}

printColor("\nProximos passos:", 'bold');
if (empty($env['SSL_CERT_FILE'])) {
    printColor("  1. Copiar configuracao: sudo cp $outputFile /etc/apache2/sites-available/", 'blue');
    printColor("  2. Ativar site: sudo a2ensite " . basename($outputFile), 'blue');
    printColor("  3. Recarregar Apache: sudo systemctl reload apache2", 'blue');
} else {
    printColor("  1. Verificar certificados SSL existem nos caminhos especificados", 'blue');
    printColor("  2. Copiar configuracao: sudo cp $outputFile /etc/apache2/sites-available/", 'blue');
    printColor("  3. Ativar modulos SSL: sudo a2enmod ssl headers deflate expires rewrite", 'blue');
    printColor("  4. Ativar site: sudo a2ensite " . basename($outputFile), 'blue');
    printColor("  5. Testar configuracao: sudo apache2ctl configtest", 'blue');
    printColor("  6. Recarregar Apache: sudo systemctl reload apache2", 'blue');
}

printColor("\nOK Concluido!\n", 'green');
exit(0);
