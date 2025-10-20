# Progressive Dialer - Sistema de Marcador Progresivo

Sistema completo de marcador progresivo para Asterisk con interfaz web PHP y motor Python.

**✨ Optimizado para Rocky Linux 8.10**

## 🚀 Características

- ✅ Marcación automática progresiva basada en agentes disponibles
- ✅ Gestión de campañas con múltiples estados
- ✅ Interfaz web intuitiva y responsive
- ✅ Reportes detallados con filtros y exportación CSV
- ✅ Sistema de logs completo
- ✅ Credenciales seguras en archivo externo
- ✅ Validación robusta de datos
- ✅ Limpieza automática de leads atascados
- ✅ Paginación en reportes
- ✅ Dashboard en tiempo real

## 📋 Requisitos

### Sistema
- **Rocky Linux 8.10** (Recomendado)
- Rocky Linux 9 / AlmaLinux 8/9 / CentOS 8 Stream
- RHEL 8/9 / Ubuntu 20.04+
- Asterisk con AMI habilitado
- MariaDB/MySQL 10.3+
- Apache/httpd con PHP 7.4+
- Python 3.6+

### Paquetes necesarios
- python3-mysql-connector
- php-mysqlnd
- php-fpm (opcional, para mejor performance)

## 🔧 Instalación Rápida

### Para Rocky Linux 8.10

```bash
# 1. Reorganizar estructura (si es necesario)
chmod +x reorganize.sh
./reorganize.sh

# 2. Descargar/subir el proyecto al servidor
cd /tmp
tar -xzf progressive_dialer.tar.gz
cd progressive_dialer

# 3. Ejecutar instalador
chmod +x scripts/install.sh scripts/rocky8-setup.sh
sudo scripts/install.sh

# 4. Verificación post-instalación
sudo scripts/rocky8-setup.sh

# 5. Editar credenciales
sudo nano /etc/progressive_dialer/credentials

# 6. Validar instalación
chmod +x scripts/validate.sh
sudo scripts/validate.sh

# 7. Iniciar motor
sudo systemctl start progressive-dialer
sudo systemctl status progressive-dialer
```

### Instalación en otros sistemas

### 1. Descarga el proyecto
```bash
cd /tmp
# Descargar y descomprimir el proyecto
tar -xzf progressive_dialer.tar.gz
cd progressive_dialer
```

### 2. Ejecutar instalador automático
```bash
chmod +x install.sh
sudo ./install.sh
```

### 3. Configurar credenciales
```bash
sudo nano /etc/progressive_dialer/credentials
```

Edita las siguientes variables:
```ini
DB_PASS=tu_contraseña_mysql
AMI_USER=tu_usuario_ami
AMI_SECRET=tu_contraseña_ami
```

### 4. Validar instalación
```bash
chmod +x validate.sh
sudo ./validate.sh
```

### 5. Diagnóstico rápido (opcional)
```bash
chmod +x diagnostico.sh
sudo ./diagnostico.sh
```

### 6. Iniciar el motor
```bash
sudo systemctl start progressive-dialer
sudo systemctl status progressive-dialer
```

## 📁 Estructura de Archivos

```
progressive_dialer/
├── bin/                          # Binarios y ejecutables
│   └── dialer_engine.py          # Motor principal Python
│
├── web/                          # Aplicación web
│   ├── index.php                 # Dashboard
│   ├── db.php                    # Conexión a BD
│   ├── upload.php                # Carga de leads CSV
│   ├── campaign_control.php      # Control de campañas
│   ├── reports.php               # Reportes con paginación
│   ├── get_status.php            # API para dashboard
│   ├── amitest.php               # Test de AMI
│   ├── app.js                    # Frontend JavaScript
│   └── style.css                 # Estilos
│
├── docs/                         # Documentación
│   ├── README.md                 # Documentación principal
│   ├── CHANGELOG.md              # Registro de cambios
│   ├── ROCKY8-QUICKSTART.md      # Guía rápida Rocky 8
│   └── setup_database.sql        # Schema de BD
│
├── scripts/                      # Scripts de instalación
│   ├── install.sh                # Instalador universal
│   ├── validate.sh               # Validador del sistema
│   ├── diagnostico.sh            # Diagnóstico completo
│   └── rocky8-setup.sh           # Verificación post-instalación
│
└── config/                       # Configuración
    └── credentials.example       # Plantilla de credenciales

# En producción (/opt/progressive_dialer/)
├── bin/
├── web/ → /var/www/html/progressive_dialer (symlink)
├── docs/
└── config/

# Configuración del sistema
/etc/progressive_dialer/
└── credentials                   # Credenciales (chmod 600)

/var/log/progressive_dialer/      # Logs del sistema
└── dialer.log                    # Log principal
```

## 🗄️ Base de Datos

### Tablas principales

**campaigns**
- Almacena las campañas creadas
- Estados: `active`, `paused`, `completed`

**leads**
- Contactos a llamar
- Estados: `pending`, `dialing`, `connected`, `completed`, `failed`, `no_answer`
- Campo `retry_count` para reintentos

**call_logs**
- Registro de todas las llamadas realizadas
- Incluye tiempos, duración, disposición

**campaign_stats** (opcional)
- Estadísticas pre-calculadas para mejor rendimiento

### Ejecutar schema manualmente
```bash
mysql -u root -p < /opt/progressive_dialer/setup_database.sql
```

## 🎯 Uso

### Crear una campaña

1. Accede a la interfaz web: `http://tu-servidor/progressive_dialer`
2. En "Crear Nueva Campaña":
   - Nombre: Identifica tu campaña
   - Cola: Nombre de la cola de Asterisk (ej: `500`)
   - CSV: Archivo con columnas `phone_number`, `first_name`, `last_name`

### Formato del CSV
```csv
phone_number,first_name,last_name
555123456,Juan,Pérez
555789012,María,González
```

### Activar campaña
1. En la tabla de campañas, clic en "▶ Activar"
2. Solo puede haber una campaña activa a la vez
3. El motor comenzará a marcar automáticamente

### Ver reportes
1. Clic en "Ver Reporte de Llamadas"
2. Filtra por campaña o disposición
3. Exporta a CSV si necesitas

## 🔐 Seguridad

### Credenciales
- **Nunca** incluyas credenciales en código
- Usa `/etc/progressive_dialer/credentials` con permisos `600`
- Solo el usuario root o apache/www-data debe tener acceso

### Validación de datos
- Todos los inputs están sanitizados
- Límite de 10 MB para archivos CSV
- Máximo 100,000 leads por campaña
- Números de teléfono validados (7-20 dígitos)

### Logs
- Todos los eventos se registran en `/var/log/progressive_dialer/dialer.log`
### Rotación de logs recomendada con logrotate

## ️ Administración

### Ver logs en tiempo real
```bash
tail -f /var/log/progressive_dialer/dialer.log
```

### Reiniciar el motor
```bash
sudo systemctl restart progressive-dialer
```

### Estado del motor
```bash
sudo systemctl status progressive-dialer
```

### Detener el motor
```bash
sudo systemctl stop progressive-dialer
```

### Reiniciar servicios web
```bash
sudo systemctl restart httpd  # o apache2
```

## 📊 Dashboard

El dashboard se actualiza automáticamente cada 3 segundos mostrando:
- Campaña activa
- Agentes libres (próximamente)
- Llamadas timbrando
- Llamadas activas
- Leads pendientes

## 🐛 Troubleshooting

### Problemas Comunes

#### El motor no inicia
```bash
# Ver logs
journalctl -u progressive-dialer -n 50 -f

# Verificar credenciales
sudo cat /etc/progressive_dialer/credentials

# Validar Python
python3 /opt/progressive_dialer/dialer_engine.py
```

### No se conecta a la BD
```bash
# Probar conexión manual
mysql -u root -p progressive_dialer

# Verificar permisos
SHOW GRANTS FOR 'root'@'localhost';
```

### AMI no responde
```bash
# Verificar configuración AMI
sudo cat /etc/asterisk/manager.conf

# Probar conexión con telnet
telnet 127.0.0.1 5038
```

### La web no carga
```bash
# Verificar Apache
sudo systemctl status httpd

# Ver logs de Apache
sudo tail -f /var/log/httpd/error_log

# Permisos
sudo chown -R apache:apache /var/www/html/progressive_dialer
```

## 🔄 Actualización

```bash
cd /opt/progressive_dialer
sudo git pull  # Si usas git
sudo systemctl restart progressive-dialer
```

## 📝 Mantenimiento

### Limpiar campañas antiguas (más de 90 días)
```sql
CALL cleanup_old_campaigns(90);
```

### Optimizar tablas
```sql
OPTIMIZE TABLE leads, call_logs, campaigns;
```

### Backup de base de datos
```bash
mysqldump -u root -p progressive_dialer > backup_$(date +%Y%m%d).sql
```

## 🤝 Soporte

Para problemas o preguntas:
1. Revisa los logs: `/var/log/progressive_dialer/dialer.log`
2. Ejecuta el validador: `sudo ./validate.sh`
3. Verifica la configuración de Asterisk AMI

## 📜 Licencia

Este proyecto es de uso interno. Todos los derechos reservados.

## 🔧 Configuración Avanzada

### Ajustar frecuencia de marcación
Edita `/etc/progressive_dialer/credentials`:
```ini
LOOP_DELAY_SECONDS=3  # Segundos entre cada ciclo
STALE_LEAD_MINUTES=2  # Minutos para marcar leads como fallidos
```

### Cambiar nivel de logs
```ini
LOG_LEVEL=INFO  # DEBUG, INFO, WARNING, ERROR, CRITICAL
```

## 🎉 Características Próximas

- [ ] Ratios de marcación configurables (1:1, 1:2, etc.)
- [ ] Reintentos automáticos para llamadas fallidas
- [ ] Horarios de marcación
- [ ] Blacklist de números
- [ ] Integración con CRM
- [ ] API REST
- [ ] Grabación automática de llamadas

---

**Versión:** 2.0  
**Última actualización:** Octubre 2025
