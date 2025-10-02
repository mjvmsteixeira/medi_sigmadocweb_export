<?php
/**
 * Template para Paginas de Erro
 * @param int $code Codigo HTTP (403, 404, 500)
 * @param string $title Titulo do erro
 * @param string $message Mensagem principal
 * @param string $description Descricao adicional
 * @param string $colorClass Classe CSS Bootstrap (danger, warning, info)
 */

require_once __DIR__ . '/../config/env.php';

function renderErrorPage(int $code, string $title, string $message, string $description, string $colorClass = 'danger') {
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title><?= $code ?> - <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(LOGO_ALT, ENT_QUOTES, 'UTF-8') ?></title>
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

  <main class="container text-center my-5">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <h2 class="display-1 text-<?= htmlspecialchars($colorClass, ENT_QUOTES, 'UTF-8') ?>"><?= $code ?></h2>
        <h3 class="mb-4"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="lead"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="/index.php" class="btn btn-primary mt-3">Voltar a Pagina Inicial</a>
        <?php if ($code === 500 && FOOTER_EMAIL): ?>
          <br>
          <a href="mailto:<?= htmlspecialchars(FOOTER_EMAIL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary mt-2">Contactar Suporte</a>
        <?php endif; ?>
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
</body>
</html>
<?php
}
?>
