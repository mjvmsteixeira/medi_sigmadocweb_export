<?php
http_response_code(403);
require_once __DIR__ . '/includes/error-template.php';

renderErrorPage(
    403,
    'Acesso Negado',
    'Nao tem permissao para aceder a este recurso.',
    'Se considera que isto e um erro, por favor contacte o suporte.',
    'danger'
);
