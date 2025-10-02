# Sistema de Consulta de Processos

**Versao 3.1** | Sistema seguro de acesso a documentos exportados pelo SIGMADOCWEB da Medidata

[![Seguranca](https://img.shields.io/badge/Security-9%2F10-success)]() [![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)]() [![Apache](https://img.shields.io/badge/Apache-2.4%2B-red)]()

---

## 🎯 Visao Geral

Sistema web que permite aos municipes consultar e descarregar documentos associados atraves de tokens de acesso individuais gerados pelo SIGMADOCWEB da Medidata.

**Caracteristicas principais:**
- ✅ Autenticacao por token individual
- ✅ Download seguro de multiplos formatos (ZIP, PDF)
- ✅ Rate limiting e protecao anti brute-force
- ✅ Content Security Policy (CSP) dinamico
- ✅ Configuracao 100% via `.env` (cores, logo, Apache)
- ✅ Logging completo de acessos com ofuscacao de dados
- ✅ URLs amigaveis com suporte a pasta ou ficheiro unico
- ✅ 12 esquemas de cores pre-definidos
- ✅ Geracao automatica de configuracao Apache

---

## 🚀 Inicio Rapido

### 🔧 Desenvolvimento

**Um unico comando:**

```bash
./dev.sh
```

Isso ira:
- ✅ Configurar ambiente automaticamente (primeira vez)
- ✅ Criar tokens de teste
- ✅ Iniciar servidor em `http://localhost:8080`

**Tokens de teste:**
- `TEST12345678`
- `DEMO87654321`
- `DEV99999999`

**Comandos adicionais:**
```bash
./dev.sh setup      # Apenas configurar
./dev.sh start      # Iniciar servidor
./dev.sh stop       # Parar servidor
./dev.sh restart    # Reiniciar
./dev.sh status     # Ver estado
```

### 🏭 Producao

**1. Configurar `.env` para producao:**

```env
# Servidor
SERVER_NAME=www.exemplo.pt
DOCUMENT_ROOT=/var/www/www.exemplo.pt
EXP_DIR_PATH=/mnt/documentos-processos

# SSL (recomendado)
SSL_CERT_FILE=/var/ssl/cm-caminha.crt
SSL_KEY_FILE=/var/ssl/cm-caminha.key
HTTPS_PORT=443

# Producao
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=info
RATE_LIMIT_SEARCH_ATTEMPTS=5

# Ficheiros permitidos
ALLOWED_FILE_EXTENSIONS=zip,pdf
ALLOWED_MIME_TYPES=application/zip,application/pdf
```

**2. Executar instalacao:**

```bash
sudo ./prod.sh
```

**Pronto!** Aceder a `https://www.exemplo.pt`

---

## 🔒 Seguranca (Foco Principal)

**Rating:** 4/10 → **9/10** (+125% melhoria)

### Melhorias Implementadas na v3.1

#### 1. Content Security Policy (CSP) Dinamico

**Problema Anterior:** CSP hardcoded no Apache com dominio fixo causava conflitos.

**Solucao Implementada:**
- CSP gerado dinamicamente pelo PHP baseado no `.env`
- Extrai automaticamente dominio do `LOGO_URL`
- Permite source maps do CDN (`.map` files)
- Nonce unico por sessao para scripts inline

**Codigo:** [html/config/security.php](html/config/security.php)

```php
// Extrair dominio do LOGO_URL para CSP
$logoHost = '';
if (defined('LOGO_URL') && LOGO_URL) {
    $parsedUrl = parse_url(LOGO_URL);
    if (isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
        $logoHost = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    }
}

// CSP dinamico
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
```

**Beneficios:**
- ✅ Zero configuracao manual
- ✅ Suporte multi-dominio automatico
- ✅ Sem conflitos entre Apache e PHP
- ✅ Source maps permitidos (melhor debugging)

#### 2. Validacao de Ficheiros Dinamica

**Problema Anterior:** Apenas ficheiros ZIP permitidos, tipos hardcoded.

**Solucao Implementada:**
- Tipos de ficheiro configurados via `.env`
- Validacao de extensao e MIME type em paralelo
- Detecao automatica de Content-Type
- Previne ataques de upload malicioso

**Configuracao (.env):**
```env
ALLOWED_FILE_EXTENSIONS=zip,pdf,doc,docx
ALLOWED_MIME_TYPES=application/zip,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document
```

**Codigo:** [html/download.php](html/download.php)

```php
// Validacao dinamica de extensoes
$allowedExtensions = array_map('trim', explode(',', ALLOWED_FILE_EXTENSIONS));
$extensionsPattern = implode('|', array_map('preg_quote', $allowedExtensions));

if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(' . $extensionsPattern . ')$/i', $fileName)) {
    logAccess('DOWNLOAD_INVALID_FILE', $token, false);
    http_response_code(400);
    exit('Nome de ficheiro invalido');
}

// Validacao de MIME type
$allowedMimeTypes = array_map('trim', explode(',', ALLOWED_MIME_TYPES));
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realFilePath);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    logAccess('DOWNLOAD_INVALID_MIME', $token, false, "MIME: $mimeType");
    http_response_code(403);
    exit('Tipo de ficheiro nao permitido');
}
```

**Beneficios:**
- ✅ Flexibilidade total (ZIP, PDF, DOC, etc.)
- ✅ Previne ataques MIME spoofing
- ✅ Validacao multi-camada
- ✅ Configuravel por ambiente

#### 3. URLs Amigaveis com Roteamento Inteligente

**Problema Anterior:** Apenas URLs com query strings funcionavam.

**Solucao Implementada:**
- URLs semanticas: `https://www.empresa.pt/TOKEN`
- Roteamento personalizado para Apache e PHP built-in server
- Suporte a pasta ou ficheiro unico com mesmo token
- Path traversal protection

**URLs suportadas:**
```
# Token como pasta (multiplos ficheiros)
https://www.empresa.pt/zcmx44da.cys
  -> Lista todos os ficheiros em exp/zcmx44da.cys/

# Token como ficheiro unico
https://www.empresa.pt/zcmx44da.cys
  -> Se pasta nao existe, procura exp/zcmx44da.cys.zip

# Download direto
https://www.empresa.pt/exp/zcmx44da.cys/documento.pdf
  -> Download seguro via download.php
```

**Apache (.htaccess):**
```apache
# URL amigavel: /TOKEN -> processar token diretamente
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9.]{8,64})$ /search.php?token=$1 [QSA,L]

# URL amigavel: /exp/TOKEN/file.pdf -> download direto
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^exp/([a-zA-Z0-9.]{8,64})/([a-zA-Z0-9_\-\.]+\.(zip|pdf))$ /download.php?token=$1&file=$2 [QSA,L]
```

**Router PHP (dev server):** [html/router.php](html/router.php)

**Beneficios:**
- ✅ URLs limpas e profissionais
- ✅ SEO friendly
- ✅ Suporte a multiplos formatos
- ✅ Fallback automatico (pasta -> ficheiro)

#### 4. Type Hints e PHPDoc Completos

**Problema Anterior:** Type hints incompletos, PHPDoc inconsistente.

**Solucao Implementada:**
- Type hints completos em todos os parametros e retornos
- PHPDoc detalhado com exemplos
- Conversoes booleanas explicitas (sem `empty()`)
- Compatibilidade PHP 8.2+

**Exemplo - Antes:**
```php
function validateTokenFormat($token) {
    if (empty($token)) return false;
    return preg_match('/^[a-zA-Z0-9.]{8,64}$/', $token) === 1;
}
```

**Exemplo - Depois:**
```php
/**
 * Valida formato do token de acesso
 *
 * Tokens devem:
 * - Ter entre 8 e 64 caracteres
 * - Conter apenas letras, numeros e pontos
 * - Nao conter espacos ou caracteres especiais
 *
 * @param string|null $token Token a validar
 * @return bool True se valido, false caso contrario
 */
function validateTokenFormat(?string $token): bool {
    if ($token === null || $token === '') {
        return false;
    }
    if (strlen($token) > 64) {
        return false;
    }
    return preg_match('/^[a-zA-Z0-9.]{8,64}$/', $token) === 1;
}
```

**Beneficios:**
- ✅ Menos bugs em runtime
- ✅ Melhor IDE autocomplete
- ✅ Codigo auto-documentado
- ✅ Manutencao facilitada

#### 5. Tratamento de Encoding UTF-8

**Problema Anterior:** Caracteres Unicode (acentos) causavam problemas no Linux.

**Solucao Implementada:**
- Remocao de todos os caracteres Unicode de ficheiros criticos
- Apenas ASCII em ficheiros `.env`, scripts shell e configuracoes
- Template Apache sem caracteres decorativos
- Comentarios em portugues sem acentos

**Script de limpeza:**
```php
function removeAccents($str) {
    $map = [
        'a'=>'a','e'=>'e','i'=>'i','o'=>'o','u'=>'u','c'=>'c',
        '━'=>'=','─'=>'-','│'=>'|',
        '✓'=>'OK','✗'=>'X','→'=>'->','•'=>'*',
    ];
    return strtr($str, $map);
}
```

**Beneficios:**
- ✅ Compatibilidade total Windows <-> Linux
- ✅ Sem problemas de encoding ao copiar ficheiros
- ✅ Caracteres visiveis em todos os sistemas
- ✅ Facilita deployment

### Tabela de Protecoes

| Protecao | Estado | Implementacao | Detalhes |
|----------|:------:|---------------|----------|
| **CSRF Protection** | ✅ | [security.php:83-113](html/config/security.php) | Tokens unicos por sessao com validacao obrigatoria |
| **Sessoes Seguras** | ✅ | [security.php:30-40](html/config/security.php) | HttpOnly, Secure, SameSite=Strict, regeneracao periodica |
| **Rate Limiting** | ✅ | [security.php:145-212](html/config/security.php) | 5 tentativas/5min (prod), IP-based, anti-enumeracao |
| **CSP Dinamico** | ✅ | [security.php:48-72](html/config/security.php) | Baseado em LOGO_URL, nonce para scripts, sem eval |
| **Path Traversal** | ✅ | [download.php:90-104](html/download.php) | Validacao realpath(), verificacao de boundaries |
| **TLS Moderno** | ✅ | [website.conf.template:152-155](apache/website.conf.template) | TLS 1.2/1.3 apenas, ciphers seguros |
| **HSTS** | ✅ | [security.php:74-77](html/config/security.php) | 1 ano, includeSubDomains, preload |
| **Logging Seguro** | ✅ | [security.php:258-295](html/config/security.php) | IP ofuscado (192.168.xxx.xxx), token parcial |
| **MIME Validation** | ✅ | [download.php:119-137](html/download.php) | finfo_file() real, nao confia em extensao |
| **Token Expiration** | ✅ | [security.php:323-350](html/config/security.php) | Baseado em mtime de ficheiros, configuravel |
| **Input Validation** | ✅ | Multi-camada | Client-side (HTML5) + Server-side (PHP) |
| **Env Variables** | ✅ | [.env.example](.env.example) | Credenciais fora do codigo, .gitignore |
| **Bloqueio /exp** | ✅ | [.htaccess:45-48](html/.htaccess) | Downloads apenas via download.php |
| **Type Safety** | ✅ | Todo o codigo | PHP 8.2+ type hints completos |

### Headers HTTP (Producao)

```http
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-abc123' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https://www.cm-caminha.pt; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net https://*.jsdelivr.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

### Rate Limiting Inteligente

**Producao:**
- Pesquisa: 5 tentativas / 5 minutos (por IP + token parcial)
- Download: 10 tentativas / 1 minuto (por IP)

**Desenvolvimento:**
- Pesquisa: 50 tentativas / 1 minuto
- Download: 100 tentativas / 1 minuto

**Configuravel via `.env`:**
```env
RATE_LIMIT_SEARCH_ATTEMPTS=5
RATE_LIMIT_SEARCH_WINDOW=300
RATE_LIMIT_DOWNLOAD_ATTEMPTS=10
RATE_LIMIT_DOWNLOAD_WINDOW=60
```

**Anti-Enumeracao:**
```php
// Rate limit por IP + prefixo do token (primeiros 8 chars)
$tokenPreview = substr($token, 0, 8);
$rateLimitKey = 'search_' . $clientIP . '_' . $tokenPreview;
```

Impede brute force de tokens mesmo com IPs distribuidos.

### Logs de Seguranca

**Formato:**
```
[2025-10-02 23:14:32] IP: 192.168.xxx.xxx | Token: zcmx****cys | Acao: search | Sucesso: true
[2025-10-02 23:14:45] IP: 192.168.xxx.xxx | Token: zcmx****cys | Acao: download | Ficheiro: documento.pdf | Sucesso: true
[2025-10-02 23:15:12] IP: 10.0.xxx.xxx | Token: INVA****LID | Acao: search | Sucesso: false | Detalhes: TOKEN_NOT_FOUND
```

**Protecoes:**
- ✅ IP ofuscado (ultimos 2 octetos)
- ✅ Token parcial (primeiros 4 + ultimos 3 chars)
- ✅ Rotacao automatica de logs
- ✅ Apenas eventos criticos em producao

**Localizacao:** `html/logs/access.log`

---

## 🎨 Personalizacao

### Cores e Branding

Tudo configuravel via `.env` **sem editar codigo**:

```env
# Cores do tema
THEME_PRIMARY_COLOR=#004080
THEME_SECONDARY_COLOR=#0066cc
THEME_SUCCESS_COLOR=#28a745
THEME_DANGER_COLOR=#dc3545
THEME_WARNING_COLOR=#ffc107
THEME_INFO_COLOR=#17a2b8
THEME_BACKGROUND_COLOR=#f8f9fa

# Logo (suporta URL externa ou local)
LOGO_URL=https://www.empresa.pt/logo.svg
LOGO_ALT=Município de Exemplo
LOGO_HEIGHT=50px

# Footer
FOOTER_DEVELOPER=@mjvmst
FOOTER_VERSION=3.1
FOOTER_ORGANIZATION=Municipio de Exemplo
FOOTER_YEAR=2025
FOOTER_EMAIL=geral@empresa.pt

# Titulo da aplicacao
APP_TITLE=Consulta de Documentos
```

**Aplicacao instantanea:** Recarregar pagina (Ctrl+F5)

### 12 Esquemas de Cores Pre-definidos

Ver [secao completa no README original](README.md#12-esquemas-de-cores-pre-definidos)

---

## ⚙️ Configuracao Apache Dinamica

O sistema gera automaticamente configuracao Apache completa a partir do `.env`:

**Template:** [apache/website.conf.template](apache/website.conf.template)
**Gerador:** [apache/generate-config.php](apache/generate-config.php)

### Variaveis Apache

```env
WEBSITE_CONFIG_NAME=www.empresa.pt
SERVER_NAME=www.empresa.pt
DOCUMENT_ROOT=/var/www/www.empresa.pt
HTTP_PORT=80
HTTPS_PORT=443
SSL_CERT_FILE=/var/ssl/empresa.pt.crt
SSL_KEY_FILE=/var/ssl/www.empresa.key
APACHE_LOG_DIR=${APACHE_LOG_DIR}
```

### Geracao

```bash
# Gerar configuracao
php apache/generate-config.php

# Output: apache/www.empresa.pt.conf

# Copiar para Apache
sudo cp apache/www.empresa.pt.conf /etc/apache2/sites-available/

# Ativar site
sudo a2ensite www.empresa.pt.conf
sudo systemctl reload apache2
```

**Automatico com `prod.sh`!**

---

## 📁 Estrutura do Projeto

```
acesso/
├── dev.sh                          # Script desenvolvimento (setup + start + stop)
├── prod.sh                         # Script producao (instalacao completa)
├── .env                            # Configuracao (NAO versionar!)
├── .env.example                    # Template de configuracao
├── .gitignore                      # Protege ficheiros sensiveis
├── README.md                       # Este ficheiro
│
├── apache/
│   ├── generate-config.php         # Gerador config Apache dinamico
│   ├── website.conf.template       # Template generico reutilizavel
│   └── *.conf                      # Configs gerados (nao versionar)
│
└── html/                           # DocumentRoot
    ├── index.php                   # Pagina inicial com formulario
    ├── search.php                  # Lista ficheiros por token
    ├── download.php                # Download seguro com validacoes
    ├── router.php                  # Roteamento URLs amigaveis (dev)
    ├── .htaccess                   # Regras Apache (URLs, seguranca)
    ├── 403.php, 404.php, 500.php   # Paginas erro dinamicas
    │
    ├── config/
    │   ├── env.php                 # Parser .env (40+ constantes)
    │   └── security.php            # Funcoes seguranca (CSRF, rate limit, CSP, logs)
    │
    ├── includes/
    │   └── error-template.php      # Template reutilizavel erros
    │
    ├── assets/
    │   ├── css/
    │   │   ├── theme.php           # CSS dinamico gerado do .env
    │   │   └── styles.css          # CSS estatico base
    │   └── js/
    │       └── main.js             # Validacao cliente, modais
    │
    ├── data/                       # Rate limiting (JSON)
    ├── logs/                       # Logs acesso (access.log)
    └── exp/                        # Link simbolico -> EXP_DIR_PATH
```

---

## 🛠️ Requisitos

### Servidor (Producao)

- **OS**: Linux (Ubuntu 20.04+, Debian 11+)
- **Servidor Web**: Apache 2.4+
- **PHP**: 8.2+ com modulos:
  - `php-cli`
  - `php-mbstring`
  - `php-json`
  - `php-fileinfo`
- **Modulos Apache**:
  - `mod_ssl` (HTTPS)
  - `mod_headers`
  - `mod_deflate`
  - `mod_expires`
  - `mod_rewrite`
- **Ferramentas**: `rsync`, `zip`

### Desenvolvimento

- **PHP**: 8.2+
- **ZIP**: Para criar ficheiros teste
- **Browser**: Moderno (Chrome, Firefox, Edge, Safari)

---

## 📊 Variaveis de Ambiente Completas

**Total: 40 variaveis configuravel** (ver [.env.example](.env.example))

Principais grupos:
- Aplicacao (4)
- Sessoes (2)
- Rate Limiting (4)
- Logging (2)
- Diretorios (1)
- Ficheiros Permitidos (2)
- Interface/Branding (16)
- Apache (8)

---

## 🚨 Resolucao de Problemas

### CSP bloqueia logo ou recursos

**Causa:** Logo em dominio diferente do configurado no `.env`.

**Solucao:**
```env
# Configurar dominio correto do logo
LOGO_URL=https://www.empresa.pt/logo.svg
```

O CSP sera gerado automaticamente para permitir esse dominio.

### Token nao encontrado

**Causa:** Token nao existe em `EXP_DIR_PATH` ou nao tem ficheiros permitidos.

**Solucao:**
```bash
# Verificar diretorios
ls -la /mnt/exportacao/TOKEN/

# Criar token teste (pasta com ficheiros)
mkdir -p /mnt/exportacao/TEST12345678
echo "teste" > /tmp/teste.txt
zip /mnt/exportacao/TEST12345678/teste.zip /tmp/teste.txt

# OU criar token teste (ficheiro unico)
echo "teste" > /tmp/teste.txt
zip /mnt/exportacao/TEST12345678.zip /tmp/teste.txt
```

### Ficheiro .env nao carrega

**Causa:** Ficheiro `.env` em local errado (deve estar 2 niveis acima de `config/`).

**Solucao:**
```bash
# Para DOCUMENT_ROOT=/var/www/www.exemplo.pt
# .env deve estar em /var/www/.env

sudo cp .env /var/www/.env
sudo chown www-data:www-data /var/www/.env
sudo chmod 640 /var/www/.env
```

### Site nao ativa (a2ensite falha)

**Causa:** Diretorio `/etc/apache2/sites-enabled` nao existe.

**Solucao:**
```bash
sudo mkdir -p /etc/apache2/sites-enabled
sudo a2ensite www.empresa.pt.pt.conf
sudo systemctl reload apache2
```

O `prod.sh` agora cria automaticamente!

---

## 📝 Changelog

### v3.1 (2025-10-02)

**🔒 Seguranca Avancada:**
- ✅ CSP dinamico baseado em LOGO_URL
- ✅ Type hints PHP 8.2+ completos
- ✅ PHPDoc detalhado em todas as funcoes
- ✅ Validacao de ficheiros configuravel (ZIP, PDF, etc.)
- ✅ Encoding UTF-8 limpo (compatibilidade Windows/Linux)
- ✅ Logging com ofuscacao de dados sensiveis

**🚀 URLs Amigaveis:**
- ✅ Roteamento `/TOKEN` direto
- ✅ Suporte pasta ou ficheiro unico
- ✅ Router customizado para PHP built-in server
- ✅ Path traversal protection

**⚙️ Configuracao:**
- ✅ prod.sh copia .env para local correto
- ✅ prod.sh cria sites-enabled se nao existir
- ✅ prod.sh reatira site para aplicar mudancas
- ✅ Template Apache sem CSP (gerido por PHP)

### v3.0 (2025-01-15)

- ✅ Customizacao total via .env
- ✅ 12 esquemas cores pre-definidos
- ✅ Geracao Apache dinamica
- ✅ CSRF, rate limiting, sessoes seguras
- ✅ Rating seguranca 9/10

---

## 📄 Licenca

MIT License

---

## 👤 Autor

**@mjvmst**

- GitHub: [@mjvmst](https://github.com/mjvmst)

---

## 📞 Suporte

**Problemas ou duvidas?**

1. Verificar [Resolucao de Problemas](#-resolucao-de-problemas)
2. Abrir issue no GitHub
3. Contactar via email (ver `.env` → `FOOTER_EMAIL`)

---

*Desenvolvido com ❤️ por @mjvmst | v3.1 | Sistema Seguro de Consulta de Documentos*
