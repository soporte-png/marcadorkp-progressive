-- ============================================================
-- Script de configuración de base de datos
-- Progressive Dialer - Base de datos optimizada
-- ============================================================

CREATE DATABASE IF NOT EXISTS progressive_dialer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE progressive_dialer;

-- Tabla de campañas
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status ENUM('active', 'paused', 'completed') DEFAULT 'paused',
    queue_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_queue (queue_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de leads
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    custom_data_1 VARCHAR(255),
    status ENUM('pending', 'dialing', 'connected', 'completed', 'failed', 'no_answer') DEFAULT 'pending',
    agent_extension VARCHAR(20),
    retry_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_status (status),
    INDEX idx_phone (phone_number),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de llamadas
CREATE TABLE IF NOT EXISTS call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    lead_id INT,
    phone_number VARCHAR(20) NOT NULL,
    agent_extension VARCHAR(20),
    call_start_time TIMESTAMP NULL,
    call_answer_time TIMESTAMP NULL,
    call_end_time TIMESTAMP NULL,
    duration INT,
    disposition VARCHAR(50),
    uniqueid VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    INDEX idx_campaign (campaign_id),
    INDEX idx_lead (lead_id),
    INDEX idx_agent (agent_extension),
    INDEX idx_disposition (disposition),
    INDEX idx_start_time (call_start_time),
    INDEX idx_uniqueid (uniqueid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de estadísticas en tiempo real (opcional, para performance)
CREATE TABLE IF NOT EXISTS campaign_stats (
    campaign_id INT PRIMARY KEY,
    total_leads INT DEFAULT 0,
    pending_leads INT DEFAULT 0,
    dialing_calls INT DEFAULT 0,
    connected_calls INT DEFAULT 0,
    completed_calls INT DEFAULT 0,
    failed_calls INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para reportes rápidos
CREATE OR REPLACE VIEW campaign_summary AS
SELECT 
    c.id AS campaign_id,
    c.name AS campaign_name,
    c.status,
    c.queue_name,
    COUNT(l.id) AS total_leads,
    SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN l.status = 'dialing' THEN 1 ELSE 0 END) AS dialing,
    SUM(CASE WHEN l.status = 'connected' THEN 1 ELSE 0 END) AS connected,
    SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) AS failed,
    SUM(CASE WHEN l.status = 'no_answer' THEN 1 ELSE 0 END) AS no_answer,
    c.created_at,
    c.updated_at
FROM campaigns c
LEFT JOIN leads l ON c.id = l.campaign_id
GROUP BY c.id;

-- Procedimiento para limpiar campañas antiguas
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_old_campaigns(IN days_old INT)
BEGIN
    DELETE FROM campaigns 
    WHERE status = 'completed' 
    AND updated_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
END //
DELIMITER ;

-- Verificar integridad
SELECT 'Base de datos configurada correctamente' AS status;
SELECT COUNT(*) AS total_campaigns FROM campaigns;
SELECT COUNT(*) AS total_leads FROM leads;
SELECT COUNT(*) AS total_call_logs FROM call_logs;
