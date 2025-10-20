import time
import mysql.connector
import socket
import sys
import os
import logging
from datetime import datetime
try:
    # Opciones de TCP keepalive (Linux)
    import socket
    if hasattr(socket, 'TCP_KEEPIDLE'):
        socket.TCP_KEEPIDLE = socket.TCP_KEEPIDLE
    else:
        socket.TCP_KEEPIDLE = 4
    if hasattr(socket, 'TCP_KEEPINTVL'):
        socket.TCP_KEEPINTVL = socket.TCP_KEEPINTVL
    else:
        socket.TCP_KEEPINTVL = 5
    if hasattr(socket, 'TCP_KEEPCNT'):
        socket.TCP_KEEPCNT = socket.TCP_KEEPCNT
    else:
        socket.TCP_KEEPCNT = 6
except:
    pass

# #################################################
# ## CONFIGURACIÓN DESDE ARCHIVO ##
# #################################################
def load_credentials():
    """Carga las credenciales desde archivo externo"""
    paths = [
        '/etc/progressive_dialer/credentials',
        os.path.join(os.path.dirname(__file__), 'config', 'credentials'),
        os.path.join(os.path.dirname(__file__), '..', 'config', 'credentials')
    ]

    for path in paths:
        if os.path.exists(path):
            config = {}
            with open(path, 'r') as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith('#') and '=' in line:
                        key, value = line.split('=', 1)
                        config[key.strip()] = value.strip()
            return config

    raise FileNotFoundError("No se encontró el archivo de credenciales. Consulte la documentación.")

# Cargar configuración
try:
    CONFIG = load_credentials()
except FileNotFoundError as e:
    print(f"ERROR CRÍTICO: {e}")
    sys.exit(1)

DB_CONFIG = {
    'host': CONFIG.get('DB_HOST', 'localhost'),
    'user': CONFIG.get('DB_USER', 'root'),
    'password': CONFIG.get('DB_PASS', ''),
    'database': CONFIG.get('DB_NAME', 'progressive_dialer')
}

AMI_CONFIG = {
    'host': CONFIG.get('AMI_HOST', '127.0.0.1'),
    'port': int(CONFIG.get('AMI_PORT', 5038)),
    'username': CONFIG.get('AMI_USER', 'admin'),
    'secret': CONFIG.get('AMI_SECRET', '')
}

LOOP_DELAY_SECONDS = int(CONFIG.get('LOOP_DELAY_SECONDS', 3))
STALE_LEAD_MINUTES = int(CONFIG.get('STALE_LEAD_MINUTES', 2))
LOG_FILE = CONFIG.get('LOG_FILE', '/var/log/progressive_dialer/dialer.log')
LOG_LEVEL = CONFIG.get('LOG_LEVEL', 'INFO')

# Configurar logging
os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL),
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)
# #################################################

def flush_print(message):
    """Mantener compatibilidad, ahora usa logging"""
    logger.info(message)

class AMIClient:
    def __init__(self, host, port, username, secret):
        self.config = {'host': host, 'port': port, 'username': username, 'secret': secret}
        self.socket = None
        self._is_connected = False
        self.reconnect_attempts = 0
        self.max_reconnects = 3
    def connect(self):
        try:
            if self.socket: self.socket.close()
            self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            # Aumentar timeout y configurar opciones de socket
            self.socket.settimeout(10)  # Aumentado de 5 a 10 segundos
            self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_KEEPALIVE, 1)
            self.socket.setsockopt(socket.IPPROTO_TCP, socket.TCP_KEEPIDLE, 60)
            self.socket.setsockopt(socket.IPPROTO_TCP, socket.TCP_KEEPINTVL, 30)
            self.socket.setsockopt(socket.IPPROTO_TCP, socket.TCP_KEEPCNT, 3)

            logger.info(f"Conectando a AMI {self.config['host']}:{self.config['port']}")
            self.socket.connect((self.config['host'], self.config['port']))

            # Leer banner de bienvenida
            welcome = self.socket.recv(4096).decode('utf-8')
            logger.debug(f"AMI Banner: {welcome.strip()}")

            login_action = f"Action: Login\r\nUsername: {self.config['username']}\r\nSecret: {self.config['secret']}\r\n\r\n"
            self.socket.sendall(login_action.encode('utf-8'))
            login_response = self.socket.recv(4096).decode('utf-8')

            if 'Response: Success' not in login_response:
                raise Exception(f"Fallo en la autenticación AMI: {login_response}")

            self._is_connected = True
            self.reconnect_attempts = 0
            logger.info("Conexión con AMI establecida/restablecida exitosamente.")

        except Exception as e:
            logger.error(f"Error al conectar a AMI (intento {self.reconnect_attempts + 1}): {e}")
            self.close()
            self.reconnect_attempts += 1
            raise
    def is_connected(self): return self._is_connected
    def send_action(self, action_dict, end_event_marker):
        if not self.is_connected():
            raise ConnectionError("No conectado a AMI.")

        action_str = "\r\n".join([f"{k}: {v}" for k, v in action_dict.items()]) + "\r\n\r\n"

        try:
            # Enviar acción
            logger.debug(f"Enviando acción AMI: {action_dict.get('Action', 'Unknown')}")
            self.socket.sendall(action_str.encode('utf-8'))

            # Leer respuesta con timeout extendido
            response_bytes = bytearray()
            end_marker_bytes = end_event_marker.encode('utf-8')
            timeout_counter = 0
            max_timeout = 30  # 30 segundos máximo

            while end_marker_bytes not in response_bytes and timeout_counter < max_timeout:
                try:
                    # Usar timeout más corto en recv para poder checkear contador
                    self.socket.settimeout(1)
                    chunk = self.socket.recv(4096)
                    if not chunk:
                        raise ConnectionError("La conexión con AMI se cerró inesperadamente.")
                    response_bytes.extend(chunk)
                    timeout_counter = 0  # Reset counter si recibimos datos
                except socket.timeout:
                    timeout_counter += 1
                    continue

            # Restaurar timeout original
            self.socket.settimeout(10)

            if timeout_counter >= max_timeout:
                raise socket.timeout("Timeout esperando respuesta AMI")

            return response_bytes.decode('utf-8', errors='ignore')

        except (socket.timeout, ConnectionError, BrokenPipeError, ConnectionResetError, OSError) as e:
            logger.warning(f"Error de red con AMI: {e}. Se intentará reconectar.")
            self.close()
            raise ConnectionError(e)
    def ping_ami(self):
        """Verifica si la conexión AMI está activa"""
        if not self.is_connected():
            return False
        try:
            # Enviar ping simple
            ping_action = {'Action': 'Ping'}
            response = self.send_action(ping_action, 'Response:')
            return 'Response: Success' in response or 'Pong' in response
        except:
            return False

    def close(self):
        if self.socket:
            try: self.socket.sendall(b"Action: Logoff\r\n\r\n")
            except: pass
            finally: self.socket.close()
        self.socket = None
        self._is_connected = False

def parse_ami_response(response_str):
    events = []
    for event_block in response_str.strip().split('\r\n\r\n'):
        if not event_block: continue
        event_dict = {}
        for line in event_block.split('\r\n'):
            if ':' in line:
                key, value = line.split(':', 1)
                event_dict[key.strip()] = value.strip()
        if event_dict.get('Event'): events.append(event_dict)
    return events

def get_available_agents_raw(ami_client, queue_name):
    available_agents = 0
    try:
        raw_response = ami_client.send_action({'Action': 'QueueStatus', 'Queue': queue_name}, 'Event: QueueStatusComplete')
        events = parse_ami_response(raw_response)
        for event in events:
            if event.get('Event') == 'QueueMember' and event.get('Queue') == queue_name:
                if event.get('Status') == '1' and event.get('Paused') != '1':
                    available_agents += 1
    except ConnectionError: return 0
    except Exception as e: flush_print(f"Error procesando estado de la cola: {e}"); return 0
    flush_print(f"Agentes realmente disponibles en '{queue_name}': {available_agents}")
    return available_agents

def originate_call_raw(ami_client, campaign_id, queue_name, lead):
    lead_id, phone_number = lead
    flush_print(f"Lanzando llamada para Lead ID: {lead_id}, Número: {phone_number}")

    # Variables a pasar al dialplan
    variables = {
        'LEAD_ID': lead_id,
        'CAMPAIGN_ID': campaign_id,
        'QUEUE_NAME': queue_name,
        'PHONE_NUMBER': phone_number
    }

    try:
        # IssabelPBX: Usar contexto seguro para evitar bucles infinitos con ext-queues
        # Context: donde va cuando se CONTESTA la llamada
        # Channel: donde se ORIGINA la llamada
        action = {
            'Action': 'Originate',
            'Channel': f'Local/{phone_number}@dialer-outbound/n',
            'Context': 'dialer-answered',
            'Exten': phone_number,
            'Priority': '1',
            'CallerID': f'Campaign_{campaign_id}',
            'Async': 'true',
            'Timeout': '60000',
            'Variable': ",".join([f"{k}={v}" for k, v in variables.items()])
        }
        response = ami_client.send_action(action, 'Response: Success')

        if 'Response: Error' in response: raise Exception(response)
        return lead_id
    except ConnectionError: return None
    except Exception as e: flush_print(f"Error al originar llamada para lead {lead_id}: {e}"); return None


def get_db_connection():
    try: return mysql.connector.connect(**DB_CONFIG)
    except Exception as e: logger.error(f"Error de base de datos: {e}"); return None

def get_active_campaign(cursor):
    cursor.execute("SELECT id, queue_name FROM campaigns WHERE status = 'active' LIMIT 1"); return cursor.fetchone()

def get_dialing_calls_count(cursor, campaign_id):
    cursor.execute("SELECT COUNT(id) FROM leads WHERE campaign_id = %s AND status IN ('dialing', 'connected')", (campaign_id,));
    count = cursor.fetchone()[0]
    flush_print(f"Llamadas actualmente activas (dialing/connected): {count}"); return count

def get_leads_to_call(cursor, campaign_id, limit):
    cursor.execute("SELECT id, phone_number FROM leads WHERE campaign_id = %s AND status = 'pending' ORDER BY id ASC LIMIT %s", (campaign_id, limit)); return cursor.fetchall()

def cleanup_stale_leads(cursor):
    try:
        query_dialing = "UPDATE leads SET status = 'failed' WHERE status = 'dialing' AND last_updated < NOW() - INTERVAL %s MINUTE"
        cursor.execute(query_dialing, (STALE_LEAD_MINUTES,));
        if cursor.rowcount > 0: logger.info(f"Limpieza: {cursor.rowcount} leads 'dialing' atascados fueron marcados como 'failed'.")

        query_connected = "UPDATE leads SET status = 'completed' WHERE status = 'connected' AND last_updated < NOW() - INTERVAL 120 MINUTE"
        cursor.execute(query_connected)
        if cursor.rowcount > 0: logger.info(f"Limpieza: {cursor.rowcount} leads 'connected' atascados fueron marcados como 'completed'.")
    except Exception as e: logger.error(f"Error durante la limpieza de leads atascados: {e}")

def main():
    ami_client = AMIClient(**AMI_CONFIG)
    logger.info("=== Motor del Marcador Progresivo Iniciado ===")
    logger.info(f"Configuración: Loop={LOOP_DELAY_SECONDS}s, Stale={STALE_LEAD_MINUTES}min")

    # Contador para verificación periódica de AMI
    ami_health_check_counter = 0
    ami_health_check_interval = 10  # Cada 10 loops

    try:
        while True:
            if not ami_client.is_connected():
                try:
                    if ami_client.reconnect_attempts >= ami_client.max_reconnects:
                        logger.warning(f"Máximo número de reconexiones AMI alcanzado ({ami_client.max_reconnects}). Esperando más tiempo...")
                        ami_client.reconnect_attempts = 0
                        time.sleep(LOOP_DELAY_SECONDS * 3)  # Esperar más tiempo
                    ami_client.connect()
                except Exception as e:
                    logger.warning(f"Fallo al reconectar a AMI: {e}. Reintentando...")
                    time.sleep(LOOP_DELAY_SECONDS)
                    continue

            db_conn = get_db_connection()
            if not db_conn: time.sleep(LOOP_DELAY_SECONDS); continue

            # Verificación periódica de salud AMI
            ami_health_check_counter += 1
            if ami_health_check_counter >= ami_health_check_interval:
                if not ami_client.ping_ami():
                    logger.warning("AMI no responde a ping. Reconectando...")
                    ami_client.close()
                ami_health_check_counter = 0

            cursor = db_conn.cursor(); cleanup_stale_leads(cursor); db_conn.commit()
            active_campaign = get_active_campaign(cursor)

            if active_campaign:
                campaign_id, queue_name = active_campaign
                flush_print(f"--- Campaña activa: {campaign_id} | Cola: {queue_name} ---")
                available_agents = get_available_agents_raw(ami_client, queue_name)
                # Persistir agentes disponibles para el dashboard
                try:
                    cursor.execute(
                        "UPDATE campaigns SET available_agents = %s WHERE id = %s",
                        (int(available_agents), int(campaign_id))
                    )
                    db_conn.commit()
                except Exception as e:
                    logger.error(f"No se pudo actualizar available_agents para la campaña {campaign_id}: {e}")
                dialing_calls = get_dialing_calls_count(cursor, campaign_id)
                calls_to_make = available_agents - dialing_calls

                if calls_to_make > 0:
                    flush_print(f"Decisión: Realizar {calls_to_make} llamadas.")
                    leads = get_leads_to_call(cursor, campaign_id, calls_to_make)
                    if leads:
                        lead_ids_to_update = []
                        for lead in leads:
                            originated_lead_id = originate_call_raw(ami_client, campaign_id, queue_name, lead)
                            if originated_lead_id: lead_ids_to_update.append(originated_lead_id)

                        if lead_ids_to_update:
                            format_strings = ','.join(['%s'] * len(lead_ids_to_update))
                            cursor.execute(f"UPDATE leads SET status = 'dialing' WHERE id IN ({format_strings})", tuple(lead_ids_to_update))
                            db_conn.commit()
                            flush_print(f"Leads {lead_ids_to_update} actualizados a 'dialing'.")
                    else: logger.info("No hay más leads pendientes en la campaña.")
                else: logger.debug("No se necesitan más llamadas en este momento.")
            else: logger.debug("No hay campañas activas. Esperando...")

            cursor.close(); db_conn.close()
            time.sleep(LOOP_DELAY_SECONDS)

    except (KeyboardInterrupt, SystemExit): logger.info("Proceso interrumpido por el usuario.")
    except Exception as e: logger.critical(f"Error crítico en el motor del marcador: {e}", exc_info=True)
    finally:
        if ami_client: ami_client.close()
        logger.info("Conexión con AMI cerrada. El motor se ha detenido.")

if __name__ == "__main__":
    main()

