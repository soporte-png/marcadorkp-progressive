# Guía de Despliegue - Progressive Dialer
## Desde Windows a Rocky Linux 8.10 vía SFTP

---

## 📁 Estructura del Proyecto (Organizada)

```
marcador2025/
├── bin/                          # Motor Python
│   └── dialer_engine.py
├── web/                          # Aplicación web
│   ├── index.php
│   ├── db.php
│   ├── upload.php
│   ├── campaign_control.php
│   ├── get_status.php
│   ├── reports.php
│   ├── amitest.php
│   ├── app.js
│   └── style.css
├── docs/                         # Documentación
│   ├── README.md
│   ├── CHANGELOG.md
│   ├── ROCKY8-QUICKSTART.md
│   └── setup_database.sql
├── scripts/                      # Scripts de instalación
│   ├── install.sh
│   ├── validate.sh
│   ├── diagnostico.sh
│   ├── reorganize.sh
│   └── reorganize.ps1
└── config/                       # Configuración
    └── credentials.example
```

---

## 🚀 Proceso de Despliegue

### PASO 1: Preparar en Windows (tu PC)

```powershell
# Abrir PowerShell en el directorio del proyecto
cd "C:\OneDrive\Escritorio\marcador2025"

# Comprimir el proyecto
Compress-Archive -Path bin,web,docs,scripts,config -DestinationPath progressive_dialer.zip -Force

# O si tienes WSL/Git Bash instalado:
tar -czf progressive_dialer.tar.gz bin web docs scripts config
```

**Alternativa con 7-Zip:**
```powershell
& "C:\Program Files\7-Zip\7z.exe" a -ttar progressive_dialer.tar bin web docs scripts config
& "C:\Program Files\7-Zip\7z.exe" a -tgzip progressive_dialer.tar.gz progressive_dialer.tar
```

---

### PASO 2: Subir al Servidor Rocky Linux 8.10

**Opción A - WinSCP:**
1. Abrir WinSCP
2. Conectar al servidor:
   - Host: IP de tu servidor Rocky 8.10
   - Usuario: tu_usuario
   - Puerto: 22
3. Navegar a `/tmp/` en el servidor
4. Arrastrar `progressive_dialer.tar.gz` (o `.zip`)

**Opción B - FileZilla:**
1. Abrir FileZilla
2. Host: `sftp://tu_servidor` Usuario/Contraseña
3. Subir archivo a `/tmp/`

**Opción C - Línea de comandos (desde PowerShell con OpenSSH):**
```powershell
scp progressive_dialer.tar.gz usuario@tu-servidor:/tmp/
```

---

### PASO 3: Desplegar en Rocky Linux 8.10

**Conectar al servidor:**
```bash
ssh usuario@tu-servidor
```

**Extraer y preparar:**
```bash
cd /tmp
tar -xzf progressive_dialer.tar.gz
cd progressive_dialer

# O si subiste ZIP:
unzip progressive_dialer.zip
cd progressive_dialer
```

**Dar permisos a scripts:**
```bash
chmod +x scripts/*.sh
```

**Instalar:**
```bash
sudo ./scripts/install.sh
```

Esto instalará:
- ✅ Dependencias del sistema (Python, PHP, MariaDB, Apache)
- ✅ Dependencias Python (mysql-connector)
- ✅ Estructura de directorios en `/opt/progressive_dialer`
- ✅ Servicio systemd `progressive-dialer`
- ✅ Configuración de Apache

---

### PASO 4: Configurar Credenciales

```bash
sudo nano /etc/progressive_dialer/credentials
```

**Editar estos valores:**
```ini
# Base de Datos
DB_PASS=tu_contraseña_mysql

# AMI Asterisk
AMI_USER=admin
AMI_SECRET=tu_contraseña_ami
```

Guardar: `Ctrl+X`, `Y`, `Enter`

---

### PASO 5: Validar Instalación

```bash
sudo ./scripts/validate.sh
```

Debe mostrar **0 errores**.

---

### PASO 6: Iniciar el Sistema

```bash
# Iniciar motor del marcador
sudo systemctl start progressive-dialer

# Ver estado
sudo systemctl status progressive-dialer

# Ver logs en tiempo real
sudo tail -f /var/log/progressive_dialer/dialer.log
```

---

## 🌐 Acceso a la Aplicación

```
http://IP_DEL_SERVIDOR/progressive_dialer
```

Por ejemplo: `http://192.168.1.100/progressive_dialer`

---

## 🔧 Comandos Útiles

### Ver logs
```bash
# Logs del motor
sudo tail -100 /var/log/progressive_dialer/dialer.log

# Logs de Apache
sudo tail -50 /var/log/httpd/error_log

# Logs del sistema
sudo journalctl -u progressive-dialer -n 50
```

### Reiniciar servicios
```bash
sudo systemctl restart progressive-dialer
sudo systemctl restart httpd
sudo systemctl restart mariadb
```

### Diagnóstico completo
```bash
cd /tmp/progressive_dialer
sudo ./scripts/diagnostico.sh
```

---

## 📝 Notas Importantes

### SELinux
- ❌ **No configurado** (según tu preferencia, está desactivado)

### Firewall
- ❌ **No gestionado por el servidor** (tienes firewall intermedio)
- ⚠️ Asegúrate de que tu firewall intermedio permite:
  - Puerto 80 (HTTP) al servidor
  - Puerto 443 (HTTPS) si usas SSL

### Seguridad
- ✅ Credenciales en archivo protegido (`/etc/progressive_dialer/credentials`)
- ✅ Permisos 600 en archivo de credenciales
- ✅ Validación y sanitización de datos en PHP
- ✅ Prepared statements en todas las consultas SQL

---

## 🔄 Actualización del Proyecto

Cuando hagas cambios en tu PC:

```powershell
# En Windows
cd "C:\OneDrive\Escritorio\marcador2025"
Compress-Archive -Path bin,web,docs,scripts,config -DestinationPath progressive_dialer.zip -Force
# Subir vía SFTP
```

```bash
# En el servidor
cd /tmp
unzip -o progressive_dialer.zip
sudo cp -r bin/dialer_engine.py /opt/progressive_dialer/bin/
sudo cp -r web/* /opt/progressive_dialer/web/
sudo systemctl restart progressive-dialer
sudo systemctl restart httpd
```

---

## 📞 Troubleshooting Rápido

### El motor no inicia
```bash
sudo journalctl -u progressive-dialer -n 50 -f
# Verificar credenciales
sudo cat /etc/progressive_dialer/credentials
```

### La web no carga
```bash
sudo systemctl status httpd
sudo tail -50 /var/log/httpd/error_log
```

### No conecta a la base de datos
```bash
sudo systemctl status mariadb
mysql -u root -p progressive_dialer
```

### No conecta a AMI
```bash
telnet 127.0.0.1 5038
sudo asterisk -rx "manager show connected"
```

---

## ✅ Checklist de Despliegue

- [ ] Proyecto comprimido en Windows
- [ ] Archivo subido al servidor vía SFTP
- [ ] Extraído en `/tmp/`
- [ ] Scripts con permisos de ejecución
- [ ] Instalador ejecutado sin errores
- [ ] Credenciales configuradas
- [ ] Validador ejecutado (0 errores)
- [ ] Motor iniciado
- [ ] Interfaz web accesible
- [ ] Primera campaña creada y probada

---

**Fecha:** Octubre 2025  
**Servidor:** Rocky Linux 8.10  
**Desarrollo:** Windows 10/11  
**Transferencia:** SFTP
