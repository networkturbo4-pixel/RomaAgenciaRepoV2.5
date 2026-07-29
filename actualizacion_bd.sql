ALTER TABLE `employees` 
ADD COLUMN `work_start` time DEFAULT NULL,
ADD COLUMN `work_end` time DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `employee_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `concept` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `extra_payment` decimal(10,2) DEFAULT 0.00,
  `extra_days` decimal(10,2) DEFAULT 0.00,
  `extra_hours` decimal(10,2) DEFAULT 0.00,
  `bonuses` decimal(10,2) DEFAULT 0.00,
  `discounts` decimal(10,2) DEFAULT 0.00,
  `voucher_url` varchar(255) DEFAULT NULL,
  `status` enum('Pagado','Pendiente','Anulado') DEFAULT 'Pagado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_payments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
