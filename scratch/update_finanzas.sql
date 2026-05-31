-- Archivo de actualización de base de datos para el módulo de Finanzas

CREATE TABLE `finance_monthly_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period` varchar(7) NOT NULL,
  `monto_repartido` decimal(15,2) DEFAULT 0.00,
  `total_incomes` decimal(15,2) DEFAULT 0.00,
  `total_expenses` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'abierto',
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `period` (`period`),
  KEY `closed_by` (`closed_by`),
  CONSTRAINT `finance_monthly_closings_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finance_incomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa` varchar(255) NOT NULL,
  `servicio` varchar(255) NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `estado` varchar(50) DEFAULT 'pendiente',
  `n_operacion` varchar(100) DEFAULT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `voucher` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finance_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `nombre_gasto` varchar(255) NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `recurring_source_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finance_recurring_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_gasto` varchar(255) NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `dia_pago` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

