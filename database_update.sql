ALTER TABLE employee_payments ADD COLUMN bonuses DECIMAL(10,2) DEFAULT 0.00 AFTER extra_hours;
ALTER TABLE employee_payments ADD COLUMN discounts DECIMAL(10,2) DEFAULT 0.00 AFTER bonuses;
