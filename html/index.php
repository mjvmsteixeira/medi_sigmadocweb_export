<?php
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/env.php';
header("Content-type: text/html; charset=utf-8");

// Gerar token CSRF
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(LOGO_ALT, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/theme.php">
</head>
<body>
  <header class="container-fluid">
    <img src="<?= htmlspecialchars(LOGO_URL, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(LOGO_ALT, ENT_QUOTES, 'UTF-8') ?>">
    <h1 class="h4 m-0"><?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <main class="container text-center">
    <form id="searchthis" action="/search.php" method="post" class="row justify-content-center g-2" aria-label="Formulario de consulta de processos">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

      <div class="col-md-6">
        <label for="token" class="visually-hidden">Token de acesso</label>
        <input
          class="form-control"
          id="token"
          name="token"
          type="text"
          placeholder="Insira o seu token!"
          pattern="[a-zA-Z0-9.]{8,64}"
          minlength="8"
          maxlength="64"
          aria-describedby="tokenHelp"
          autocomplete="off"
          required
        />
        <small id="tokenHelp" class="form-text text-muted d-none">
          Token invalido. Use apenas letras, numeros e pontos (8-64 caracteres).
        </small>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" type="submit" id="submitBtn">
          <span class="spinner-border spinner-border-sm d-none" id="loadingSpinner" role="status" aria-hidden="true"></span>
          <span id="btnText">Consultar</span>
        </button>
      </div>
    </form>

    <!-- Modal de Ajuda -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="helpModalLabel">Como obter o token?</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p>O token de acesso e fornecido pela Autarquia!</p>
            <p><strong>Onde encontrar:</strong></p>
            <ul class="text-start">
              <li>Email de confirmacao de submissao</li>
              <li>Documento de protocolo</li>
              <li>Balcao de atendimento presencial</li>
            </ul>
            <p class="mb-0"><strong>Duvidas?</strong> Contacte-nos atraves do <a href="mailto:geral@empresa.pt">geral@empresa.pt</a></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Erro -->
    <?php
    $errorMessage = '';
    if (isset($_SESSION['Error']) && is_string($_SESSION['Error']) && $_SESSION['Error'] !== "") {
      $errorMessage = $_SESSION['Error'];
      unset($_SESSION['Error']); // Limpar imediatamente apos capturar
    }
    ?>
    <?php if ($errorMessage !== ''): ?>
      <!-- Fallback: Alert visivel se JavaScript falhar -->
      <noscript>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
          <strong>Erro:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
      </noscript>

      <!-- Modal de erro (requer JavaScript) -->
      <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title" id="errorModalLabel">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill me-2" viewBox="0 0 16 16" style="vertical-align: text-bottom;">
                  <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                </svg>
                Erro
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <p class="mb-0"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <footer>
    Desenvolvido por <?= htmlspecialchars(FOOTER_DEVELOPER, ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
    Versao <?= htmlspecialchars(FOOTER_VERSION, ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
    © <?= htmlspecialchars(FOOTER_YEAR, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(FOOTER_ORGANIZATION, ENT_QUOTES, 'UTF-8') ?>
    <?php if (FOOTER_EMAIL): ?>
      &nbsp;|&nbsp; <a href="mailto:<?= htmlspecialchars(FOOTER_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(FOOTER_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </footer>

  <!-- Bootstrap Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

  <script nonce="<?= htmlspecialchars(getCSPNonce(), ENT_QUOTES, 'UTF-8') ?>">
    // Loading state no formulario
    document.getElementById('searchthis').addEventListener('submit', function(e) {
      const submitBtn = document.getElementById('submitBtn');
      const spinner = document.getElementById('loadingSpinner');
      const btnText = document.getElementById('btnText');

      submitBtn.disabled = true;
      spinner.classList.remove('d-none');
      btnText.textContent = ' A consultar...';
    });

    // Validacao em tempo real
    const tokenInput = document.getElementById('token');
    const tokenHelp = document.getElementById('tokenHelp');

    tokenInput.addEventListener('input', function() {
      const value = this.value;
      const isValid = /^[a-zA-Z0-9.]{8,64}$/.test(value);

      if (value.length > 0 && !isValid) {
        this.classList.add('is-invalid');
        tokenHelp.classList.remove('d-none');
      } else {
        this.classList.remove('is-invalid');
        tokenHelp.classList.add('d-none');
      }
    });

    // Mostrar modal de erro se existir
    const errorModalEl = document.getElementById('errorModal');
    if (errorModalEl) {
      const errorModal = new bootstrap.Modal(errorModalEl);
      errorModal.show();
    }
  </script>

</body>
</html>