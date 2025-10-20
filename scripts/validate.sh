#!/bin/bash
# ============================================================
# Script de validación - Progressive Dialer
# Verifica que todos los componentes estén correctamente configurados
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ERRORS=0

echo "=== Validación de Progressive Dialer ==="
echo ""

# 1. Verificar archivos críticos
echo -e "${YELLOW}Verificando archivos...${NC}"
FILES=(
    "/etc/progressive_dialer/credentials"
    "/opt/progressive_dialer/bin/dialer_engine.py"
    "/opt/progressive_dialer/web/db.php"
    "/opt/progressive_dialer/web/index.php"
    "/opt/progressive_dialer/docs/setup_database.sql"
    "/var/log/progressive_dialer"
)

for file in "${FILES[@]}"; do
    if [ -e "$file" ]; then
        echo -e "${GREEN}✓${NC} Existe: $file"
    else
        echo -e "${RED}✗${NC} Falta: $file"
        ((ERRORS++))
    fi
done

# 2. Verificar permisos del archivo de credenciales
echo ""
echo -e "${YELLOW}Verificando permisos de credenciales...${NC}"
if [ -f "/etc/progressive_dialer/credentials" ]; then
    PERMS=$(stat -c %a /etc/progressive_dialer/credentials 2>/dev/null || stat -f %Lp /etc/progressive_dialer/credentials)
    if [ "$PERMS" = "600" ] || [ "$PERMS" = "400" ]; then
        echo -e "${GREEN}✓${NC} Permisos seguros ($PERMS)"
    else
        echo -e "${RED}✗${NC} Permisos inseguros ($PERMS). Recomendado: 600"
        ((ERRORS++))
    fi
fi

# 3. Verificar servicios
echo ""
echo -e "${YELLOW}Verificando servicios...${NC}"
SERVICES=("mariadb" "httpd" "progressive-dialer")
for service in "${SERVICES[@]}"; do
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} Servicio activo: $service"
    else
        # Intentar con apache2 si httpd falla
        if [ "$service" = "httpd" ] && systemctl is-active --quiet "apache2" 2>/dev/null; then
            echo -e "${GREEN}✓${NC} Servicio activo: apache2"
        else
            echo -e "${RED}✗${NC} Servicio inactivo: $service"
            ((ERRORS++))
        fi
    fi
done

# 4. Verificar conexión a base de datos
echo ""
echo -e "${YELLOW}Verificando conexión a base de datos...${NC}"
if mysql -u root -e "USE progressive_dialer;" 2>/dev/null; then
    echo -e "${GREEN}✓${NC} Conexión a BD exitosa"
    
    # Verificar tablas
    TABLES=$(mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'progressive_dialer';")
    if [ "$TABLES" -ge 3 ]; then
        echo -e "${GREEN}✓${NC} Tablas encontradas: $TABLES"
    else
        echo -e "${RED}✗${NC} Faltan tablas en la BD. Ejecuta setup_database.sql"
        ((ERRORS++))
    fi
else
    echo -e "${RED}✗${NC} No se pudo conectar a la base de datos"
    ((ERRORS++))
fi

# 5. Verificar dependencias de Python
echo ""
echo -e "${YELLOW}Verificando dependencias de Python...${NC}"
if python3 -c "import mysql.connector" 2>/dev/null; then
    echo -e "${GREEN}✓${NC} mysql-connector-python instalado"
else
    echo -e "${RED}✗${NC} Falta mysql-connector-python"
    echo "   Instalar con: pip3 install mysql-connector-python"
    ((ERRORS++))
fi

# 6. Verificar logs
echo ""
echo -e "${YELLOW}Verificando logs...${NC}"
if [ -d "/var/log/progressive_dialer" ]; then
    LOG_COUNT=$(find /var/log/progressive_dialer -name "*.log" 2>/dev/null | wc -l)
    echo -e "${GREEN}✓${NC} Directorio de logs existe ($LOG_COUNT archivos)"
    
    if [ -f "/var/log/progressive_dialer/dialer.log" ]; then
        LINES=$(wc -l < /var/log/progressive_dialer/dialer.log)
        echo "   Líneas en dialer.log: $LINES"
    fi
else
    echo -e "${YELLOW}⚠${NC}  Directorio de logs no existe"
fi

# 7. Verificar acceso web
echo ""
echo -e "${YELLOW}Verificando acceso web...${NC}"
if curl -s -o /dev/null -w "%{http_code}" http://localhost/progressive_dialer/index.php | grep -q "200\|302"; then
    echo -e "${GREEN}✓${NC} Interfaz web accesible"
else
    echo -e "${RED}✗${NC} No se puede acceder a la interfaz web"
    ((ERRORS++))
fi

# 8. Verificar configuración de AMI
echo ""
echo -e "${YELLOW}Verificando configuración de Asterisk AMI...${NC}"
if [ -f "/etc/asterisk/manager.conf" ]; then
    if grep -q "enabled = yes" /etc/asterisk/manager.conf; then
        echo -e "${GREEN}✓${NC} AMI habilitado en Asterisk"
    else
        echo -e "${RED}✗${NC} AMI no está habilitado en manager.conf"
        ((ERRORS++))
    fi
else
    echo -e "${YELLOW}⚠${NC}  No se encontró /etc/asterisk/manager.conf"
fi

# Resumen final
echo ""
echo "=========================================="
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Validación exitosa. Sistema listo.${NC}"
    exit 0
else
    echo -e "${RED}✗ Se encontraron $ERRORS error(es).${NC}"
    echo "Por favor, revisa los mensajes anteriores y corrige los problemas."
    exit 1
fi
