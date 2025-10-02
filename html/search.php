<?php
/**
 * Script de Pesquisa de Tokens
 *
 * Valida tokens e lista documentos disponiveis para download.
 * Implementa seguranca atraves de:
 * - CSRF protection
 * - Rate limiting (anti-enumeracao)
 * - Token format validation
 * - Token expiration checking
 * - Symlink security validation
 *
 * @author @mjvmst
 * @version 3.1
 */

require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';
header("Content-type: text/html; charset=utf-8");

// Aceitar POST (formulario) ou GET (URL amigavel)
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isGet = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token']);

if (!$isPost && !$isGet) {
    header('Location: /index.php');
    exit;
}

// Validar CSRF Token apenas para POST (formulario)
if ($isPost && !validateCSRFToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['Error'] = 'Sessao invalida. Por favor, tente novamente.';
    logAccess('CSRF_FAIL', $_POST['token'] ?? 'none', false);
    header('Location: /index.php');
    exit;
}

// Obter token do POST ou GET
$token = trim($isPost ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''));

// Rate Limiting - por IP e por token (anti-enumeracao)
$clientIP = getClientIP();
$tokenPreview = substr($token, 0, 8);
$rateLimitKey = 'search_' . $clientIP . '_' . $tokenPreview;

// FIX: Usar constantes do .env em vez de magic numbers
if (!checkRateLimit($rateLimitKey, RATE_LIMIT_SEARCH_ATTEMPTS, RATE_LIMIT_SEARCH_WINDOW)) {
    $_SESSION['Error'] = 'Demasiadas tentativas. Por favor, aguarde ' . (RATE_LIMIT_SEARCH_WINDOW / 60) . ' minutos e tente novamente.';
    logAccess('RATE_LIMIT', $tokenPreview, false);
    header('Location: /index.php');
    exit;
}

if (empty($token)) {
    $_SESSION['Error'] = 'Por favor, insira o seu token !';
    header('Location: /index.php');
    exit;
}

// Validar formato do token
if (!validateTokenFormat($token)) {
    $_SESSION['Error'] = 'Formato de token invalido. Use apenas letras, numeros e pontos (8-64 caracteres).';
    logAccess('INVALID_FORMAT', $token, false);
    header('Location: /index.php');
    exit;
}

// Diretorio de exportacoes (link simbolico)
$expDir = __DIR__ . '/exp';

// FIX: Validar symlink de forma segura
if (is_link($expDir)) {
    $linkTarget = readlink($expDir);

    // Validar que o destino existe e e seguro
    if ($linkTarget === false || !is_dir($linkTarget) || !is_readable($linkTarget)) {
        $_SESSION['Error'] = 'Erro no sistema. Por favor, contacte o suporte tecnico.';
        logAccess('EXP_SYMLINK_INVALID', $token, false, 'Symlink target invalid');
        error_log("SECURITY: Symlink /exp invalido ou inacessivel: $linkTarget");
        header('Location: /index.php');
        exit;
    }

    // Verificar que nao aponta para diretorios sensiveis do sistema
    $realTarget = realpath($linkTarget);
    $dangerousPaths = ['/etc', '/var/www', '/root', '/home', '/usr', '/bin', '/sbin'];
    foreach ($dangerousPaths as $dangerous) {
        if ($realTarget && strpos($realTarget, $dangerous) === 0) {
            $_SESSION['Error'] = 'Erro no sistema. Por favor, contacte o suporte tecnico.';
            logAccess('EXP_SYMLINK_DANGEROUS', $token, false, "Points to: $realTarget");
            error_log("SECURITY: Symlink /exp aponta para localizacao perigosa: $realTarget");
            header('Location: /index.php');
            exit;
        }
    }
}

// Verificar se o diretorio existe
if (!is_dir($expDir) || !is_readable($expDir)) {
    $_SESSION['Error'] = 'Erro no sistema. Por favor, contacte o suporte tecnico.';
    logAccess('EXP_DIR_ERROR', $token, false);
    error_log("Diretorio /exp nao encontrado ou nao acessivel: $expDir");
    header('Location: /index.php');
    exit;
}

// Construir caminho seguro para o subdiretorio do token
$tokenDir = $expDir . DIRECTORY_SEPARATOR . $token;
$realTokenDir = realpath($tokenDir);

// Validar que o caminho esta dentro do diretorio permitido
$realExpDir = realpath($expDir);

// Variavel para armazenar ficheiros ZIP
$zipFiles = [];

// Cenario 1: Verificar se existe pasta com o nome do token
if ($realTokenDir !== false && strpos($realTokenDir, $realExpDir) === 0 && is_dir($realTokenDir)) {
    // FIX: Verificar expiracao do token (apenas para pastas)
    $expirationCheck = checkTokenExpiration($realTokenDir);
    if (!$expirationCheck['valid']) {
        $_SESSION['Error'] = $expirationCheck['message'];
        logAccess('TOKEN_EXPIRED', $token, false, $expirationCheck['message']);
        header('Location: /index.php');
        exit;
    }

    // Listar ficheiros ZIP no diretorio do token
    $zipFiles = glob($realTokenDir . DIRECTORY_SEPARATOR . '*.zip');

    if ($zipFiles === false) {
        $_SESSION['Error'] = 'Erro ao listar ficheiros. Por favor, tente novamente.';
        logAccess('GLOB_ERROR', $token, false);
        error_log("Erro ao executar glob em: $realTokenDir");
        header('Location: /index.php');
        exit;
    }

    // Filtrar apenas ficheiros (nao diretorios)
    $zipFiles = array_filter($zipFiles, 'is_file');
}

// Cenario 2: Se nao existe pasta, procurar ficheiro token.zip
if (empty($zipFiles)) {
    $tokenZipFile = $expDir . DIRECTORY_SEPARATOR . $token . '.zip';
    $realTokenZipFile = realpath($tokenZipFile);

    // Validar que o ficheiro esta dentro do diretorio permitido e existe
    if ($realTokenZipFile !== false &&
        strpos($realTokenZipFile, $realExpDir) === 0 &&
        is_file($realTokenZipFile)) {
        $zipFiles = [$realTokenZipFile];
    }
}

// Se nao encontrou nem pasta nem ficheiro
if (empty($zipFiles)) {
    $_SESSION['Error'] = 'Token nao encontrado. Verifique se inseriu corretamente.';
    logAccess('TOKEN_NOT_FOUND', $token, false);
    header('Location: /index.php');
    exit;
}

// Preparar informacoes dos ficheiros
$documentos = [];
foreach ($zipFiles as $zipPath) {
    $fileName = basename($zipPath);
    $fileSize = filesize($zipPath);
    $fileDate = filemtime($zipPath);

    $documentos[] = [
        'nome' => $fileName,
        'tamanho' => formatBytes($fileSize),
        'data' => date('Y-m-d H:i', $fileDate),
        'download_url' => '/download.php?' . http_build_query([
            'token' => $token,
            'file' => $fileName
        ])
    ];
}

// Sucesso - Log
logAccess('SUCCESS', $token, true);
$csrfToken = generateCSRFToken();

/**
 * Formata tamanho de ficheiro em bytes para formato legivel (B, KB, MB, GB)
 *
 * Converte valores numericos em representacao human-readable usando
 * unidades binarias (1024 bytes = 1 KB).
 *
 * Exemplos:
 * - 1024 -> "1.00 KB"
 * - 1048576 -> "1.00 MB"
 * - 5242880 -> "5.00 MB"
 *
 * @param int $bytes Tamanho em bytes (nao pode ser negativo)
 * @param int $precision Numero de casas decimais (default: 2)
 * @return string Tamanho formatado com unidade (ex: "5.25 MB")
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Documentos Disponiveis - <?= htmlspecialchars(LOGO_ALT, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/theme.php">
</head>
<body>
  <header class="container-fluid">
    <img src="<?= htmlspecialchars(LOGO_URL, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(LOGO_ALT, ENT_QUOTES, 'UTF-8') ?>">
    <h1 class="h4 m-0"><?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <main class="container my-4">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Documentos Disponiveis</h2>
            <span class="badge bg-light text-dark"><?= count($documentos) ?> <?= count($documentos) === 1 ? 'ficheiro' : 'ficheiros' ?></span>
          </div>
          <div class="card-body">
            <div class="alert alert-info">
              <strong>Token:</strong> <?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Ficheiro</th>
                    <th class="text-center">Tamanho</th>
                    <th class="text-center">Data</th>
                    <th class="text-center">Acao</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($documentos as $doc): ?>
                    <tr>
                      <td>
                        <i class="bi bi-file-earmark-zip"></i>
                        <?= htmlspecialchars($doc['nome'], ENT_QUOTES, 'UTF-8') ?>
                      </td>
                      <td class="text-center">
                        <small class="text-muted"><?= htmlspecialchars($doc['tamanho'], ENT_QUOTES, 'UTF-8') ?></small>
                      </td>
                      <td class="text-center">
                        <small class="text-muted"><?= htmlspecialchars($doc['data'], ENT_QUOTES, 'UTF-8') ?></small>
                      </td>
                      <td class="text-center">
                        <a href="<?= htmlspecialchars($doc['download_url'], ENT_QUOTES, 'UTF-8') ?>"
                           class="btn btn-sm btn-primary"
                           download>
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                          </svg>
                          Descarregar
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer text-end">
            <a href="/index.php" class="btn btn-secondary">Nova Consulta</a>
          </div>
        </div>

        <div class="mt-3">
          <div class="alert alert-warning">
            <strong>⚠ Importante:</strong> Os documentos estao disponiveis apenas durante um periodo limitado.
            Guarde os ficheiros que necessitar no seu computador.
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer>
    Desenvolvido por <?= htmlspecialchars(FOOTER_DEVELOPER, ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
    Versao <?= htmlspecialchars(FOOTER_VERSION, ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
    © <?= htmlspecialchars(FOOTER_YEAR, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(FOOTER_ORGANIZATION, ENT_QUOTES, 'UTF-8') ?>
    <?php if (FOOTER_EMAIL): ?>
      &nbsp;|&nbsp; <a href="mailto:<?= htmlspecialchars(FOOTER_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(FOOTER_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoYz1HiPTC0zjU6z9g9ZCk5lZl5FZC1UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
</body>
</html>
