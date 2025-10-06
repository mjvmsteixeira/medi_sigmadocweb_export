<?php
/**
 * Router para Servidor de Desenvolvimento PHP
 *
 * Este router permite URLs amigaveis no PHP built-in server.
 * Em producao com Apache, o .htaccess trata das rewrite rules.
 *
 * @author @mjvmst
 * @version 3.1
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Remover query string
$path = strtok($uri, '?');

// Debug
file_put_contents(__DIR__ . '/logs/router.log', date('Y-m-d H:i:s') . " - Path: $path\n", FILE_APPEND);

// Servir ficheiros estaticos diretamente
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false; // Deixar o PHP servir o ficheiro
}

// URL amigavel: /TOKEN -> search.php?token=TOKEN
// Exemplo: /DEMO87654321
if (preg_match('#^/([a-zA-Z0-9.]{8,64})$#', $path, $matches)) {
    file_put_contents(__DIR__ . '/logs/router.log', date('Y-m-d H:i:s') . " - Match! Token: {$matches[1]}\n", FILE_APPEND);

    $_GET['token'] = $matches[1];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['QUERY_STRING'] = 'token=' . urlencode($matches[1]);

    file_put_contents(__DIR__ . '/logs/router.log', date('Y-m-d H:i:s') . " - Calling search.php\n", FILE_APPEND);
    require __DIR__ . '/search.php';
    exit;
}

// URL amigavel: /exp/TOKEN/qualquercoisa -> redireciona para search
// Compatibilidade total - redireciona para pagina de pesquisa protegida:
//   /exp/prhqafqf.zzt/prhqafqf.zzt.zip  -> /search.php?token=prhqafqf.zzt
//   /exp/prhqafqf.zzt/Indice.pdf        -> /search.php?token=prhqafqf.zzt
// Download controlado atraves da interface de pesquisa
if (preg_match('#^/exp/([a-zA-Z0-9.]{8,64})/(.+)$#', $path, $matches)) {
    header('Location: /search.php?token=' . urlencode($matches[1]), true, 302);
    exit;
}

// Se nao houver match, servir normalmente
return false;
