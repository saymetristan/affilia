-- Crear usuario administrador por defecto
-- Password: admin123 (cambiar después del primer login)

INSERT INTO users (
    email,
    password,
    name,
    is_admin,
    created_at
) VALUES (
    'admin@numok.local',
    '$2y$10$bLQ3Qd64NRSxvc7A2wKJAe/ocgCCkB5jbyC11I1XklnjDClzO6vpK',
    'Admin User',
    1,
    CURRENT_TIMESTAMP
) ON DUPLICATE KEY UPDATE email=email;

-- Insertar configuraciones básicas
INSERT INTO settings (name, value) VALUES 
('company_name', 'Numok Affiliates'),
('company_logo', ''),
('stripe_secret_key', ''),
('stripe_webhook_secret', ''),
('commission_rate', '10.00'),
('cookie_duration', '30'),
('payout_threshold', '100.00')
ON DUPLICATE KEY UPDATE name=name;