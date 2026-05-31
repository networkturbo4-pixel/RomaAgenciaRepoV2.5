<?php
// create_finance_tables.php - Migración de tablas financieras
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// ============================================================
// 1. Crear Tablas
// ============================================================

$tables = [
    "finance_monthly_closings" => "
        CREATE TABLE IF NOT EXISTS finance_monthly_closings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `period` VARCHAR(7) UNIQUE NOT NULL,
            monto_repartido DECIMAL(15,2) DEFAULT 0.00,
            total_incomes DECIMAL(15,2) DEFAULT 0.00,
            total_expenses DECIMAL(15,2) DEFAULT 0.00,
            `status` VARCHAR(20) DEFAULT 'abierto',
            closed_by INT NULL,
            closed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "finance_incomes" => "
        CREATE TABLE IF NOT EXISTS finance_incomes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa VARCHAR(255) NOT NULL,
            servicio VARCHAR(255) NOT NULL,
            monto DECIMAL(15,2) NOT NULL,
            fecha_pago DATE NOT NULL,
            estado VARCHAR(50) DEFAULT 'pendiente',
            n_operacion VARCHAR(100) DEFAULT NULL,
            banco VARCHAR(100) DEFAULT NULL,
            voucher VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "finance_expenses" => "
        CREATE TABLE IF NOT EXISTS finance_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATE NOT NULL,
            nombre_gasto VARCHAR(255) NOT NULL,
            monto DECIMAL(15,2) NOT NULL,
            categoria VARCHAR(100) NOT NULL,
            recurring_source_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "finance_recurring_expenses" => "
        CREATE TABLE IF NOT EXISTS finance_recurring_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre_gasto VARCHAR(255) NOT NULL,
            monto DECIMAL(15,2) NOT NULL,
            categoria VARCHAR(100) NOT NULL,
            dia_pago INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "✅ Tabla '$name' creada exitosamente.\n";
    } catch (PDOException $e) {
        echo "❌ Error en '$name': " . $e->getMessage() . "\n";
    }
}

// ============================================================
// 2. Datos Semilla - Plantillas de Gastos Recurrentes
// ============================================================

$recurring = [
    ['Alquiler de Oficina',       1200.00, 'Oficina',       1],
    ['Internet Fibra Óptica',      120.00, 'Servicios',     5],
    ['Suscripción Adobe CC',       250.00, 'Herramientas', 15],
    ['Suscripción Figma',           45.00, 'Herramientas', 15],
    ['Hosting y Dominio',           85.00, 'Herramientas', 10],
    ['Servicio de Luz',            180.00, 'Servicios',    20],
    ['Servicio de Agua',            60.00, 'Servicios',    20],
];

$stmtCheck = $db->query("SELECT COUNT(*) FROM finance_recurring_expenses");
if ((int)$stmtCheck->fetchColumn() === 0) {
    $stmtIns = $db->prepare("INSERT INTO finance_recurring_expenses (nombre_gasto, monto, categoria, dia_pago) VALUES (?, ?, ?, ?)");
    foreach ($recurring as $r) {
        $stmtIns->execute($r);
    }
    echo "✅ Plantillas de gastos recurrentes sembradas (" . count($recurring) . " registros).\n";
} else {
    echo "⏭️  Plantillas recurrentes ya existen, se omite la siembra.\n";
}

// ============================================================
// 3. Datos Semilla - Ingresos y Gastos de los últimos 4 meses
// ============================================================

$stmtCheckInc = $db->query("SELECT COUNT(*) FROM finance_incomes");
if ((int)$stmtCheckInc->fetchColumn() === 0) {

    $incomes = [
        // Febrero 2026
        ['TechSolutions SAC',  'Diseño Web',            3500.00, '2026-02-05', 'pagado',    'OP-20260205', 'BCP',           null],
        ['Bodega El Milagro',  'Community Manager',     1800.00, '2026-02-10', 'pagado',    'OP-20260210', 'Interbank',     null],
        ['Clínica San Martín', 'Publicidad Digital',    2200.00, '2026-02-15', 'pagado',    'OP-20260215', 'BBVA',          null],
        // Marzo 2026
        ['TechSolutions SAC',  'Mantenimiento Web',     1500.00, '2026-03-05', 'pagado',    'OP-20260305', 'BCP',           null],
        ['Restaurante Fusión', 'Branding Completo',     4200.00, '2026-03-12', 'pagado',    'OP-20260312', 'Scotiabank',    null],
        ['Bodega El Milagro',  'Community Manager',     1800.00, '2026-03-10', 'pagado',    'OP-20260310', 'Interbank',     null],
        ['Farmacia Vida',      'SEO y Ads',             2800.00, '2026-03-20', 'pagado',    'OP-20260320', 'BCP',           null],
        // Abril 2026
        ['TechSolutions SAC',  'Diseño Web',            3500.00, '2026-04-05', 'pagado',    'OP-20260405', 'BCP',           null],
        ['Bodega El Milagro',  'Community Manager',     1800.00, '2026-04-10', 'pagado',    'OP-20260410', 'Interbank',     null],
        ['Clínica San Martín', 'Publicidad Digital',    2200.00, '2026-04-15', 'pagado',    'OP-20260415', 'BBVA',          null],
        ['Restaurante Fusión', 'Social Media',          2500.00, '2026-04-18', 'pagado',    'OP-20260418', 'Scotiabank',    null],
        ['Farmacia Vida',      'SEO y Ads',             2800.00, '2026-04-22', 'pagado',    'OP-20260422', 'BCP',           null],
        // Mayo 2026
        ['TechSolutions SAC',  'Mantenimiento Web',     1500.00, '2026-05-05', 'pagado',    'OP-20260505', 'BCP',           null],
        ['Bodega El Milagro',  'Community Manager',     1800.00, '2026-05-10', 'pendiente', null,          null,            null],
        ['Clínica San Martín', 'Publicidad Digital',    2200.00, '2026-05-15', 'pendiente', null,          null,            null],
        ['Restaurante Fusión', 'Branding Completo',     4200.00, '2026-05-20', 'pendiente', null,          null,            null],
    ];

    $stmtInc = $db->prepare("INSERT INTO finance_incomes (empresa, servicio, monto, fecha_pago, estado, n_operacion, banco, voucher) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($incomes as $inc) {
        $stmtInc->execute($inc);
    }
    echo "✅ Ingresos semilla sembrados (" . count($incomes) . " registros).\n";
} else {
    echo "⏭️  Ingresos ya existen, se omite la siembra.\n";
}

$stmtCheckExp = $db->query("SELECT COUNT(*) FROM finance_expenses");
if ((int)$stmtCheckExp->fetchColumn() === 0) {
    $expenses = [
        // Febrero 2026
        ['2026-02-01', 'Alquiler de Oficina',       1200.00, 'Oficina',       null],
        ['2026-02-05', 'Internet Fibra Óptica',      120.00, 'Servicios',     null],
        ['2026-02-15', 'Suscripción Adobe CC',        250.00, 'Herramientas', null],
        ['2026-02-20', 'Servicio de Luz',             180.00, 'Servicios',    null],
        ['2026-02-20', 'Servicio de Agua',             60.00, 'Servicios',    null],
        // Marzo 2026
        ['2026-03-01', 'Alquiler de Oficina',       1200.00, 'Oficina',       null],
        ['2026-03-05', 'Internet Fibra Óptica',      120.00, 'Servicios',     null],
        ['2026-03-10', 'Materiales de Impresión',     350.00, 'Oficina',      null],
        ['2026-03-15', 'Suscripción Adobe CC',        250.00, 'Herramientas', null],
        ['2026-03-15', 'Suscripción Figma',            45.00, 'Herramientas', null],
        ['2026-03-20', 'Servicio de Luz',             190.00, 'Servicios',    null],
        ['2026-03-20', 'Servicio de Agua',             55.00, 'Servicios',    null],
        ['2026-03-25', 'Publicidad Facebook Ads',     500.00, 'Publicidad',   null],
        // Abril 2026
        ['2026-04-01', 'Alquiler de Oficina',       1200.00, 'Oficina',       null],
        ['2026-04-05', 'Internet Fibra Óptica',      120.00, 'Servicios',     null],
        ['2026-04-10', 'Hosting y Dominio',            85.00, 'Herramientas', null],
        ['2026-04-15', 'Suscripción Adobe CC',        250.00, 'Herramientas', null],
        ['2026-04-15', 'Suscripción Figma',            45.00, 'Herramientas', null],
        ['2026-04-20', 'Servicio de Luz',             175.00, 'Servicios',    null],
        ['2026-04-20', 'Servicio de Agua',             65.00, 'Servicios',    null],
        ['2026-04-22', 'Útiles de Oficina',           120.00, 'Oficina',      null],
        // Mayo 2026
        ['2026-05-01', 'Alquiler de Oficina',       1200.00, 'Oficina',       null],
        ['2026-05-05', 'Internet Fibra Óptica',      120.00, 'Servicios',     null],
        ['2026-05-15', 'Suscripción Adobe CC',        250.00, 'Herramientas', null],
    ];

    $stmtExp = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, recurring_source_id) VALUES (?, ?, ?, ?, ?)");
    foreach ($expenses as $exp) {
        $stmtExp->execute($exp);
    }
    echo "✅ Gastos semilla sembrados (" . count($expenses) . " registros).\n";
} else {
    echo "⏭️  Gastos ya existen, se omite la siembra.\n";
}

// ============================================================
// 4. Cierres de mes para meses pasados (Feb, Mar, Abr cerrados)
// ============================================================

$stmtCheckCl = $db->query("SELECT COUNT(*) FROM finance_monthly_closings");
if ((int)$stmtCheckCl->fetchColumn() === 0) {
    $closings = [
        ['2026-02', 2500.00, 7500.00, 1810.00, 'cerrado', 1],
        ['2026-03', 4000.00, 10300.00, 2710.00, 'cerrado', 1],
        ['2026-04', 5000.00, 12800.00, 2060.00, 'cerrado', 1],
    ];

    $stmtCl = $db->prepare("INSERT INTO finance_monthly_closings (`period`, monto_repartido, total_incomes, total_expenses, `status`, closed_by, closed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    foreach ($closings as $cl) {
        $stmtCl->execute($cl);
    }
    echo "✅ Cierres de mes sembrados (" . count($closings) . " registros).\n";
} else {
    echo "⏭️  Cierres de mes ya existen, se omite la siembra.\n";
}

echo "\n🎉 Migración completada.\n";
?>
