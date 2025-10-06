<?php
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';

/**
 * Script de Download Seguro
 *
 * Permite download de ficheiros (ZIP, PDF, etc) mediante token valido.
 * Implementa multiplas camadas de seguranca:
 * - Validacao de token (formato e expiracao)
 * - Rate limiting por IP e token
 * - Validacao de MIME type real
 * - Path traversal protection
 * - Symlink security validation
 *
 * @author @mjvmst
 * @version 3.1
 */

// Apenas GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Validar parametros
$token = $_GET['token'] ?? '';
$fileName = $_GET['file'] ?? '';

if (empty($token) || empty($fileName)) {
    http_response_code(400);
    exit('Parametros invalidos');
}

// Validar formato do token
if (!validateTokenFormat($token)) {
    logAccess('DOWNLOAD_INVALID_TOKEN', $token, false);
    http_response_code(403);
    exit('Token invalido');
}

// Obter extensoes permitidas do .env
$allowedExtensions = array_map('trim', explode(',', ALLOWED_FILE_EXTENSIONS));
$extensionsPattern = implode('|', array_map('preg_quote', $allowedExtensions));

// Validar nome do ficheiro contra extensoes permitidas
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(' . $extensionsPattern . ')$/i', $fileName)) {
    logAccess('DOWNLOAD_INVALID_FILE', $token, false);
    http_response_code(400);
    exit('Nome de ficheiro invalido');
}

// FIX: Rate limiting por IP e por token (anti-enumeracao) usando constantes do .env
$clientIP = getClientIP();
$tokenPreview = substr($token, 0, 8);
$rateLimitKey = 'download_' . $clientIP . '_' . $tokenPreview;

if (!checkRateLimit($rateLimitKey, RATE_LIMIT_DOWNLOAD_ATTEMPTS, RATE_LIMIT_DOWNLOAD_WINDOW)) {
    http_response_code(429);
    exit('Demasiadas tentativas. Aguarde ' . (RATE_LIMIT_DOWNLOAD_WINDOW / 60) . ' minutos.');
}

// Diretorio de exportacoes (link simbolico)
$expDir = __DIR__ . '/exp';

// FIX: Validar symlink de forma segura
if (is_link($expDir)) {
    $linkTarget = readlink($expDir);

    if ($linkTarget === false || !is_dir($linkTarget) || !is_readable($linkTarget)) {
        logAccess('DOWNLOAD_SYMLINK_INVALID', $token, false, 'Symlink invalid');
        error_log("SECURITY: Symlink /exp invalido: $linkTarget");
        http_response_code(500);
        exit('Erro de configuracao do sistema');
    }

    $realTarget = realpath($linkTarget);
    $dangerousPaths = ['/etc', '/var/www', '/root', '/home', '/usr', '/bin', '/sbin'];
    foreach ($dangerousPaths as $dangerous) {
        if ($realTarget && strpos($realTarget, $dangerous) === 0) {
            logAccess('DOWNLOAD_SYMLINK_DANGEROUS', $token, false, "Points to: $realTarget");
            error_log("SECURITY: Symlink /exp aponta para: $realTarget");
            http_response_code(500);
            exit('Erro de configuracao do sistema');
        }
    }
}

// Construir caminho seguro
// Cenario 1: Verificar se existe pasta com o nome do token
$tokenDir = $expDir . DIRECTORY_SEPARATOR . $token;
$filePath = $tokenDir . DIRECTORY_SEPARATOR . $fileName;
$realFilePath = realpath($filePath);
$realExpDir = realpath($expDir);

// Cenario 2: Se nao existe em pasta, verificar ficheiro diretamente em /exp/
// Compatibilidade: /exp/TOKEN/TOKEN.zip onde ficheiro real e /exp/TOKEN.zip
if ($realFilePath === false && $fileName === $token . '.zip') {
    $filePath = $expDir . DIRECTORY_SEPARATOR . $fileName;
    $realFilePath = realpath($filePath);
    $tokenDir = $expDir; // Usar exp/ como diretorio base para expiracao
}

// Validar caminho real
if ($realFilePath === false || strpos($realFilePath, $realExpDir) !== 0) {
    logAccess('DOWNLOAD_PATH_TRAVERSAL', $token, false);
    http_response_code(403);
    exit('Acesso negado');
}

// Verificar se ficheiro existe e e legivel
if (!file_exists($realFilePath) || !is_file($realFilePath) || !is_readable($realFilePath)) {
    logAccess('DOWNLOAD_FILE_NOT_FOUND', $token, false);
    http_response_code(404);
    exit('Ficheiro nao encontrado');
}

// FIX: Verificar expiracao do token antes do download
$expirationCheck = checkTokenExpiration($tokenDir);
if (!$expirationCheck['valid']) {
    logAccess('DOWNLOAD_TOKEN_EXPIRED', $token, false, $expirationCheck['message']);
    http_response_code(403);
    exit($expirationCheck['message']);
}

// FIX: Validar MIME type real do ficheiro
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realFilePath);
finfo_close($finfo);

// Obter MIME types permitidos do .env
$allowedMimeTypes = array_map('trim', explode(',', ALLOWED_MIME_TYPES));

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    error_log("Ficheiro tem MIME type invalido: $realFilePath (MIME: $mimeType)");
    logAccess('DOWNLOAD_INVALID_MIME', $token, false);
    http_response_code(400);
    exit('Ficheiro invalido');
}

// FIX: Incrementar contador de downloads
incrementDownloadCount($tokenDir);

// Log de sucesso
logAccess('DOWNLOAD_SUCCESS', $token, true);

// FIX: Sanitizar filename para prevenir header injection
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($fileName));

// Headers para download seguro (usar MIME type validado)
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($realFilePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Limpar buffer e enviar ficheiro
ob_clean();
flush();
readfile($realFilePath);
exit;
