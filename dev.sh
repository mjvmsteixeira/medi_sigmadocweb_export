#!/bin/bash
################################################################################
# Script de Desenvolvimento
# Sistema de Consulta de Processos
# Uso: ./dev.sh [setup|start|stop|restart]
# Versao 3.0
################################################################################

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Configuracoes
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEV_PORT=8080
TEST_TOKENS=("TEST12345678" "DEMO87654321" "DEV99999999")
PID_FILE="$PROJECT_DIR/.dev-server.pid"

# ==================================================================
# FUNCOES
# ==================================================================

show_banner() {
    echo -e "${MAGENTA}==================================================================${NC}"
    echo -e "${MAGENTA}  Ambiente de Desenvolvimento${NC}"
    echo -e "${MAGENTA}  Sistema de Consulta de Processos - v3.0${NC}"
    echo -e "${MAGENTA}==================================================================${NC}"
    echo ""
}

show_usage() {
    echo "Uso: ./dev.sh [comando]"
    echo ""
    echo "Comandos:"
    echo "  setup     Configurar ambiente de desenvolvimento (primeira vez)"
    echo "  start     Iniciar servidor de desenvolvimento"
    echo "  stop      Parar servidor"
    echo "  restart   Reiniciar servidor"
    echo "  status    Ver estado do servidor"
    echo ""
    echo "Se nenhum comando for especificado, executa 'start' (ou 'setup' se necessario)"
}

check_dependencies() {
    echo -e "${BLUE}-> Verificando dependencias...${NC}"

    # Verificar PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}X PHP nao instalado${NC}"
        echo "  Instale: sudo apt install php php-cli php-mbstring"
        exit 1
    fi
    PHP_VERSION=$(php -v | head -n 1 | awk '{print $2}' | cut -d. -f1,2)
    echo -e "${GREEN}  OK PHP $PHP_VERSION${NC}"

    # Verificar ZIP
    if ! command -v zip &> /dev/null; then
        echo -e "${RED}X ZIP nao instalado${NC}"
        echo "  Instale: sudo apt install zip"
        exit 1
    fi
    echo -e "${GREEN}  OK ZIP${NC}"
}

setup_env() {
    echo ""
    echo -e "${BLUE}-> Configurando ficheiro .env...${NC}"

    # Verificar se .env existe
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        echo -e "${YELLOW}  ⚠ Ficheiro .env nao encontrado - criando...${NC}"

        if [ -f "$PROJECT_DIR/.env.example" ]; then
            cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
            echo -e "${GREEN}  OK Ficheiro .env criado${NC}"
        else
            echo -e "${RED}  X Ficheiro .env.example nao encontrado${NC}"
            exit 1
        fi
    else
        echo -e "${GREEN}  OK Ficheiro .env encontrado${NC}"
    fi

    # Ler EXP_DIR_PATH do .env
    EXP_DIR_PATH=$(grep "^EXP_DIR_PATH=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")

    if [ -z "$EXP_DIR_PATH" ]; then
        echo -e "${RED}  X EXP_DIR_PATH nao definido no .env${NC}"
        exit 1
    fi

    # Converter caminho do Windows para formato Linux/WSL se necessario
    if [[ "$EXP_DIR_PATH" =~ ^[A-Za-z]: ]]; then
        DRIVE_LETTER=$(echo "$EXP_DIR_PATH" | cut -d ':' -f1 | tr '[:upper:]' '[:lower:]')
        REST_OF_PATH=$(echo "$EXP_DIR_PATH" | cut -d ':' -f2 | sed 's|\\|/|g')
        EXP_DIR_PATH="/mnt/$DRIVE_LETTER$REST_OF_PATH"
        echo -e "${BLUE}  -> Caminho convertido para WSL: $EXP_DIR_PATH${NC}"
    fi

    echo -e "${BLUE}  -> EXP_DIR_PATH: $EXP_DIR_PATH${NC}"
}

create_directories() {
    echo ""
    echo -e "${BLUE}-> Criando diretorios...${NC}"

    # Criar diretorio de exportacoes
    mkdir -p "$EXP_DIR_PATH"
    echo -e "${GREEN}  OK $EXP_DIR_PATH${NC}"

    # Criar link simbolico
    if [ -L "$PROJECT_DIR/html/exp" ]; then
        rm "$PROJECT_DIR/html/exp"
    fi
    ln -sf "$EXP_DIR_PATH" "$PROJECT_DIR/html/exp"
    echo -e "${GREEN}  OK Link simbolico: html/exp -> $EXP_DIR_PATH${NC}"

    # Criar outros diretorios necessarios
    mkdir -p "$PROJECT_DIR/html/data"
    mkdir -p "$PROJECT_DIR/html/logs"
    echo -e "${GREEN}  OK Diretorios auxiliares criados${NC}"
}

create_test_data() {
    echo ""
    echo -e "${BLUE}-> Criando dados de teste...${NC}"

    for token in "${TEST_TOKENS[@]}"; do
        TOKEN_DIR="$EXP_DIR_PATH/$token"

        if [ ! -d "$TOKEN_DIR" ]; then
            mkdir -p "$TOKEN_DIR"

            # Criar ficheiro de teste
            echo "Documento de teste para token $token" > "$TOKEN_DIR/teste.txt"
            echo "Gerado em: $(date)" >> "$TOKEN_DIR/teste.txt"

            # Criar ZIP de teste
            (cd "$TOKEN_DIR" && zip -q teste.zip teste.txt)
            rm "$TOKEN_DIR/teste.txt"

            echo -e "${GREEN}  OK Token criado: $token${NC}"
        else
            echo -e "${YELLOW}  ⚠ Token ja existe: $token${NC}"
        fi
    done
}

update_env_dev() {
    echo ""
    echo -e "${BLUE}-> Configurando .env para desenvolvimento...${NC}"

    # Atualizar variaveis de desenvolvimento
    sed -i.bak "s|^APP_ENV=.*|APP_ENV=development|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^APP_DEBUG=.*|APP_DEBUG=true|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^APP_URL=.*|APP_URL=http://localhost:$DEV_PORT|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^LOG_LEVEL=.*|LOG_LEVEL=debug|" "$PROJECT_DIR/.env"

    # Rate limiting relaxado
    sed -i.bak "s|^RATE_LIMIT_SEARCH_ATTEMPTS=.*|RATE_LIMIT_SEARCH_ATTEMPTS=50|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^RATE_LIMIT_SEARCH_WINDOW=.*|RATE_LIMIT_SEARCH_WINDOW=60|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^RATE_LIMIT_DOWNLOAD_ATTEMPTS=.*|RATE_LIMIT_DOWNLOAD_ATTEMPTS=100|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^RATE_LIMIT_DOWNLOAD_WINDOW=.*|RATE_LIMIT_DOWNLOAD_WINDOW=60|" "$PROJECT_DIR/.env"

    # Configuracao Apache para desenvolvimento
    sed -i.bak "s|^SERVER_NAME=.*|SERVER_NAME=localhost|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^DOCUMENT_ROOT=.*|DOCUMENT_ROOT=$PROJECT_DIR/html|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^HTTP_PORT=.*|HTTP_PORT=$DEV_PORT|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^HTTPS_PORT=.*|HTTPS_PORT=|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^SSL_CERT_FILE=.*|SSL_CERT_FILE=|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^SSL_KEY_FILE=.*|SSL_KEY_FILE=|" "$PROJECT_DIR/.env"
    sed -i.bak "s|^WEBSITE_CONFIG_NAME=.*|WEBSITE_CONFIG_NAME=localhost|" "$PROJECT_DIR/.env"

    # Remover backup
    rm -f "$PROJECT_DIR/.env.bak"

    echo -e "${GREEN}  OK Configuracao atualizada${NC}"
}

set_permissions() {
    echo ""
    echo -e "${BLUE}-> Configurando permissoes...${NC}"

    chmod -R 755 "$PROJECT_DIR/html/data" 2>/dev/null || true
    chmod -R 755 "$PROJECT_DIR/html/logs" 2>/dev/null || true
    chmod -R 755 "$EXP_DIR_PATH" 2>/dev/null || true

    echo -e "${GREEN}  OK Permissoes configuradas${NC}"
}

do_setup() {
    show_banner

    check_dependencies
    setup_env
    create_directories
    create_test_data
    update_env_dev
    set_permissions

    echo ""
    echo -e "${GREEN}+====================================================+${NC}"
    echo -e "${GREEN}|  Configuracao concluida com sucesso!              |${NC}"
    echo -e "${GREEN}+====================================================+${NC}"
    echo ""
    echo -e "${YELLOW}Proximo passo: ${NC}${BLUE}./dev.sh start${NC}"
    echo ""
}

is_running() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p "$PID" > /dev/null 2>&1; then
            return 0
        else
            rm -f "$PID_FILE"
            return 1
        fi
    fi
    return 1
}

do_start() {
    # Verificar se ja esta a correr
    if is_running; then
        echo -e "${YELLOW}⚠ Servidor ja esta a correr (PID: $(cat $PID_FILE))${NC}"
        echo -e "  URL: ${BLUE}http://localhost:$DEV_PORT${NC}"
        exit 0
    fi

    # Verificar se .env existe (setup foi feito)
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        echo -e "${YELLOW}⚠ Ambiente nao configurado. A executar setup...${NC}"
        echo ""
        do_setup
    fi

    # Ler EXP_DIR_PATH
    EXP_DIR_PATH=$(grep "^EXP_DIR_PATH=" "$PROJECT_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")

    show_banner

    echo -e "${GREEN}Servidor de Desenvolvimento - Consulta de Processos${NC}"
    echo ""
    echo -e "  ${BLUE}URL:${NC} http://localhost:$DEV_PORT"
    echo ""
    echo -e "  ${BLUE}Tokens de teste disponiveis:${NC}"
    for token in "${TEST_TOKENS[@]}"; do
        echo -e "    * $token"
    done
    echo ""
    echo -e "  ${BLUE}Diretorio de documentos:${NC} $EXP_DIR_PATH"
    echo ""
    echo -e "  ${YELLOW}Para parar:${NC} Ctrl+C ou ${BLUE}./dev.sh stop${NC}"
    echo ""
    echo -e "${MAGENTA}==================================================================${NC}"
    echo ""

    # Iniciar servidor
    cd "$PROJECT_DIR/html"
    php -S localhost:$DEV_PORT router.php &
    echo $! > "$PID_FILE"

    # Aguardar Ctrl+C
    wait
}

do_stop() {
    if is_running; then
        PID=$(cat "$PID_FILE")
        echo -e "${BLUE}-> A parar servidor (PID: $PID)...${NC}"
        kill "$PID" 2>/dev/null || true
        rm -f "$PID_FILE"
        echo -e "${GREEN}OK Servidor parado${NC}"
    else
        echo -e "${YELLOW}⚠ Servidor nao esta a correr${NC}"
    fi
}

do_restart() {
    echo -e "${BLUE}-> A reiniciar servidor...${NC}"
    do_stop
    sleep 1
    do_start
}

do_status() {
    if is_running; then
        PID=$(cat "$PID_FILE")
        echo -e "${GREEN}OK Servidor esta a correr${NC}"
        echo -e "  PID: $PID"
        echo -e "  URL: ${BLUE}http://localhost:$DEV_PORT${NC}"
    else
        echo -e "${YELLOW}⚠ Servidor nao esta a correr${NC}"
    fi
}

# ==================================================================
# MAIN
# ==================================================================

COMMAND=${1:-auto}

case "$COMMAND" in
    setup)
        do_setup
        ;;
    start)
        do_start
        ;;
    stop)
        do_stop
        ;;
    restart)
        do_restart
        ;;
    status)
        do_status
        ;;
    auto)
        # Auto-detectar: se .env nao existe, fazer setup
        if [ ! -f "$PROJECT_DIR/.env" ]; then
            do_setup
            echo ""
            read -p "Iniciar servidor agora? (S/n): " START_NOW
            if [[ ! "$START_NOW" =~ ^[Nn]$ ]]; then
                echo ""
                do_start
            fi
        else
            do_start
        fi
        ;;
    help|--help|-h)
        show_usage
        ;;
    *)
        echo -e "${RED}X Comando desconhecido: $COMMAND${NC}"
        echo ""
        show_usage
        exit 1
        ;;
esac
