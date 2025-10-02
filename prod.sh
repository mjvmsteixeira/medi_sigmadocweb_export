#!/bin/bash
################################################################################
# Script de Instalacao - Producao
# Sistema de Consulta de Processos
# Uso: sudo ./prod.sh
# Versao 3.0
################################################################################

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuracoes padrao (serao lidas do .env)
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_USER="www-data"
WEB_GROUP="www-data"

# Banner
echo -e "${BLUE}==================================================================${NC}"
echo -e "${BLUE}  Instalacao em Producao${NC}"
echo -e "${BLUE}  Sistema de Consulta de Processos - v3.0${NC}"
echo -e "${BLUE}==================================================================${NC}"
echo ""

# Verificar se e root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}X Este script deve ser executado como root${NC}"
    echo "  Uso: sudo ./prod.sh"
    exit 1
fi

# ==================================================================
# 1. LER CONFIGURACAO DO .ENV
# ==================================================================

echo -e "${BLUE}[1/10] Lendo configuracao do .env...${NC}"

if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo -e "${RED}X Ficheiro .env nao encontrado!${NC}"
    echo "  Crie o ficheiro .env com as configuracoes de producao"
    echo "  Use .env.example como referencia"
    exit 1
fi

# Ler variaveis do .env
SERVER_NAME=$(grep "^SERVER_NAME=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
DOCUMENT_ROOT=$(grep "^DOCUMENT_ROOT=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
EXP_DIR_PATH=$(grep "^EXP_DIR_PATH=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
WEBSITE_CONFIG_NAME=$(grep "^WEBSITE_CONFIG_NAME=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
SSL_CERT_FILE=$(grep "^SSL_CERT_FILE=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
SSL_KEY_FILE=$(grep "^SSL_KEY_FILE=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
HTTPS_PORT=$(grep "^HTTPS_PORT=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")

# Validar variaveis obrigatorias
if [ -z "$SERVER_NAME" ] || [ -z "$DOCUMENT_ROOT" ] || [ -z "$EXP_DIR_PATH" ]; then
    echo -e "${RED}X Configuracao incompleta no .env${NC}"
    echo "  Variaveis obrigatorias:"
    echo "    - SERVER_NAME (dominio do servidor)"
    echo "    - DOCUMENT_ROOT (caminho de instalacao)"
    echo "    - EXP_DIR_PATH (diretorio de documentos)"
    exit 1
fi

# Usar SERVER_NAME como nome de config se WEBSITE_CONFIG_NAME nao estiver definido
if [ -z "$WEBSITE_CONFIG_NAME" ]; then
    WEBSITE_CONFIG_NAME="$SERVER_NAME"
fi

APACHE_CONFIG="/etc/apache2/sites-available/${WEBSITE_CONFIG_NAME}.conf"

echo -e "${GREEN}  OK Configuracao carregada${NC}"
echo -e "${BLUE}    -> Servidor: $SERVER_NAME${NC}"
echo -e "${BLUE}    -> Instalar em: $DOCUMENT_ROOT${NC}"
echo -e "${BLUE}    -> Documentos: $EXP_DIR_PATH${NC}"
if [ -n "$HTTPS_PORT" ]; then
    echo -e "${BLUE}    -> SSL: Ativado (porta $HTTPS_PORT)${NC}"
else
    echo -e "${YELLOW}    -> SSL: Desativado${NC}"
fi

# ==================================================================
# 2. CONFIRMACAO
# ==================================================================

echo ""
read -p "Continuar com a instalacao? (s/N): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[sS]$ ]]; then
    echo -e "${YELLOW}Instalacao cancelada.${NC}"
    exit 0
fi

# ==================================================================
# 3. VERIFICAR DEPENDENCIAS
# ==================================================================

echo ""
echo -e "${BLUE}[2/10] Verificando dependencias...${NC}"

# Verificar Apache
if ! command -v apache2 &> /dev/null; then
    echo -e "${RED}X Apache nao instalado${NC}"
    echo "  Instale: sudo apt install apache2"
    exit 1
fi
echo -e "${GREEN}  OK Apache instalado${NC}"

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}X PHP nao instalado${NC}"
    echo "  Instale: sudo apt install php php-cli php-mbstring"
    exit 1
fi
PHP_VERSION=$(php -v | head -n 1 | awk '{print $2}' | cut -d. -f1,2)
echo -e "${GREEN}  OK PHP $PHP_VERSION instalado${NC}"

# Verificar modulos Apache
REQUIRED_MODULES=("headers" "deflate" "expires" "rewrite")
if [ -n "$HTTPS_PORT" ]; then
    REQUIRED_MODULES+=("ssl")
fi

for mod in "${REQUIRED_MODULES[@]}"; do
    if ! apache2ctl -M 2>/dev/null | grep -q "${mod}_module"; then
        echo -e "${YELLOW}  ⚠ Modulo $mod nao ativo - ativando...${NC}"
        a2enmod "$mod" > /dev/null 2>&1
    else
        echo -e "${GREEN}  OK Modulo $mod ativo${NC}"
    fi
done

# ==================================================================
# 4. VERIFICAR DIRETORIO DE DOCUMENTOS
# ==================================================================

echo ""
echo -e "${BLUE}[3/10] Verificando diretorio de documentos...${NC}"

if [ ! -d "$EXP_DIR_PATH" ]; then
    echo -e "${YELLOW}  ⚠ Diretorio nao existe, criando...${NC}"
    mkdir -p "$EXP_DIR_PATH"
fi
echo -e "${GREEN}  OK Diretorio de documentos: $EXP_DIR_PATH${NC}"

# ==================================================================
# 5. VERIFICAR CERTIFICADOS SSL
# ==================================================================

if [ -n "$HTTPS_PORT" ]; then
    echo ""
    echo -e "${BLUE}[4/10] Verificando certificados SSL...${NC}"

    if [ ! -f "$SSL_CERT_FILE" ]; then
        echo -e "${RED}X Certificado SSL nao encontrado: $SSL_CERT_FILE${NC}"
        exit 1
    fi
    echo -e "${GREEN}  OK Certificado: $SSL_CERT_FILE${NC}"

    if [ ! -f "$SSL_KEY_FILE" ]; then
        echo -e "${RED}X Chave SSL nao encontrada: $SSL_KEY_FILE${NC}"
        exit 1
    fi
    echo -e "${GREEN}  OK Chave: $SSL_KEY_FILE${NC}"
else
    echo ""
    echo -e "${BLUE}[4/10] SSL desativado (modo HTTP apenas)${NC}"
fi

# ==================================================================
# 6. CRIAR DIRETORIO DE INSTALACAO
# ==================================================================

echo ""
echo -e "${BLUE}[5/10] Criando diretorio de instalacao...${NC}"

if [ ! -d "$DOCUMENT_ROOT" ]; then
    mkdir -p "$DOCUMENT_ROOT"
    echo -e "${GREEN}  OK Diretorio criado: $DOCUMENT_ROOT${NC}"
else
    echo -e "${YELLOW}  ⚠ Diretorio ja existe${NC}"
fi

# ==================================================================
# 7. COPIAR FICHEIROS
# ==================================================================

echo ""
echo -e "${BLUE}[6/10] Copiando ficheiros...${NC}"

rsync -av --exclude='exp' html/ "$DOCUMENT_ROOT/" > /dev/null
echo -e "${GREEN}  OK Ficheiros copiados${NC}"

# Criar link simbolico para documentos
if [ -L "$DOCUMENT_ROOT/exp" ]; then
    rm "$DOCUMENT_ROOT/exp"
fi
ln -sf "$EXP_DIR_PATH" "$DOCUMENT_ROOT/exp"
echo -e "${GREEN}  OK Link simbolico: $DOCUMENT_ROOT/exp -> $EXP_DIR_PATH${NC}"

# Criar diretorios necessarios
mkdir -p "$DOCUMENT_ROOT/data"
mkdir -p "$DOCUMENT_ROOT/logs"
mkdir -p "$DOCUMENT_ROOT/config"
echo -e "${GREEN}  OK Diretorios criados${NC}"

# ==================================================================
# 8. COPIAR E CONFIGURAR .ENV
# ==================================================================

echo ""
echo -e "${BLUE}[7/10] Configurando .env...${NC}"

# Determinar diretorio pai do DOCUMENT_ROOT para o .env
# env.php procura em __DIR__ . '/../../.env' (2 niveis acima de config/)
# Se DOCUMENT_ROOT=/var/www/acesso.cm-caminha.pt entao .env vai para /var/www/
ENV_DIR="$(dirname "$DOCUMENT_ROOT")"

if [ ! -f "$ENV_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env" "$ENV_DIR/.env"

    # Garantir configuracoes de producao
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" "$ENV_DIR/.env"
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" "$ENV_DIR/.env"
    sed -i "s|^LOG_LEVEL=.*|LOG_LEVEL=info|" "$ENV_DIR/.env"

    chmod 640 "$ENV_DIR/.env"
    chown $WEB_USER:$WEB_GROUP "$ENV_DIR/.env"

    echo -e "${GREEN}  OK Ficheiro .env configurado em: $ENV_DIR/.env${NC}"
else
    echo -e "${YELLOW}  Ficheiro .env ja existe em: $ENV_DIR/.env - mantido${NC}"
    echo -e "${YELLOW}  Para atualizar: sudo cp .env $ENV_DIR/.env${NC}"
fi

# ==================================================================
# 9. GERAR CONFIGURACAO APACHE
# ==================================================================

echo ""
echo -e "${BLUE}[8/10] Gerando configuracao Apache...${NC}"

if [ -f "$PROJECT_DIR/apache/generate-config.php" ]; then
    # Gerar a partir do .env de producao
    cd "$PROJECT_DIR"
    php apache/generate-config.php

    GENERATED_CONF=$(ls -t apache/${WEBSITE_CONFIG_NAME}.conf 2>/dev/null | head -n 1)

    if [ -f "$GENERATED_CONF" ]; then
        echo -e "${GREEN}  OK Configuracao gerada: $(basename $GENERATED_CONF)${NC}"

        # Copiar para Apache
        cp "$GENERATED_CONF" "$APACHE_CONFIG"
        echo -e "${GREEN}  OK Copiada para: $APACHE_CONFIG${NC}"
    else
        echo -e "${RED}  X Erro ao gerar configuracao${NC}"
        exit 1
    fi
else
    echo -e "${RED}  X generate-config.php nao encontrado${NC}"
    exit 1
fi

# ==================================================================
# 10. CONFIGURAR PERMISSOES
# ==================================================================

echo ""
echo -e "${BLUE}[9/10] Configurando permissoes...${NC}"

chown -R $WEB_USER:$WEB_GROUP "$DOCUMENT_ROOT"
echo -e "${GREEN}  OK Owner: $WEB_USER:$WEB_GROUP${NC}"

find "$DOCUMENT_ROOT" -type f -exec chmod 640 {} \;
echo -e "${GREEN}  OK Ficheiros: 640${NC}"

find "$DOCUMENT_ROOT" -type d -exec chmod 750 {} \;
echo -e "${GREEN}  OK Diretorios: 750${NC}"

chmod 700 "$DOCUMENT_ROOT/data"
chmod 700 "$DOCUMENT_ROOT/logs"
echo -e "${GREEN}  OK Permissoes especiais aplicadas${NC}"

# ==================================================================
# 11. ATIVAR SITE NO APACHE
# ==================================================================

echo ""
echo -e "${BLUE}[10/10] Ativando site no Apache...${NC}"

# Garantir que diretorio sites-enabled existe
if [ ! -d "/etc/apache2/sites-enabled" ]; then
    echo -e "${YELLOW}  -> Criando diretorio sites-enabled...${NC}"
    mkdir -p /etc/apache2/sites-enabled
fi

# Ativar site (desativar primeiro se ja existir para forcar atualizacao)
SITE_ENABLED="/etc/apache2/sites-enabled/${WEBSITE_CONFIG_NAME}.conf"
if [ -L "$SITE_ENABLED" ]; then
    echo -e "${YELLOW}  -> Site ja ativado, a reativar para aplicar mudancas...${NC}"
    a2dissite "${WEBSITE_CONFIG_NAME}.conf" > /dev/null 2>&1
fi

a2ensite "${WEBSITE_CONFIG_NAME}.conf" > /dev/null 2>&1
echo -e "${GREEN}  OK Site ativado: ${WEBSITE_CONFIG_NAME}.conf${NC}"

# Testar configuracao
echo ""
echo -e "${BLUE}-> Testando configuracao Apache...${NC}"
if apache2ctl configtest > /dev/null 2>&1; then
    echo -e "${GREEN}  OK Configuracao valida${NC}"
else
    echo -e "${RED}  X Erro na configuracao Apache${NC}"
    apache2ctl configtest
    exit 1
fi

# Recarregar Apache
echo ""
echo -e "${BLUE}-> Recarregando Apache...${NC}"
systemctl reload apache2
echo -e "${GREEN}  OK Apache recarregado${NC}"

# ==================================================================
# CONCLUSAO
# ==================================================================

echo ""
echo -e "${GREEN}+================================================================+${NC}"
echo -e "${GREEN}|  Instalacao concluida com sucesso!                            |${NC}"
echo -e "${GREEN}+================================================================+${NC}"
echo ""
echo -e "${BLUE}Configuracao:${NC}"
echo -e "  * Servidor: ${GREEN}$SERVER_NAME${NC}"
echo -e "  * DocumentRoot: ${GREEN}$DOCUMENT_ROOT${NC}"
echo -e "  * Documentos: ${GREEN}$EXP_DIR_PATH${NC}"
if [ -n "$HTTPS_PORT" ]; then
    echo -e "  * URL: ${GREEN}https://$SERVER_NAME${NC}"
else
    echo -e "  * URL: ${GREEN}http://$SERVER_NAME${NC}"
fi
echo ""
echo -e "${YELLOW}Proximos passos:${NC}"
echo -e "  1. Configurar DNS para apontar para este servidor"
if [ -n "$HTTPS_PORT" ]; then
    echo -e "  2. Aceder a ${BLUE}https://$SERVER_NAME${NC}"
else
    echo -e "  2. Aceder a ${BLUE}http://$SERVER_NAME${NC}"
fi
echo -e "  3. Verificar logs em: ${BLUE}$DOCUMENT_ROOT/logs/${NC}"
echo ""
