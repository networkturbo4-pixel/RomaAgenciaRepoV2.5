USE saas_cesar_db;

-- 1. Agregar campo DNI al cliente
ALTER TABLE clients ADD COLUMN IF NOT EXISTS dni VARCHAR(20) DEFAULT NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS portal_enabled TINYINT(1) DEFAULT 1;

-- 2. Tabla de logs de acceso del portal
CREATE TABLE IF NOT EXISTS client_portal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- 3. Tabla de notas de pago (migración desde localStorage)
CREATE TABLE IF NOT EXISTS payment_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_code VARCHAR(50) NOT NULL,
    client_id INT DEFAULT NULL,
    client_name VARCHAR(255),
    company_name VARCHAR(255),
    start_date DATE,
    total DECIMAL(10,2) DEFAULT 0,
    services_json LONGTEXT,
    schedule_json LONGTEXT,
    status VARCHAR(50) DEFAULT 'En proceso',
    public_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);
