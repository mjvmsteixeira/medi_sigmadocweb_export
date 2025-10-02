<?php
http_response_code(500);
require_once __DIR__ . '/includes/error-template.php';

renderErrorPage(
    500,
    'Erro Interno do Servidor',
    'Ocorreu um erro inesperado no servidor.',
    'Por favor, tente novamente mais tarde. Se o problema persistir, contacte o suporte tecnico.',
    'danger'
);
