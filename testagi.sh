#!/bin/bash
# Script para verificar variables AGI en tiempo real

echo "=== 1. TEST DE CONEXIÓN MYSQL DEL AGI ==="
cat > /tmp/test_mysql_agi.py << 'EOF'
#!/usr/bin/env python3
import mysql.connector
try:
    conn = mysql.connector.connect(
        host='localhost',
        user='root', 
        password='vonaGe3102iP',
        database='progressive_dialer'
    )
    print("✅ Conexión MySQL exitosa")
    cursor = conn.cursor()
    
    # Test 1: Insertar con lead_id único
    cursor.execute("INSERT INTO call_logs (lead_id, campaign_id, phone_number, disposition) VALUES (999, 999, 'TEST_CONNECTION', 'TEST')")
    conn.commit()
    print(f"✅ Inserción 1 exitosa - ID: {cursor.lastrowid}")
    
    # Test 2: Intentar insertar MISMO lead_id para ver si falla
    try:
        cursor.execute("INSERT INTO call_logs (lead_id, campaign_id, phone_number, disposition) VALUES (999, 998, 'TEST_DUPLICATE', 'TEST2')")
        conn.commit()
        print(f"✅ Inserción 2 exitosa - ID: {cursor.lastrowid} (NO hay constraint UNIQUE)")
    except Exception as e2:
        print(f"❌ Error inserción duplicada: {e2}")
        print("🔍 CONFIRMADO: Hay constraint UNIQUE en lead_id")
    
    # Limpiar tests
    cursor.execute("DELETE FROM call_logs WHERE lead_id = 999")
    conn.commit()
    print("✅ Test completado exitosamente")
    conn.close()
except Exception as e:
    print(f"❌ Error MySQL: {e}")
EOF

python3 /tmp/test_mysql_agi.py

echo ""
echo "=== 2. VERIFICAR LOGS AGI DETALLADOS ==="
echo "Logs de AGI dialer_call_log.py:"
tail -200 /var/log/asterisk/full | grep -A50 -B10 "dialer_call_log.py"
echo ""
echo "Logs de verbose AGI (últimos 100 líneas):"
tail -200 /var/log/asterisk/full | grep -E "(AGI:|AGI DEBUG|AGI ERROR|AGI WARNING)"
echo ""
echo "Logs AGI recientes (últimos 5 minutos):"
tail -500 /var/log/asterisk/full | grep -E "$(date +'%Y-%m-%d %H:%M'|cut -c1-13)" | grep -E "(dialer_call_log|AGI)"

echo ""
echo "=== 3. VERIFICAR ESTRUCTURA TABLA call_logs ==="
echo "Estructura actual de call_logs:"
mysql -u root -pvonaGe3102iP progressive_dialer -e "DESCRIBE call_logs;"
echo ""
echo "Todos los registros en call_logs:"
mysql -u root -pvonaGe3102iP progressive_dialer -e "SELECT * FROM call_logs ORDER BY id DESC LIMIT 10;"
echo ""
echo "Total de registros en call_logs:"
mysql -u root -pvonaGe3102iP progressive_dialer -e "SELECT COUNT(*) as total FROM call_logs;"

echo ""
echo "=== 4. VERIFICAR LEADS ACTIVOS ==="
mysql -u root -pvonaGe3102iP progressive_dialer -e "SELECT id, phone_number, status, last_updated FROM leads WHERE status IN ('connected', 'dialing') ORDER BY last_updated DESC LIMIT 10;"

echo ""
echo "=== 5. VERIFICAR CANALES Y CONTEXTOS ACTIVOS ==="
asterisk -rx "core show channels concise" | grep -E "(dialer-|queue|Local)"

echo ""
echo "=== 6. VERIFICAR PERMISOS DEL AGI ==="
ls -la /var/lib/asterisk/agi-bin/dialer_call_log.py

echo ""
echo "=== 7. TEST DIRECTO DEL AGI CON VARIABLES ==="
echo "Ejecutando dialer_call_log.py directamente para ver errores..."
cat > /tmp/test_direct_agi.py << 'EOF'
#!/usr/bin/env python3
import sys
import os

# Agregar path del AGI
sys.path.append('/var/lib/asterisk/agi-bin')

print("=== TEST DIRECTO AGI ===")
try:
    # Importar el módulo
    import dialer_call_log
    print("✅ Módulo importado correctamente")
    
    # Intentar crear instancia AGI
    agi = dialer_call_log.AGI()
    print("✅ AGI class funciona")
    
    # Intentar función log_call_result
    dialer_call_log.log_call_result()
    print("✅ log_call_result ejecutada")
    
except ImportError as e:
    print(f"❌ Error importando módulo: {e}")
except Exception as e:
    print(f"❌ Error ejecutando AGI: {e}")
    import traceback
    print("TRACEBACK:")
    traceback.print_exc()
EOF

python3 /tmp/test_direct_agi.py

echo ""
echo "=== 8. VERIFICAR DEPENDENCIAS PYTHON ==="
echo "Verificando mysql.connector..."
python3 -c "import mysql.connector; print('✅ mysql.connector OK')" 2>/dev/null || echo "❌ mysql.connector FALTA"
echo "Verificando psutil..."
python3 -c "import psutil; print('✅ psutil OK')" 2>/dev/null || echo "❌ psutil FALTA"
