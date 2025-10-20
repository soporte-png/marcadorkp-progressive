#!/bin/bash#!/bin/bash

# ============================================================# ============================================================

# Script de instalación - Progressive Dialer# Script de instalación - Progressive Dialer

# Solo configura el proyecto (NO instala paquetes del sistema)# Solo configura el proyecto (NO instala paquetes del sistema)

# Prerequisitos: Python3, PHP, MariaDB, Apache ya instalados# Prerequisitos: Python3, PHP, MariaDB, Apache ya instalados

# ============================================================# ============================================================



set -eset -e



echo "=== Instalador Progressive Dialer ==="echo "=== Instalador Progressive Dialer ==="

echo ""echo ""



# Colores para output# Colores para output

RED='\033[0;31m'RED='\033[0;31m'

GREEN='\033[0;32m'GREEN='\033[0;32m'

YELLOW='\033[1;33m'YELLOW='\033[1;33m'

NC='\033[0m' # No ColorNC='\033[0m' # No Color



# Verificar si se ejecuta como root# Verificar si se ejecuta como root

if [ "$EUID" -ne 0 ]; then if [ "$EUID" -ne 0 ]; then 

    echo -e "${RED}Error: Este script debe ejecutarse como root${NC}"    echo -e "${RED}Error: Este script debe ejecutarse como root${NC}"

    exit 1    exit 1

fifi



echo -e "${GREEN}1. Verificando dependencias...${NC}"echo -e "${GREEN}1. Verificando dependencias...${NC}"

MISSING_DEPS=0MISSING_DEPS=0



if ! command -v python3 &> /dev/null; thenif ! command -v python3 &> /dev/null; then

    echo -e "${RED}✗ Python3 no está instalado${NC}"    echo -e "${RED}✗ Python3 no está instalado${NC}"

    MISSING_DEPS=1    MISSING_DEPS=1

elsefi

    echo -e "${GREEN}✓ Python3 instalado${NC}"

fiif ! command -v php &> /dev/null; then

    echo -e "${RED}✗ PHP no está instalado${NC}"

if ! command -v php &> /dev/null; then    MISSING_DEPS=1

    echo -e "${RED}✗ PHP no está instalado${NC}"fi

    MISSING_DEPS=1

elseif ! systemctl is-active --quiet mariadb && ! systemctl is-active --quiet mysql; then

    echo -e "${GREEN}✓ PHP instalado${NC}"    echo -e "${RED}✗ MariaDB/MySQL no está ejecutándose${NC}"

fi    MISSING_DEPS=1

fi

if ! systemctl is-active --quiet mariadb && ! systemctl is-active --quiet mysql; then

    echo -e "${RED}✗ MariaDB/MySQL no está ejecutándose${NC}"if ! systemctl is-active --quiet httpd && ! systemctl is-active --quiet apache2; then

    MISSING_DEPS=1    echo -e "${RED}✗ Apache no está ejecutándose${NC}"

else    MISSING_DEPS=1

    echo -e "${GREEN}✓ MariaDB/MySQL ejecutándose${NC}"fi

fi

if [ $MISSING_DEPS -eq 1 ]; then

if ! systemctl is-active --quiet httpd && ! systemctl is-active --quiet apache2; then    echo -e "${RED}Error: Instala las dependencias faltantes antes de continuar${NC}"

    echo -e "${RED}✗ Apache no está ejecutándose${NC}"    exit 1

    MISSING_DEPS=1fi

else

    echo -e "${GREEN}✓ Apache ejecutándose${NC}"echo -e "${GREEN}✓ Todas las dependencias están instaladas${NC}"

fi

echo -e "${GREEN}2. Instalando dependencias de Python...${NC}"

if [ $MISSING_DEPS -eq 1 ]; thenpip3 install mysql-connector-python --quiet

    echo -e "${RED}Error: Instala las dependencias faltantes antes de continuar${NC}"

    exit 1echo -e "${GREEN}✓ Todas las dependencias están instaladas${NC}"

fi

echo -e "${GREEN}2. Instalando dependencias de Python...${NC}"

echo ""pip3 install mysql-connector-python --quiet

echo -e "${GREEN}2. Instalando dependencias de Python...${NC}"

pip3 install mysql-connector-python --quietecho -e "${GREEN}3. Creando estructura de directorios...${NC}"

echo -e "${GREEN}✓ mysql-connector-python instalado${NC}"mkdir -p /var/www/html/progressive_dialer

mkdir -p /etc/progressive_dialer

echo ""mkdir -p /var/log/progressive_dialer

echo -e "${GREEN}3. Creando estructura de directorios...${NC}"mkdir -p /opt/progressive_dialer/{bin,config,web,docs}

mkdir -p /var/www/html/progressive_dialer

mkdir -p /etc/progressive_dialerecho -e "${GREEN}4. Copiando archivos...${NC}"

mkdir -p /var/log/progressive_dialerSCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

mkdir -p /opt/progressive_dialer/{bin,config,web,docs}

echo -e "${GREEN}✓ Directorios creados${NC}"# Copiar motor Python

cp "$SCRIPT_DIR/bin/dialer_engine.py" /opt/progressive_dialer/bin/

echo ""

echo -e "${GREEN}4. Copiando archivos...${NC}"# Copiar archivos web

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"cp "$SCRIPT_DIR"/web/*.php /opt/progressive_dialer/web/

cp "$SCRIPT_DIR"/web/*.js /opt/progressive_dialer/web/

# Copiar motor Pythoncp "$SCRIPT_DIR"/web/*.css /opt/progressive_dialer/web/

cp "$SCRIPT_DIR/bin/dialer_engine.py" /opt/progressive_dialer/bin/

echo "  ✓ Motor Python copiado"# Copiar configuración

cp "$SCRIPT_DIR/config/credentials.example" /opt/progressive_dialer/config/

# Copiar archivos web

cp "$SCRIPT_DIR"/web/*.php /opt/progressive_dialer/web/# Copiar documentación

cp "$SCRIPT_DIR"/web/*.js /opt/progressive_dialer/web/cp "$SCRIPT_DIR"/docs/*.md /opt/progressive_dialer/docs/ 2>/dev/null || true

cp "$SCRIPT_DIR"/web/*.css /opt/progressive_dialer/web/cp "$SCRIPT_DIR"/docs/*.sql /opt/progressive_dialer/docs/ 2>/dev/null || true

echo "  ✓ Archivos web copiados"

# Crear symlink para la web

# Copiar configuraciónln -sf /opt/progressive_dialer/web /var/www/html/progressive_dialer

cp "$SCRIPT_DIR/config/credentials.example" /opt/progressive_dialer/config/

echo "  ✓ Configuración copiada"echo -e "${GREEN}5. Configurando credenciales...${NC}"

if [ ! -f /etc/progressive_dialer/credentials ]; then

# Copiar documentación    cp /opt/progressive_dialer/config/credentials.example /etc/progressive_dialer/credentials

cp "$SCRIPT_DIR"/docs/*.md /opt/progressive_dialer/docs/ 2>/dev/null || true    

cp "$SCRIPT_DIR"/docs/*.sql /opt/progressive_dialer/docs/ 2>/dev/null || true    echo -e "${YELLOW}Por favor, edita el archivo de credenciales:${NC}"

echo "  ✓ Documentación copiada"    echo "nano /etc/progressive_dialer/credentials"

    echo ""

# Crear symlink para la web    read -p "Presiona Enter cuando hayas configurado las credenciales..."

rm -f /var/www/html/progressive_dialerfi

ln -sf /opt/progressive_dialer/web /var/www/html/progressive_dialer

echo "  ✓ Symlink web creado"echo -e "${GREEN}7. Configurando permisos...${NC}"

chmod 600 /etc/progressive_dialer/credentials

echo ""chown -R apache:apache /var/www/html/progressive_dialer 2>/dev/null || chown -R www-data:www-data /var/www/html/progressive_dialer

echo -e "${GREEN}5. Configurando credenciales...${NC}"chown -R root:root /opt/progressive_dialer

if [ ! -f /etc/progressive_dialer/credentials ]; thenchmod 755 /opt/progressive_dialer/dialer_engine.py

    cp /opt/progressive_dialer/config/credentials.example /etc/progressive_dialer/credentialschmod -R 755 /var/log/progressive_dialer

    echo -e "${GREEN}✓ Archivo de credenciales creado${NC}"

    echo -e "${YELLOW}⚠ IMPORTANTE: Edita las credenciales antes de iniciar:${NC}"echo -e "${GREEN}8. Configurando base de datos...${NC}"

    echo -e "${YELLOW}  nano /etc/progressive_dialer/credentials${NC}"echo "Ejecutando setup_database.sql..."

elsemysql -u root -p < /opt/progressive_dialer/docs/setup_database.sql

    echo -e "${GREEN}✓ Archivo de credenciales ya existe${NC}"

fiecho -e "${GREEN}9. Creando servicio systemd...${NC}"

cat > /etc/systemd/system/progressive-dialer.service <<EOF

echo ""[Unit]

echo -e "${GREEN}6. Configurando permisos...${NC}"Description=Progressive Dialer Engine

chmod 600 /etc/progressive_dialer/credentialsAfter=network.target mariadb.service

chown -R apache:apache /var/www/html/progressive_dialer 2>/dev/null || chown -R www-data:www-data /var/www/html/progressive_dialer

chown -R root:root /opt/progressive_dialer[Service]

chmod 755 /opt/progressive_dialer/bin/dialer_engine.pyType=simple

chmod -R 755 /var/log/progressive_dialerUser=root

echo -e "${GREEN}✓ Permisos configurados${NC}"WorkingDirectory=/opt/progressive_dialer/bin

ExecStart=/usr/bin/python3 /opt/progressive_dialer/bin/dialer_engine.py

echo ""Restart=always

echo -e "${GREEN}7. Configurando base de datos...${NC}"RestartSec=10

echo -e "${YELLOW}Se solicitará la contraseña de root de MySQL...${NC}"

if mysql -u root -p < /opt/progressive_dialer/docs/setup_database.sql; then[Install]

    echo -e "${GREEN}✓ Base de datos configurada${NC}"WantedBy=multi-user.target

elseEOF

    echo -e "${RED}✗ Error configurando la base de datos${NC}"

    echo -e "${YELLOW}Puedes configurarla manualmente:${NC}"systemctl daemon-reload

    echo "  mysql -u root -p < /opt/progressive_dialer/docs/setup_database.sql"systemctl enable progressive-dialer

fi

echo -e "${GREEN}10. Configurando Apache/httpd...${NC}"

echo ""systemctl enable httpd 2>/dev/null || systemctl enable apache2

echo -e "${GREEN}8. Creando servicio systemd...${NC}"systemctl start httpd 2>/dev/null || systemctl start apache2

cat > /etc/systemd/system/progressive-dialer.service <<EOF

[Unit]echo ""

Description=Progressive Dialer Engineecho -e "${GREEN}=========================================="

After=network.target mariadb.serviceecho "✓ Instalación completada"

echo "==========================================${NC}"

[Service]echo ""

Type=simpleecho "Próximos pasos:"

User=rootecho "1. Verifica las credenciales: nano /etc/progressive_dialer/credentials"

WorkingDirectory=/opt/progressive_dialer/binecho "2. Inicia el motor: systemctl start progressive-dialer"

ExecStart=/usr/bin/python3 /opt/progressive_dialer/bin/dialer_engine.pyecho "3. Verifica el estado: systemctl status progressive-dialer"

Restart=alwaysecho "4. Accede a la interfaz web: http://$(hostname -I | awk '{print $1}')/progressive_dialer"

RestartSec=10echo ""

echo "Logs del motor: /var/log/progressive_dialer/dialer.log"

[Install]echo "Ver logs en tiempo real: tail -f /var/log/progressive_dialer/dialer.log"

WantedBy=multi-user.targetecho ""

EOF

systemctl daemon-reload
systemctl enable progressive-dialer
echo -e "${GREEN}✓ Servicio systemd configurado${NC}"

echo ""
echo -e "${GREEN}=========================================="
echo "✓ Instalación completada exitosamente"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Próximos pasos:${NC}"
echo ""
echo "1. ${YELLOW}Editar credenciales:${NC}"
echo "   nano /etc/progressive_dialer/credentials"
echo ""
echo "2. ${YELLOW}Iniciar el motor:${NC}"
echo "   systemctl start progressive-dialer"
echo ""
echo "3. ${YELLOW}Verificar estado:${NC}"
echo "   systemctl status progressive-dialer"
echo ""
echo "4. ${YELLOW}Ver logs:${NC}"
echo "   tail -f /var/log/progressive_dialer/dialer.log"
echo ""
echo "5. ${YELLOW}Acceder a la interfaz web:${NC}"
echo "   http://$(hostname -I | awk '{print $1}')/progressive_dialer"
echo ""
