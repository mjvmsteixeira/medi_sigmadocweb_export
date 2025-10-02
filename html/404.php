<?php
http_response_code(404);
require_once __DIR__ . '/includes/error-template.php';

renderErrorPage(
    404,
    'Pagina Nao Encontrada',
    'A pagina que procura nao existe ou foi removida.',
    'Verifique o endereco ou volte a pagina inicial.',
    'warning'
);
