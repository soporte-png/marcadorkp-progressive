#!/bin/bash
# ============================================================
# Comandos de diagnóstico rápido para Rocky Linux 8.10
# ============================================================

echo "=== Diagnóstico Rápido - Progressive Dialer ==="
echo ""

# Información del sistema
echo "1. INFORMACIÓN DEL SISTEMA"
echo "   Versión: $(cat /etc/redhat-release)"
echo "   Kernel: $(uname -r)"
echo "   Hostname: $(hostname)"
echo "   IP: $(hostname -I | awk '{print $1}')"
echo ""

# Estado de servicios
echo "2. ESTADO DE SERVICIOS"
for service in mariadb httpd progressive-dialer; do
    if systemctl is-active --quiet $service; then
        echo "   ✓ $service: ACTIVO"
    else
        echo "   ✗ $service: INACTIVO"
    fi
done
echo ""

# Base de datos
echo "2. BASE DE DATOS"
if systemctl is-active --quiet mariadb; then
    DB_VERSION=$(mysql -V 2>/dev/null | awk '{print $5}' | sed 's/,//')
    echo "   MariaDB versión: $DB_VERSION"
    
    if mysql -u root -e "USE progressive_dialer;" 2>/dev/null; then
        CAMPAIGNS=$(mysql -u root -N -e "SELECT COUNT(*) FROM progressive_dialer.campaigns;" 2>/dev/null)
        LEADS=$(mysql -u root -N -e "SELECT COUNT(*) FROM progressive_dialer.leads;" 2>/dev/null)
        echo "   ✓ Base de datos accesible"
        echo "   Campañas: $CAMPAIGNS"
        echo "   Leads: $LEADS"
    else
        echo "   ✗ No se puede acceder a la base de datos"
        echo "     Verifica credenciales en /etc/progressive_dialer/credentials"
    fi
else
    echo "   MariaDB no está corriendo"
fi
echo ""

# PHP
echo "3. PHP"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | awk '{print $2}')
    echo "   Versión: $PHP_VERSION"
    
    echo "   Módulos críticos:"
    for mod in mysqli pdo_mysql json; do
        if php -m | grep -q "^$mod$"; then
            echo "     ✓ $mod"
        else
            echo "     ✗ $mod (falta)"
        fi
    done
else
    echo "   PHP no instalado"
fi
echo ""

# Apache
echo "4. APACHE/HTTPD"
if systemctl is-active --quiet httpd; then
    HTTPD_VERSION=$(httpd -v | head -n 1 | awk '{print $3}')
    echo "   Versión: $HTTPD_VERSION"
    
    if curl -s -o /dev/null -w "%{http_code}" http://localhost/progressive_dialer/index.php | grep -q "200"; then
        echo "   ✓ Interfaz web accesible"
    else
        echo "   ⚠ Interfaz web no responde correctamente"
    fi
else
    echo "   httpd no está corriendo"
fi
echo ""

# Motor del dialer
echo "5. MOTOR PROGRESSIVE DIALER"
if systemctl is-active --quiet progressive-dialer; then
    echo "   Estado: ACTIVO"
    UPTIME=$(systemctl show -p ActiveEnterTimestamp progressive-dialer | cut -d'=' -f2)
    echo "   Iniciado: $UPTIME"
    
    if [ -f /var/log/progressive_dialer/dialer.log ]; then
        LOG_LINES=$(wc -l < /var/log/progressive_dialer/dialer.log)
        LAST_ERROR=$(grep -i "error\|critical" /var/log/progressive_dialer/dialer.log | tail -1)
        echo "   Líneas de log: $LOG_LINES"
        if [ -n "$LAST_ERROR" ]; then
            echo "   ⚠ Último error: $LAST_ERROR"
        else
            echo "   ✓ Sin errores recientes"
        fi
    fi
else
    echo "   Estado: INACTIVO"
    echo "   Iniciar con: systemctl start progressive-dialer"
fi
echo ""

# Conectividad AMI
echo "6. CONECTIVIDAD AMI"
if [ -f /etc/progressive_dialer/credentials ]; then
    AMI_HOST=$(grep "AMI_HOST" /etc/progressive_dialer/credentials | cut -d'=' -f2)
    AMI_PORT=$(grep "AMI_PORT" /etc/progressive_dialer/credentials | cut -d'=' -f2)
    
    echo "   Host: $AMI_HOST:$AMI_PORT"
    if timeout 2 bash -c "cat < /dev/null > /dev/tcp/$AMI_HOST/$AMI_PORT" 2>/dev/null; then
        echo "   ✓ Puerto accesible"
    else
        echo "   ✗ No se puede conectar"
        echo "     Verifica que Asterisk esté corriendo"
    fi
else
    echo "   Archivo de credenciales no encontrado"
fi
echo ""

# Espacio en disco
echo "7. ESPACIO EN DISCO"
df -h / /var | tail -n +2 | awk '{print "   " $1 ": " $5 " usado (" $4 " libres)"}'
echo ""

# Resumen
echo "=========================================="
echo "RESUMEN"
echo "=========================================="

ISSUES=0

systemctl is-active --quiet mariadb || { echo "⚠ MariaDB no está corriendo"; ((ISSUES++)); }
systemctl is-active --quiet httpd || { echo "⚠ httpd no está corriendo"; ((ISSUES++)); }
systemctl is-active --quiet progressive-dialer || { echo "⚠ progressive-dialer no está corriendo"; ((ISSUES++)); }

[ -f /etc/progressive_dialer/credentials ] || { echo "⚠ Archivo de credenciales no encontrado"; ((ISSUES++)); }

if [ $ISSUES -eq 0 ]; then
    echo "✓ Todos los componentes están funcionando correctamente"
else
    echo "✗ Se encontraron $ISSUES problema(s)"
    echo ""
    echo "Acciones sugeridas:"
    echo "1. Revisar servicios inactivos: systemctl status <servicio>"
    echo "2. Ver logs: journalctl -xe"
    echo "3. Ejecutar validador: ./validate.sh"
fi

echo ""
echo "Para más información:"
echo "  - Logs del motor: tail -50 /var/log/progressive_dialer/dialer.log"
echo "  - Logs de Apache: tail -50 /var/log/httpd/error_log"
echo "  - Logs del sistema: journalctl -xe"
echo ""
