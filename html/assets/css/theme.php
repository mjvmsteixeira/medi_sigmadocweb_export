<?php
/**
 * Gerador de CSS Dinamico
 * Gera CSS baseado nas variaveis de ambiente
 */

require_once __DIR__ . '/../../config/env.php';

// Definir header para CSS
header('Content-Type: text/css; charset=utf-8');

// FIX: Cache mais agressivo em producao (24h em vez de 1h)
if (!APP_DEBUG) {
    $cacheTime = 86400; // 24 horas
    header('Cache-Control: public, max-age=' . $cacheTime);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
    header('ETag: "' . md5(APP_URL . THEME_PRIMARY_COLOR . THEME_SECONDARY_COLOR) . '"');
} else {
    // Desenvolvimento: sem cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
}
?>
/**
 * CSS Dinamico - Sistema de Consulta de Processos
 * Gerado automaticamente a partir do .env
  */

:root {
  /* Cores do Tema */
  --primary-color: <?= THEME_PRIMARY_COLOR ?>;
  --secondary-color: <?= THEME_SECONDARY_COLOR ?>;
  --success-color: <?= THEME_SUCCESS_COLOR ?>;
  --danger-color: <?= THEME_DANGER_COLOR ?>;
  --warning-color: <?= THEME_WARNING_COLOR ?>;
  --info-color: <?= THEME_INFO_COLOR ?>;
  --background-color: <?= THEME_BACKGROUND_COLOR ?>;

  /* Logo */
  --logo-height: <?= LOGO_HEIGHT ?>;
}

/* Layout Geral */
body {
  background-color: var(--background-color);
}

/* Header */
header {
  background-color: var(--primary-color);
  color: white;
}

header img {
  height: var(--logo-height);
}

/* Botoes Primarios */
.btn-primary {
  background-color: var(--primary-color) !important;
  border-color: var(--primary-color) !important;
}

.btn-primary:hover {
  background-color: var(--secondary-color) !important;
  border-color: var(--secondary-color) !important;
}

/* Cards */
.card-header.bg-primary {
  background-color: var(--primary-color) !important;
}

/* Alertas */
.alert-info {
  background-color: rgba(<?= hexToRgb(THEME_INFO_COLOR) ?>, 0.1);
  border-color: var(--info-color);
  color: var(--info-color);
}

.alert-warning {
  background-color: rgba(<?= hexToRgb(THEME_WARNING_COLOR) ?>, 0.1);
  border-color: var(--warning-color);
  color: #856404;
}

.alert-danger,
.modal-content.border-danger {
  border-color: var(--danger-color) !important;
}

.bg-danger {
  background-color: var(--danger-color) !important;
}

/* Links */
a {
  color: var(--secondary-color);
}

a:hover {
  color: var(--primary-color);
}

/* Focus para acessibilidade */
a:focus,
button:focus,
input:focus,
select:focus,
textarea:focus {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Badge */
.badge.bg-light {
  background-color: #f8f9fa !important;
  color: #212529 !important;
}

/* Badges de Estado (para search.php) */
.badge.bg-success {
  background-color: var(--success-color) !important;
}

.badge.bg-warning {
  background-color: var(--warning-color) !important;
  color: #212529 !important;
}

.badge.bg-info {
  background-color: var(--info-color) !important;
}

.badge.bg-danger {
  background-color: var(--danger-color) !important;
}

/* Modal de Erro */
.modal-header.bg-danger {
  background-color: var(--danger-color) !important;
}

/* Spinner de Loading */
.spinner-border {
  border-color: currentColor;
  border-right-color: transparent;
}

/* Validacao de Input Invalido */
.is-invalid {
  border-color: var(--danger-color) !important;
}

/* Links de Ajuda */
.text-muted a {
  color: #6c757d;
}

.text-muted a:hover {
  color: var(--primary-color);
}

/* Paginas de Erro */
.text-danger {
  color: var(--danger-color) !important;
}

.text-warning {
  color: var(--warning-color) !important;
}

/* Responsividade do Logo */
@media (max-width: 576px) {
  header img {
    height: calc(var(--logo-height) * 0.8);
  }
}

<?php
/**
 * Converte HEX para RGB
 * @param string $hex Cor em formato #RRGGBB
 * @return string RGB separado por virgulas (ex: "255, 0, 0")
 */
function hexToRgb(string $hex): string {
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return "$r, $g, $b";
}
?>
