<?php
/**
 * Configuracao de Seguranca
 * Sistema de Consulta de Processos
 */

// FIX: Carregar env.php primeiro para ter acesso as constantes
if (!defined('APP_ENV')) {
    require_once __DIR__ . '/env.php';
}

// Configuracao segura de sessoes
if (session_status() === PHP_SESSION_NONE) {
    // FIX: Definir domain explicito baseado em APP_URL
    $domain = '';
    if (defined('APP_URL')) {
        $parsedUrl = parse_url(APP_URL);
        $domain = $parsedUrl['host'] ?? '';
    }

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,  // Usar constante do .env
        'path' => '/',
        'domain' => $domain,
        'secure' => APP_ENV === 'production',  // HTTPS apenas em producao
        'httponly' => true,                    // Bloqueia acesso via JavaScript
        'samesite' => 'Strict'                 // Protecao CSRF adicional
    ]);
    session_start();
}

// FIX: Regenerar ID de sessao periodicamente usando constante do .env
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > SESSION_REGENERATE_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// FIX: Adicionar Security Headers (CSP, HSTS, etc.)
function setSecurityHeaders(): void {
    // Gerar nonce para scripts inline
    if (!isset($_SESSION['csp_nonce'])) {
        $_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
    }
    $nonce = $_SESSION['csp_nonce'];

    // Extrair dominio do LOGO_URL para CSP
    $logoHost = '';
    if (defined('LOGO_URL') && LOGO_URL) {
        $parsedUrl = parse_url(LOGO_URL);
        if (isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
            $logoHost = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        }
    }

    // Content Security Policy (dinamico baseado no .env)
    $imgSrc = "'self' data: https:";
    if ($logoHost) {
        $imgSrc = "'self' data: " . $logoHost;
    }

    $csp = "default-src 'self'; " .
           "script-src 'self' 'nonce-" . $nonce . "' https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
           "img-src " . $imgSrc . "; " .
           "font-src 'self' https://cdn.jsdelivr.net; " .
           "connect-src 'self' https://cdn.jsdelivr.net https://*.jsdelivr.net; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self'";
    header("Content-Security-Policy: $csp");

    // HSTS (apenas em producao com HTTPS)
    if (APP_ENV === 'production' && ($_SERVER['HTTPS'] ?? '') === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Outros headers de seguranca
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Aplicar headers automaticamente
setSecurityHeaders();

/**
 * Obtem nonce CSP da sessao para scripts inline
 *
 * @return string Nonce CSP de 32 caracteres hexadecimais
 */
function getCSPNonce(): string {
    return $_SESSION['csp_nonce'] ?? '';
}

/**
 * Gera token CSRF para protecao contra Cross-Site Request Forgery
 *
 * @return string Token CSRF de 64 caracteres hexadecimais
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF usando comparacao timing-safe
 *
 * @param string|null $token Token a validar (pode ser null)
 * @return bool True se o token e valido, false caso contrario
 */
function validateCSRFToken(?string $token): bool {
    if ($token === null || $token === '' || !isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate Limiting - Controla numero de tentativas por chave dentro de janela temporal
 *
 * Usa file locking (flock) para prevenir race conditions em ambientes concorrentes.
 * Implementa "fail open" em caso de erro para nao bloquear utilizadores legitimos.
 *
 * @param string $key Identificador unico (ex: "search_192.168.1.1_TOKEN123")
 * @param int $maxAttempts Numero maximo de tentativas permitidas (default: 5)
 * @param int $timeWindow Janela temporal em segundos (default: 300 = 5 minutos)
 * @return bool True se a tentativa e permitida, false se limite excedido
 */
function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool {
    $rateFile = __DIR__ . '/../data/rate_limit.json';

    // Criar diretorio se nao existir
    if (!file_exists(dirname($rateFile))) {
        mkdir(dirname($rateFile), 0750, true);
    }

    // FIX: Usar flock() para prevenir race condition
    $handle = fopen($rateFile, 'c+');
    if (!$handle) {
        error_log("Erro ao abrir ficheiro rate limit: $rateFile");
        return true; // Fail open em caso de erro
    }

    // Lock exclusivo para leitura e escrita atomica
    if (flock($handle, LOCK_EX)) {
        $fileSize = filesize($rateFile);
        $content = $fileSize > 0 ? fread($handle, $fileSize) : '[]';
        $data = json_decode($content, true);

        // Validar integridade do JSON
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log("Rate limit JSON corrompido, a reiniciar: " . json_last_error_msg());
            $data = [];
        }

        $now = time();

        // Limpar registos antigos
        $data = array_filter($data, fn($item) =>
            isset($item['time']) && ($now - $item['time'] < $timeWindow)
        );

        // Verificar tentativas
        $attempts = array_filter($data, fn($item) =>
            isset($item['key']) && $item['key'] === $key
        );

        $allowed = count($attempts) < $maxAttempts;

        if ($allowed) {
            // Registar tentativa
            $data[] = ['key' => $key, 'time' => $now];

            // Escrever atomicamente
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data));
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return $allowed;
    }

    fclose($handle);
    return true; // Fail open se nao conseguir lock
}

/**
 * Obtem endereco IP real do cliente com protecao contra spoofing
 *
 * Valida headers de proxy apenas se o request vier de proxy confiavel.
 * Prioriza: CF-Connecting-IP > X-Forwarded-For > X-Real-IP > REMOTE_ADDR
 *
 * @return string Endereco IP do cliente (formato IPv4 ou IPv6)
 */
function getClientIP(): string {
    // Lista de proxies/load balancers confiaveis
    // Configurar conforme infraestrutura (Cloudflare, nginx, Apache)
    $trustedProxies = [
        '127.0.0.1',
        '::1',
        // Adicionar IPs de proxies confiaveis aqui
        // Exemplo Cloudflare: '103.21.244.0/22', '103.22.200.0/22', etc.
    ];

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Apenas confiar em headers se o request vier de proxy confiavel
    if (in_array($remoteAddr, $trustedProxies, true)) {
        // Cloudflare tem header especifico
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // X-Forwarded-For (primeiro IP e o cliente real)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // X-Real-IP (usado por nginx)
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    // Fallback: usar REMOTE_ADDR (nao falsificavel)
    return $remoteAddr;
}

/**
 * Valida formato do token usando regex
 *
 * Tokens devem conter apenas caracteres alfanumericos (a-z, A-Z, 0-9) e pontos (.)
 * com comprimento entre 8 e 64 caracteres. Valida comprimento antes
 * do regex para prevenir ReDoS attacks.
 *
 * @param string|null $token Token a validar (pode ser null)
 * @return bool True se o formato e valido, false caso contrario
 */
function validateTokenFormat(?string $token): bool {
    if ($token === null || $token === '') {
        return false;
    }

    // Rejeitar strings muito longas ANTES do regex (prevenir ReDoS)
    if (strlen($token) > 64) {
        return false;
    }

    // Token deve ter entre 8 e 64 caracteres alfanumericos e pontos
    return preg_match('/^[a-zA-Z0-9.]{8,64}$/', $token) === 1;
}

/**
 * Verifica se token expirou baseado em ficheiro .metadata.json
 *
 * Verifica duas condicoes:
 * 1. Data de expiracao (campo 'expires_at')
 * 2. Limite de downloads (campos 'max_downloads' e 'download_count')
 *
 * Implementa "fail open" se metadata nao existir (compatibilidade retroativa)
 * ou estiver corrompido (nao bloqueia tokens validos).
 *
 * @param string $tokenDir Caminho absoluto do diretorio do token
 * @return array{valid: bool, message: string|null} Array associativo com resultado da validacao
 */
function checkTokenExpiration(string $tokenDir): array {
    $metadataFile = $tokenDir . DIRECTORY_SEPARATOR . '.metadata.json';

    // Sem metadata = nao expira (compatibilidade retroativa)
    if (!file_exists($metadataFile)) {
        return ['valid' => true, 'message' => null];
    }

    $content = file_get_contents($metadataFile);
    $metadata = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadata)) {
        error_log("Metadata corrompido para token: $tokenDir");
        return ['valid' => true, 'message' => null]; // Fail open
    }

    // Verificar expiracao por data
    if (isset($metadata['expires_at'])) {
        $expiresAt = strtotime($metadata['expires_at']);
        if ($expiresAt === false) {
            error_log("Data de expiracao invalida: " . $metadata['expires_at']);
            return ['valid' => true, 'message' => null];
        }

        if (time() > $expiresAt) {
            $expiresAtFormatted = date('d/m/Y H:i', $expiresAt);
            return [
                'valid' => false,
                'message' => "Token expirado em $expiresAtFormatted. Contacte o emissor para renovacao."
            ];
        }
    }

    // Verificar numero maximo de downloads
    if (isset($metadata['max_downloads']) && isset($metadata['download_count'])) {
        if ($metadata['download_count'] >= $metadata['max_downloads']) {
            return [
                'valid' => false,
                'message' => "Limite de downloads atingido ({$metadata['max_downloads']}). Contacte o emissor."
            ];
        }
    }

    return ['valid' => true, 'message' => null];
}

/**
 * Incrementa contador de downloads no ficheiro .metadata.json
 *
 * Usa file locking (flock) para garantir operacoes atomicas.
 * Atualiza tambem o campo 'last_download' com timestamp atual.
 * Se metadata nao existir, nao faz nada (fail silently).
 *
 * @param string $tokenDir Caminho absoluto do diretorio do token
 * @return void
 */
function incrementDownloadCount(string $tokenDir): void {
    $metadataFile = $tokenDir . DIRECTORY_SEPARATOR . '.metadata.json';

    if (!file_exists($metadataFile)) {
        return; // Sem metadata, nao trackear
    }

    $handle = fopen($metadataFile, 'c+');
    if (!$handle) {
        error_log("Erro ao abrir metadata para incremento: $metadataFile");
        return;
    }

    if (flock($handle, LOCK_EX)) {
        $fileSize = filesize($metadataFile);
        $content = $fileSize > 0 ? fread($handle, $fileSize) : '{}';
        $metadata = json_decode($content, true) ?? [];

        // Incrementar contador
        $metadata['download_count'] = ($metadata['download_count'] ?? 0) + 1;
        $metadata['last_download'] = date('Y-m-d H:i:s');

        // Escrever atomicamente
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($metadata, JSON_PRETTY_PRINT));

        flock($handle, LOCK_UN);
    }

    fclose($handle);
}

/**
 * Regista tentativas de acesso em formato JSON estruturado
 *
 * Gera logs com informacoes de seguranca incluindo IP ofuscado,
 * token parcialmente mascarado e user agent. Respeita RGPD/GDPR
 * ao ofuscar IPs automaticamente.
 *
 * @param string $action Acao realizada (ex: "SUCCESS", "TOKEN_EXPIRED")
 * @param string $token Token usado (sera mascarado no log)
 * @param bool $success True se operacao foi bem-sucedida, false caso contrario
 * @param string|null $details Detalhes adicionais opcionais
 * @return void
 */
function logAccess(string $action, string $token, bool $success, ?string $details = null): void {
    if (!LOG_ACCESS) {
        return;
    }

    $logFile = __DIR__ . '/../logs/access.log';

    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0750, true);
    }

    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => obfuscateIP(getClientIP()),
        'action' => $action,
        'token' => strlen($token) >= 8 ? substr($token, 0, 4) . '****' . substr($token, -4) : '****',
        'success' => $success,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100),
        'details' => $details
    ];

    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Ofusca endereco IP para conformidade com RGPD/GDPR
 *
 * Mantem apenas os primeiros 2 octetos (IPv4) ou 2 segmentos (IPv6)
 * para permitir analise geografica basica sem identificacao precisa.
 *
 * Exemplos:
 * - IPv4: 192.168.1.100 -> 192.168.xxx.xxx
 * - IPv6: 2001:db8::1 -> 2001:db8:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx
 *
 * @param string $ip Endereco IP completo (IPv4 ou IPv6)
 * @return string IP ofuscado ou "xxx.xxx.xxx.xxx" se invalido
 */
function obfuscateIP(string $ip): string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        return $parts[0] . ':' . $parts[1] . ':xxxx:xxxx:xxxx:xxxx:xxxx:xxxx';
    }
    return 'xxx.xxx.xxx.xxx';
}

/**
 * Centraliza tratamento de erros com redirect ou pagina de erro
 *
 * Oferece dois modos:
 * 1. Redirect mode: Guarda mensagem na sessao e redireciona para index.php
 * 2. Template mode: Renderiza pagina de erro com codigo HTTP apropriado
 *
 * Esta funcao termina sempre a execucao do script (never return type).
 *
 * @param string $message Mensagem de erro a apresentar ao utilizador
 * @param int $code Codigo de resposta HTTP (ex: 400, 403, 404, 500)
 * @param bool $redirect Se true, redireciona para index.php; se false, renderiza template de erro
 * @return never Esta funcao nunca retorna (termina com exit)
 */
function handleError(string $message, int $code = 400, bool $redirect = false): never {
    if ($redirect) {
        $_SESSION['Error'] = $message;
        header('Location: /index.php');
        exit;
    } else {
        http_response_code($code);
        require_once __DIR__ . '/../includes/error-template.php';
        renderError($code, $message);
        exit;
    }
}
