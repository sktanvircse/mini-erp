-- ============================================================
-- Mini ERP - Comprehensive Transaction Seed Data
-- Covers all transaction tables with realistic demo data
-- Run AFTER seed.sql (base master data must exist first)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- SETTINGS
-- ============================================================
INSERT INTO `settings` (`company_id`, `key_name`, `key_value`) VALUES
(1, 'app_name',            'Apex ERP'),
(1, 'currency',            'USD'),
(1, 'currency_symbol',     '$'),
(1, 'date_format',         'Y-m-d'),
(1, 'fiscal_year_start',   '01-01'),
(1, 'tax_rate_default',    '8.00'),
(1, 'invoice_prefix',      'INV'),
(1, 'po_prefix',           'PO'),
(1, 'grn_prefix',          'GRN'),
(1, 'so_prefix',           'SO'),
(1, 'delivery_prefix',     'DEL'),
(1, 'receipt_prefix',      'RCP'),
(1, 'payroll_prefix',      'PAY'),
(1, 'low_stock_alert',     '1'),
(1, 'email_notifications', '1'),
(1, 'timezone',            'America/New_York')
ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`);

-- ============================================================
-- ADDITIONAL EMPLOYEES (expand to 7 total)
-- ============================================================
INSERT INTO `employees` (`id`, `company_id`, `branch_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department_id`, `designation_id`, `joining_date`, `basic_salary`, `house_rent`, `medical_allowance`, `other_allowance`, `bank_account`, `is_active`) VALUES
(4, 1, 1, 'EMP-004', 'James',   'Wilson',  'james.wilson@apexenterprises.com',  '+1 (555) 880-1004', 4, 4, '2024-07-01', 4200.0000, 900.0000, 300.0000, 100.0000, 'CHASE-SAVINGS-6644', 1),
(5, 1, 1, 'EMP-005', 'Linda',   'Parker',  'linda.parker@apexenterprises.com',  '+1 (555) 880-1005', 2, 2, '2024-09-15', 4000.0000, 850.0000, 280.0000, 120.0000, 'BOA-CHECKING-5533', 1),
(6, 1, 1, 'EMP-006', 'Robert',  'Chen',    'robert.chen@apexenterprises.com',   '+1 (555) 880-1006', 1, 1, '2025-01-10', 5200.0000, 1100.0000, 380.0000, 180.0000, 'WF-SAVINGS-4422', 1),
(7, 1, 1, 'EMP-007', 'Angela',  'Torres',  'angela.torres@apexenterprises.com', '+1 (555) 880-1007', 3, 3, '2025-03-01', 3400.0000, 750.0000, 230.0000, 80.0000,  'CHASE-CHECKING-3311', 1)
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

-- ============================================================
-- PURCHASE REQUISITIONS
-- ============================================================
INSERT INTO `purchase_requisitions` (`id`, `company_id`, `branch_id`, `requisition_number`, `requested_by`, `date`, `status`, `remarks`, `approved_by`, `approved_at`) VALUES
(1, 1, 1, 'PR-2026-001', 4, '2026-09-01', 'approved',    'Quarterly laptop refresh for IT team',       1, '2026-09-02 10:00:00'),
(2, 1, 1, 'PR-2026-002', 5, '2026-09-10', 'approved',    'Office supplies restocking for Q4',          2, '2026-09-11 11:00:00'),
(3, 1, 1, 'PR-2026-003', 3, '2026-10-01', 'recommended', 'New ergonomic furniture for finance floor',  2, NULL),
(4, 1, 1, 'PR-2026-004', 4, '2026-10-15', 'draft',       'Additional monitors and docking stations',  NULL, NULL),
(5, 1, 1, 'PR-2026-005', 1, '2026-10-20', 'approved',    'Network cable replacement for server room',  1, '2026-10-21 09:30:00')
ON DUPLICATE KEY UPDATE `requisition_number` = VALUES(`requisition_number`);

INSERT INTO `purchase_requisition_items` (`id`, `requisition_id`, `product_id`, `quantity`, `estimated_unit_cost`, `remarks`) VALUES
-- PR-001: Laptops
(1,  1, 1, 5.00, 650.0000, 'Dell Latitude i7 for dev team'),
(2,  1, 2, 5.00,  55.0000, 'Mice for new laptops'),
(3,  1, 3, 5.00,  60.0000, 'Keyboards for new laptops'),
-- PR-002: Office supplies
(4,  2, 7, 20.00, 18.0000, 'A4 paper restock'),
(5,  2, 8, 15.00,  8.5000, 'Gel pens restock'),
-- PR-003: Furniture
(6,  3, 5, 4.00, 380.0000, 'Ergonomic chairs for finance'),
(7,  3, 6, 2.00, 210.0000, 'Standing desks for finance'),
-- PR-004: Monitors & docks
(8,  4, 4, 6.00, 240.0000, 'UltraSharp monitors'),
(9,  4, 9, 6.00,  50.0000, 'USB-C docking stations'),
-- PR-005: Cables
(10, 5, 10, 50.00, 4.5000, 'Cat6 cables for server room')
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- PURCHASE ORDERS
-- ============================================================
INSERT INTO `purchase_orders` (`id`, `company_id`, `branch_id`, `po_number`, `requisition_id`, `supplier_id`, `date`, `expected_delivery_date`, `status`, `total_amount`, `tax_amount`, `discount_amount`, `net_amount`, `remarks`, `approved_by`, `approved_at`, `created_by`) VALUES
(1, 1, 1, 'PO-2026-001', 1, 1, '2026-09-03', '2026-09-15', 'received',           4475.0000,  358.0000, 0.0000,  4833.0000, 'Laptop equipment for IT dept',       1, '2026-09-03 11:00:00', 2),
(2, 1, 1, 'PO-2026-002', 2, 2, '2026-09-12', '2026-09-22', 'received',            530.0000,   42.4000, 0.0000,   572.4000, 'Office supplies Q4',                  1, '2026-09-12 14:00:00', 2),
(3, 1, 1, 'PO-2026-003', 3, 3, '2026-10-02', '2026-10-20', 'approved',           1990.0000,  159.2000, 0.0000,  2149.2000, 'Ergonomic furniture finance dept',    1, '2026-10-02 10:00:00', 2),
(4, 1, 1, 'PO-2026-004', 5, 5, '2026-10-22', '2026-10-28', 'received',            225.0000,   18.0000, 0.0000,   243.0000, 'Cat6 cables server room',             1, '2026-10-22 09:00:00', 1),
(5, 1, 1, 'PO-2026-005', NULL,4, '2026-10-25', '2026-11-05', 'draft',             590.0000,   47.2000, 0.0000,   637.2000, 'Extra peripherals November batch',   NULL, NULL,                 1)
ON DUPLICATE KEY UPDATE `po_number` = VALUES(`po_number`);

INSERT INTO `purchase_order_items` (`id`, `po_id`, `product_id`, `quantity`, `received_quantity`, `unit_price`, `tax_rate`, `tax_amount`, `discount_amount`, `total_price`) VALUES
-- PO-001
(1,  1, 1, 5.00, 5.00, 650.0000, 8.00,  260.0000, 0.0000, 3510.0000),
(2,  1, 2, 5.00, 5.00,  55.0000, 8.00,   22.0000, 0.0000,  297.0000),
(3,  1, 3, 5.00, 5.00,  60.0000, 8.00,   24.0000, 0.0000,  324.0000),
-- PO-002
(4,  2, 7, 20.00,20.00, 18.0000, 8.00,   28.8000, 0.0000,  388.8000),
(5,  2, 8, 15.00,15.00,  8.5000, 8.00,   10.2000, 0.0000,  137.7000),
-- PO-003
(6,  3, 5,  4.00, 0.00, 380.0000, 8.00, 121.6000, 0.0000, 1641.6000),
(7,  3, 6,  2.00, 0.00, 210.0000, 8.00,  33.6000, 0.0000,  453.6000),
-- PO-004
(8,  4,10, 50.00,50.00,   4.5000, 8.00,  18.0000, 0.0000,  243.0000),
-- PO-005
(9,  5, 2, 10.00, 0.00,  55.0000, 8.00,  44.0000, 0.0000,  594.0000),
(10, 5, 9,  2.00, 0.00,  50.0000, 8.00,   8.0000, 0.0000,  108.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- GOODS RECEIPTS (GRN) - posted, stock already includes these
-- ============================================================
INSERT INTO `goods_receipts` (`id`, `company_id`, `branch_id`, `grn_number`, `po_id`, `supplier_id`, `warehouse_id`, `date`, `status`, `total_amount`, `voucher_id`, `remarks`, `received_by`) VALUES
(1, 1, 1, 'GRN-2026-001', 1, 1, 1, '2026-09-14', 'approved', 4131.0000, NULL, 'Received laptops, mice, keyboards from TechGlobal', 4),
(2, 1, 1, 'GRN-2026-002', 2, 2, 1, '2026-09-21', 'approved',  526.5000, NULL, 'Received office supplies from OfficeSmart',          4),
(3, 1, 1, 'GRN-2026-003', 4, 5, 1, '2026-10-27', 'approved',  225.0000, NULL, 'Network cables received from PrimeCable',             4)
ON DUPLICATE KEY UPDATE `grn_number` = VALUES(`grn_number`);

INSERT INTO `goods_receipt_items` (`id`, `grn_id`, `po_item_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
-- GRN-001
(1, 1, 1, 1, 5.00, 650.0000, 3250.0000),
(2, 1, 2, 2, 5.00,  55.0000,  275.0000),
(3, 1, 3, 3, 5.00,  60.0000,  300.0000),
-- GRN-002
(4, 2, 4, 7,20.00,  18.0000,  360.0000),
(5, 2, 5, 8,15.00,   8.5000,  127.5000),
-- GRN-003
(6, 3, 8,10,50.00,   4.5000,  225.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- PURCHASE RETURNS
-- ============================================================
INSERT INTO `purchase_returns` (`id`, `company_id`, `branch_id`, `return_number`, `grn_id`, `supplier_id`, `warehouse_id`, `date`, `status`, `total_amount`, `voucher_id`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'PRR-2026-001', 1, 1, 1, '2026-09-20', 'approved',  650.0000, NULL, 'One defective laptop returned to TechGlobal',  4),
(2, 1, 1, 'PRR-2026-002', 2, 2, 1, '2026-09-25', 'draft',      36.0000, NULL, 'Damaged paper packs, pending return approval',  4)
ON DUPLICATE KEY UPDATE `return_number` = VALUES(`return_number`);

INSERT INTO `purchase_return_items` (`id`, `return_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 1, 1.00, 650.0000, 650.0000),
(2, 2, 7, 2.00,  18.0000,  36.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- SALES ORDERS
-- ============================================================
INSERT INTO `sales_orders` (`id`, `company_id`, `branch_id`, `so_number`, `customer_id`, `date`, `delivery_date`, `status`, `total_amount`, `tax_amount`, `discount_amount`, `net_amount`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'SO-2026-001', 1, '2026-09-05', '2026-09-18', 'delivered',         3686.1600,  294.8930, 0.0000, 3980.0000, 'Acorn Cloud - laptop setup package',        5),
(2, 1, 1, 'SO-2026-002', 2, '2026-09-20', '2026-10-05', 'delivered',         1468.0000,  117.4400, 0.0000, 1585.4400, 'Blue Horizon - office supplies & cables',   5),
(3, 1, 1, 'SO-2026-003', 3, '2026-10-01', '2026-10-20', 'confirmed',         2988.0000,  239.0400, 0.0000, 3227.0400, 'Summit Health - monitor & dock order',      5),
(4, 1, 1, 'SO-2026-004', 4, '2026-10-10', NULL,          'draft',             1770.0000,  141.6000, 0.0000, 1911.6000, 'Vanguard Legal - furniture quotation',      5),
(5, 1, 1, 'SO-2026-005', 5, '2026-10-18', '2026-11-01', 'partially_delivered', 896.0000,  71.6800, 0.0000,  967.6800, 'Pioneer Logistics - mixed peripherals',     5)
ON DUPLICATE KEY UPDATE `so_number` = VALUES(`so_number`);

INSERT INTO `sales_order_items` (`id`, `so_id`, `product_id`, `quantity`, `delivered_quantity`, `unit_price`, `tax_rate`, `tax_amount`, `discount_amount`, `total_price`) VALUES
-- SO-001: Acorn Cloud (laptops + mouse + keyboard)
(1,  1, 1, 2.00, 2.00, 899.0000, 8.00, 143.8400, 0.0000, 1941.8400),
(2,  1, 2, 4.00, 4.00,  99.0000, 8.00,  31.6800, 0.0000,  427.6800),
(3,  1, 3, 4.00, 4.00,  95.0000, 8.00,  30.4000, 0.0000,  410.4000),
(4,  1, 9, 2.00, 2.00,  89.0000, 8.00,  14.2400, 0.0000,  192.2400),
-- SO-002: Blue Horizon (supplies + cables)
(5,  2, 7,10.00,10.00,  32.0000, 8.00,  25.6000, 0.0000,  345.6000),
(6,  2, 8,10.00,10.00,  16.0000, 8.00,  12.8000, 0.0000,  172.8000),
(7,  2,10,20.00,20.00,  12.0000, 8.00,  19.2000, 0.0000,  259.2000),
-- SO-003: Summit Health (monitors + docks)
(8,  3, 4, 6.00, 0.00, 380.0000, 8.00, 182.4000, 0.0000, 2462.4000),
(9,  3, 9, 4.00, 0.00,  89.0000, 8.00,  28.4800, 0.0000,  381.4800),
-- SO-004: Vanguard Legal (chairs)
(10, 4, 5, 3.00, 0.00, 590.0000, 8.00, 141.6000, 0.0000, 1911.6000),
-- SO-005: Pioneer (pens + paper + cables)
(11, 5, 7, 5.00, 5.00,  32.0000, 8.00,  12.8000, 0.0000,  172.8000),
(12, 5, 8, 5.00, 5.00,  16.0000, 8.00,   6.4000, 0.0000,   86.4000),
(13, 5,10,10.00, 0.00,  12.0000, 8.00,   9.6000, 0.0000,  129.6000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- DELIVERIES
-- ============================================================
INSERT INTO `deliveries` (`id`, `company_id`, `branch_id`, `delivery_number`, `so_id`, `customer_id`, `warehouse_id`, `date`, `status`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'DEL-2026-001', 1, 1, 1, '2026-09-17', 'delivered', 'Full delivery to Acorn Cloud Seattle office',   4),
(2, 1, 1, 'DEL-2026-002', 2, 2, 1, '2026-10-04', 'delivered', 'Full delivery to Blue Horizon LA office',       4),
(3, 1, 1, 'DEL-2026-003', 5, 5, 1, '2026-10-28', 'delivered', 'Partial delivery to Pioneer Logistics Chicago', 4)
ON DUPLICATE KEY UPDATE `delivery_number` = VALUES(`delivery_number`);

INSERT INTO `delivery_items` (`id`, `delivery_id`, `so_item_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
-- DEL-001: Acorn Cloud
(1,  1, 1, 1, 2.00, 899.0000, 1798.0000),
(2,  1, 2, 2, 4.00,  99.0000,  396.0000),
(3,  1, 3, 3, 4.00,  95.0000,  380.0000),
(4,  1, 4, 9, 2.00,  89.0000,  178.0000),
-- DEL-002: Blue Horizon
(5,  2, 5, 7,10.00,  32.0000,  320.0000),
(6,  2, 6, 8,10.00,  16.0000,  160.0000),
(7,  2, 7,10,20.00,  12.0000,  240.0000),
-- DEL-003: Pioneer (partial - paper & pens only)
(8,  3,11, 7, 5.00,  32.0000,  160.0000),
(9,  3,12, 8, 5.00,  16.0000,   80.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- SALES INVOICES
-- ============================================================
INSERT INTO `invoices` (`id`, `company_id`, `branch_id`, `invoice_number`, `so_id`, `delivery_id`, `customer_id`, `date`, `due_date`, `status`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `paid_amount`, `balance_amount`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'INV-2026-001', 1, 1, 1, '2026-09-17', '2026-10-17', 'paid',           2752.0000, 220.1600, 0.0000, 2972.1600, 2972.1600,    0.0000, 'Acorn Cloud full payment received',       1),
(2, 1, 1, 'INV-2026-002', 2, 2, 2, '2026-10-04', '2026-11-04', 'partially_paid', 1360.0000, 108.8000, 0.0000, 1468.8000,  800.0000,  668.8000, 'Blue Horizon partial payment - balance due', 1),
(3, 1, 1, 'INV-2026-003', 3, NULL,3, '2026-10-05', '2026-11-05', 'unpaid',        2988.0000, 239.0400, 0.0000, 3227.0400,    0.0000, 3227.0400, 'Summit Health - awaiting payment',        1),
(4, 1, 1, 'INV-2026-004', 5, 3,  5, '2026-10-28', '2026-11-28', 'unpaid',          396.0000,  31.6800, 0.0000,  427.6800,    0.0000,  427.6800, 'Pioneer partial delivery invoice',        1)
ON DUPLICATE KEY UPDATE `invoice_number` = VALUES(`invoice_number`);

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `tax_rate`, `tax_amount`, `discount_amount`, `total_price`) VALUES
-- INV-001: Acorn Cloud
(1,  1, 1, 2.00, 899.0000, 8.00, 143.8400, 0.0000, 1941.8400),
(2,  1, 2, 4.00,  99.0000, 8.00,  31.6800, 0.0000,  427.6800),
(3,  1, 3, 4.00,  95.0000, 8.00,  30.4000, 0.0000,  410.4000),
(4,  1, 9, 2.00,  89.0000, 8.00,  14.2400, 0.0000,  192.2400),
-- INV-002: Blue Horizon
(5,  2, 7,10.00,  32.0000, 8.00,  25.6000, 0.0000,  345.6000),
(6,  2, 8,10.00,  16.0000, 8.00,  12.8000, 0.0000,  172.8000),
(7,  2,10,20.00,  12.0000, 8.00,  19.2000, 0.0000,  259.2000),
-- INV-003: Summit Health
(8,  3, 4, 6.00, 380.0000, 8.00, 182.4000, 0.0000, 2462.4000),
(9,  3, 9, 4.00,  89.0000, 8.00,  28.4800, 0.0000,  381.4800),
-- INV-004: Pioneer
(10, 4, 7, 5.00,  32.0000, 8.00,  12.8000, 0.0000,  172.8000),
(11, 4, 8, 5.00,  16.0000, 8.00,   6.4000, 0.0000,   86.4000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- MONEY RECEIPTS (Customer Payments)
-- ============================================================
INSERT INTO `money_receipts` (`id`, `company_id`, `branch_id`, `receipt_number`, `customer_id`, `date`, `payment_method`, `reference_no`, `deposit_account_id`, `amount`, `status`, `voucher_id`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'RCP-2026-001', 1, '2026-10-15', 'bank',   'WIRE-ACN-20261015', 3, 2972.1600, 'approved', NULL, 'Full payment from Acorn Cloud - INV-2026-001', 3),
(2, 1, 1, 'RCP-2026-002', 2, '2026-10-20', 'cheque', 'CHQ-BHM-00445',     3,  800.0000, 'approved', NULL, 'Partial payment from Blue Horizon - INV-2026-002', 3)
ON DUPLICATE KEY UPDATE `receipt_number` = VALUES(`receipt_number`);

INSERT INTO `money_receipt_invoices` (`id`, `money_receipt_id`, `invoice_id`, `allocated_amount`) VALUES
(1, 1, 1, 2972.1600),
(2, 2, 2,  800.0000)
ON DUPLICATE KEY UPDATE `allocated_amount` = VALUES(`allocated_amount`);

-- ============================================================
-- SALES RETURNS
-- ============================================================
INSERT INTO `sales_returns` (`id`, `company_id`, `branch_id`, `return_number`, `invoice_id`, `customer_id`, `warehouse_id`, `date`, `status`, `total_amount`, `voucher_id`, `remarks`, `created_by`) VALUES
(1, 1, 1, 'SRR-2026-001', 1, 1, 1, '2026-10-22', 'approved', 192.2400, NULL, 'Acorn Cloud returned 2 defective USB-C docks', 5),
(2, 1, 1, 'SRR-2026-002', 2, 2, 1, '2026-10-25', 'draft',    172.8000, NULL, 'Blue Horizon disputed paper box quality',       5)
ON DUPLICATE KEY UPDATE `return_number` = VALUES(`return_number`);

INSERT INTO `sales_return_items` (`id`, `return_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 9, 2.00,  89.0000, 178.0000),
(2, 2, 7, 5.00,  32.0000, 160.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- STOCK TRANSFERS
-- ============================================================
INSERT INTO `stock_transfers` (`id`, `company_id`, `transfer_number`, `from_warehouse_id`, `to_warehouse_id`, `date`, `status`, `remarks`, `created_by`) VALUES
(1, 1, 'TRF-2026-001', 1, 2, '2026-09-25', 'completed', 'Retail depot replenishment - paper and pens',    4),
(2, 1, 'TRF-2026-002', 1, 2, '2026-10-10', 'completed', 'Retail depot - cables and accessories',          4),
(3, 1, 'TRF-2026-003', 1, 2, '2026-10-30', 'draft',     'Planned: furniture samples for retail showroom', 4)
ON DUPLICATE KEY UPDATE `transfer_number` = VALUES(`transfer_number`);

INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `product_id`, `quantity`, `unit_cost`, `total_cost`) VALUES
-- TRF-001
(1, 1, 7, 10.00, 18.0000, 180.0000),
(2, 1, 8, 10.00,  8.5000,  85.0000),
-- TRF-002
(3, 2, 10,20.00,  4.5000,  90.0000),
(4, 2,  9, 3.00, 50.0000, 150.0000),
-- TRF-003 (draft)
(5, 3,  5, 2.00,380.0000, 760.0000),
(6, 3,  6, 1.00,210.0000, 210.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- RETAIL WAREHOUSE STOCK BALANCES (after transfers)
-- ============================================================
INSERT INTO `stock_balances` (`company_id`, `warehouse_id`, `product_id`, `quantity`, `reserved_qty`, `avg_unit_cost`) VALUES
(1, 2, 7,  10.00, 0.00, 18.0000),
(1, 2, 8,  10.00, 0.00,  8.5000),
(1, 2, 9,   3.00, 0.00, 50.0000),
(1, 2,10,  20.00, 0.00,  4.5000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- ============================================================
-- ADDITIONAL GL VOUCHERS (Manual Journal Entries)
-- ============================================================
-- VR-002: GRN-001 Payable
INSERT INTO `vouchers` (`id`, `company_id`, `branch_id`, `voucher_number`, `voucher_type`, `date`, `reference_type`, `reference_id`, `narration`, `total_debit`, `total_credit`, `status`, `created_by`, `posted_by`, `posted_at`) VALUES
(2, 1, 1, 'VR-2026-00002', 'purchase', '2026-09-14', 'GRN', 1, 'GRN-2026-001: Goods received from TechGlobal - laptops, mice, keyboards', 4131.0000, 4131.0000, 'posted', 3, 3, '2026-09-14 16:00:00'),
(3, 1, 1, 'VR-2026-00003', 'purchase', '2026-09-21', 'GRN', 2, 'GRN-2026-002: Office supplies received from OfficeSmart',                   526.5000,  526.5000, 'posted', 3, 3, '2026-09-21 15:00:00'),
(4, 1, 1, 'VR-2026-00004', 'purchase', '2026-10-27', 'GRN', 3, 'GRN-2026-003: Network cables received from PrimeCable',                     225.0000,  225.0000, 'posted', 3, 3, '2026-10-27 14:00:00'),
-- VR-005: Sales Invoice 1
(5, 1, 1, 'VR-2026-00005', 'sales',    '2026-09-17', 'INVOICE', 1, 'INV-2026-001: Sales to Acorn Cloud Solutions',                         2972.1600, 2972.1600, 'posted', 3, 3, '2026-09-17 17:00:00'),
-- VR-006: Customer Payment - Acorn Cloud
(6, 1, 1, 'VR-2026-00006', 'receipt',  '2026-10-15', 'RECEIPT', 1, 'RCP-2026-001: Payment received from Acorn Cloud',                      2972.1600, 2972.1600, 'posted', 3, 3, '2026-10-15 11:00:00'),
-- VR-007: Sales Invoice 2
(7, 1, 1, 'VR-2026-00007', 'sales',    '2026-10-04', 'INVOICE', 2, 'INV-2026-002: Sales to Blue Horizon Media',                            1468.8000, 1468.8000, 'posted', 3, 3, '2026-10-04 15:00:00'),
-- VR-008: Partial payment Blue Horizon
(8, 1, 1, 'VR-2026-00008', 'receipt',  '2026-10-20', 'RECEIPT', 2, 'RCP-2026-002: Partial payment from Blue Horizon (cheque)',               800.0000,  800.0000, 'posted', 3, 3, '2026-10-20 10:00:00'),
-- VR-009: October Rent Expense
(9, 1, 1, 'VR-2026-00009', 'journal',  '2026-10-01', NULL, NULL, 'Monthly office rent expense - October 2026',                             3500.0000, 3500.0000, 'posted', 3, 3, '2026-10-01 09:00:00'),
-- VR-010: Utilities Expense
(10,1, 1, 'VR-2026-00010', 'journal',  '2026-10-31', NULL, NULL, 'Electricity and utilities - October 2026',                                480.0000,  480.0000, 'posted', 3, 3, '2026-10-31 09:00:00')
ON DUPLICATE KEY UPDATE `voucher_number` = VALUES(`voucher_number`);

-- ============================================================
-- VOUCHER LINES (all balanced: SUM(debit) = SUM(credit))
-- ============================================================
-- VR-002: GRN-001 (Inventory DR, AP CR)
INSERT INTO `voucher_lines` (`voucher_id`, `account_id`, `party_type`, `party_id`, `debit`, `credit`, `description`) VALUES
(2, 5, 'none',     NULL, 4131.0000,    0.0000, 'Inventory received - laptops, mice, keyboards'),
(2, 9, 'supplier',    1,    0.0000, 4131.0000, 'Accounts payable - TechGlobal Distribution'),

-- VR-003: GRN-002 (Inventory DR, AP CR)
(3, 5, 'none',     NULL,  526.5000,    0.0000, 'Inventory received - office supplies'),
(3, 9, 'supplier',    2,    0.0000,  526.5000, 'Accounts payable - OfficeSmart Wholesale'),

-- VR-004: GRN-003 (Inventory DR, AP CR)
(4, 5, 'none',     NULL,  225.0000,    0.0000, 'Network cables received'),
(4, 9, 'supplier',    5,    0.0000,  225.0000, 'Accounts payable - PrimeCable Network'),

-- VR-005: Invoice 1 (AR DR, Revenue CR, COGS DR, Inventory CR)
(5, 4, 'customer',    1, 2972.1600,    0.0000, 'Accounts receivable - Acorn Cloud Solutions'),
(5,16, 'none',     NULL,    0.0000, 2752.0000, 'Sales revenue - INV-2026-001'),
(5,11, 'none',     NULL,    0.0000,  220.1600, 'Sales tax payable - INV-2026-001'),

-- VR-006: Receipt 1 (Bank DR, AR CR)
(6, 3, 'none',     NULL, 2972.1600,    0.0000, 'Bank receipt - Acorn Cloud wire transfer'),
(6, 4, 'customer',    1,    0.0000, 2972.1600, 'Clear AR - Acorn Cloud INV-2026-001'),

-- VR-007: Invoice 2 (AR DR, Revenue CR)
(7, 4, 'customer',    2, 1468.8000,    0.0000, 'Accounts receivable - Blue Horizon Media'),
(7,16, 'none',     NULL,    0.0000, 1360.0000, 'Sales revenue - INV-2026-002'),
(7,11, 'none',     NULL,    0.0000,  108.8000, 'Sales tax payable - INV-2026-002'),

-- VR-008: Receipt 2 partial (Bank DR, AR CR)
(8, 3, 'none',     NULL,  800.0000,    0.0000, 'Bank receipt - Blue Horizon cheque CHQ-BHM-00445'),
(8, 4, 'customer',    2,    0.0000,  800.0000, 'Partial clear AR - Blue Horizon INV-2026-002'),

-- VR-009: Rent Expense
(9,23, 'none',     NULL, 3500.0000,    0.0000, 'Office rent expense - October 2026'),
(9, 3, 'none',     NULL,    0.0000, 3500.0000, 'Bank payment - rent October 2026'),

-- VR-010: Utilities
(10,24,'none',     NULL,  480.0000,    0.0000, 'Electricity and utilities expense - October 2026'),
(10, 3,'none',     NULL,    0.0000,  480.0000, 'Bank payment - utilities October 2026');

-- ============================================================
-- ATTENDANCE RECORDS (last 30 working days for 7 employees)
-- ============================================================
INSERT INTO `attendance` (`company_id`, `employee_id`, `date`, `check_in`, `check_out`, `status`, `remarks`) VALUES
-- Employee 1 - Ethan Hunt
(1,1,'2026-10-01','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-02','09:05:00','18:10:00','present',NULL),
(1,1,'2026-10-03','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-06','09:30:00','18:00:00','late','Traffic delay'),
(1,1,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-10','09:00:00','13:00:00','half_day','Doctor appointment'),
(1,1,'2026-10-13','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-17',NULL,NULL,'absent','Sick leave'),
(1,1,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-23','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-24','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-28','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-29','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-30','09:00:00','18:00:00','present',NULL),
(1,1,'2026-10-31','09:00:00','18:00:00','present',NULL),
-- Employee 2 - Sarah Connor
(1,2,'2026-10-01','08:55:00','17:55:00','present',NULL),
(1,2,'2026-10-02','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-03','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-08',NULL,NULL,'absent','Personal leave'),
(1,2,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-13','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-17','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-23','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-24','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-28','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-29','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-30','09:00:00','18:00:00','present',NULL),
(1,2,'2026-10-31','09:00:00','18:00:00','present',NULL),
-- Employee 3 - Marcus Brody
(1,3,'2026-10-01','09:10:00','18:10:00','present',NULL),
(1,3,'2026-10-02','09:40:00','18:00:00','late','Bus delay'),
(1,3,'2026-10-03','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-13',NULL,NULL,'on_leave','Annual leave'),
(1,3,'2026-10-14',NULL,NULL,'on_leave','Annual leave'),
(1,3,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-17','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-23','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-24','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-28','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-29','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-30','09:00:00','18:00:00','present',NULL),
(1,3,'2026-10-31','09:00:00','18:00:00','present',NULL),
-- Employee 4-7 (abbreviated for October)
(1,4,'2026-10-01','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-02','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-13','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-17','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-23','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-24','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,4,'2026-10-28','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-01','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-02','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-09',NULL,NULL,'absent','Sick'),
(1,5,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-13','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-17','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,5,'2026-10-28','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-01','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-02','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-13','09:30:00','18:00:00','late','Metro delay'),
(1,6,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-17','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-22','09:00:00','18:00:00','present',NULL),
(1,6,'2026-10-27','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-01','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-02','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-06','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-07','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-08','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-09','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-10','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-13','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-14','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-15','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-16','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-17',NULL,NULL,'absent','Personal'),
(1,7,'2026-10-20','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-21','09:00:00','18:00:00','present',NULL),
(1,7,'2026-10-27','09:00:00','18:00:00','present',NULL)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- ============================================================
-- LEAVE REQUESTS
-- ============================================================
INSERT INTO `leave_requests` (`id`, `company_id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `approved_by`, `approved_at`) VALUES
(1, 1, 1, 'sick',   '2026-10-17', '2026-10-17', 1, 'Flu symptoms - visited clinic',                  'approved', 2, '2026-10-16 17:00:00'),
(2, 1, 3, 'annual', '2026-10-13', '2026-10-14', 2, 'Family trip planned in advance',                 'approved', 2, '2026-10-10 10:00:00'),
(3, 1, 2, 'casual', '2026-10-08', '2026-10-08', 1, 'Personal errand - bank visit',                  'approved', 2, '2026-10-07 18:00:00'),
(4, 1, 5, 'sick',   '2026-10-09', '2026-10-09', 1, 'Food poisoning, doctor recommended rest',       'approved', 2, '2026-10-09 08:00:00'),
(5, 1, 7, 'casual', '2026-10-17', '2026-10-17', 1, 'Attending sibling wedding',                     'pending',  NULL, NULL),
(6, 1, 4, 'annual', '2026-11-10', '2026-11-14', 5, 'Planned vacation - Thanksgiving week',          'pending',  NULL, NULL)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- ============================================================
-- PAYROLL BATCHES & RECORDS (September 2026)
-- ============================================================
-- VR-011: Payroll voucher
INSERT INTO `vouchers` (`id`, `company_id`, `branch_id`, `voucher_number`, `voucher_type`, `date`, `narration`, `total_debit`, `total_credit`, `status`, `created_by`, `posted_by`, `posted_at`) VALUES
(11, 1, 1, 'VR-2026-00011', 'journal', '2026-09-30', 'Monthly payroll - September 2026 (7 employees)', 51360.0000, 51360.0000, 'posted', 3, 3, '2026-09-30 17:00:00')
ON DUPLICATE KEY UPDATE `voucher_number` = VALUES(`voucher_number`);

INSERT INTO `voucher_lines` (`voucher_id`, `account_id`, `party_type`, `party_id`, `debit`, `credit`, `description`) VALUES
(11,22,'none',NULL,44560.0000,    0.0000, 'Gross salaries expense - September 2026'),
(11,10,'none',NULL,    0.0000,37760.0000, 'Net salary payable to employees'),
(11,10,'none',NULL,    0.0000, 6800.0000, 'Tax & deductions withheld September 2026');

INSERT INTO `payroll_batches` (`id`, `company_id`, `branch_id`, `batch_number`, `month`, `year`, `start_date`, `end_date`, `status`, `total_basic`, `total_allowances`, `total_deductions`, `net_salary`, `voucher_id`, `approved_by`, `approved_at`, `created_by`) VALUES
(1, 1, 1, 'PAY-2026-09', 9, 2026, '2026-09-01', '2026-09-30', 'paid', 31700.0000, 12660.0000, 1900.0000, 42460.0000, 11, 1, '2026-09-30 16:00:00', 3)
ON DUPLICATE KEY UPDATE `batch_number` = VALUES(`batch_number`);

INSERT INTO `payroll_items` (`id`, `payroll_batch_id`, `employee_id`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `working_days`, `present_days`, `leave_days`, `absent_days`, `remarks`) VALUES
(1, 1, 1, 5500.0000, 1800.0000, 365.0000, 6935.0000, 22, 22, 0, 0, NULL),
(2, 1, 2, 4800.0000, 1500.0000, 315.0000, 5985.0000, 22, 22, 0, 0, NULL),
(3, 1, 3, 3600.0000, 1150.0000, 237.5000, 4512.5000, 22, 22, 0, 0, NULL),
(4, 1, 4, 4200.0000, 1300.0000, 275.0000, 5225.0000, 22, 22, 0, 0, NULL),
(5, 1, 5, 4000.0000, 1250.0000, 262.5000, 4987.5000, 22, 22, 0, 0, NULL),
(6, 1, 6, 5200.0000, 1660.0000, 343.0000, 6517.0000, 22, 22, 0, 0, NULL),
(7, 1, 7, 3400.0000, 1060.0000, 223.0000, 4237.0000, 22, 22, 0, 0, NULL)
ON DUPLICATE KEY UPDATE `basic_salary` = VALUES(`basic_salary`);

-- ============================================================
-- ADDITIONAL STOCK LEDGER ENTRIES (for GRN & Delivery movements)
-- ============================================================
INSERT INTO `stock_ledger` (`company_id`, `branch_id`, `warehouse_id`, `product_id`, `date`, `reference_type`, `reference_id`, `qty_in`, `qty_out`, `unit_cost`, `total_cost`, `remarks`) VALUES
-- GRN-001 inflows
(1,1,1,1,'2026-09-14','GRN',1, 5.00,0.00,650.0000, 3250.0000,'GRN-2026-001: Laptops received'),
(1,1,1,2,'2026-09-14','GRN',1, 5.00,0.00, 55.0000,  275.0000,'GRN-2026-001: Mice received'),
(1,1,1,3,'2026-09-14','GRN',1, 5.00,0.00, 60.0000,  300.0000,'GRN-2026-001: Keyboards received'),
-- GRN-002 inflows
(1,1,1,7,'2026-09-21','GRN',2,20.00,0.00, 18.0000,  360.0000,'GRN-2026-002: Paper received'),
(1,1,1,8,'2026-09-21','GRN',2,15.00,0.00,  8.5000,  127.5000,'GRN-2026-002: Pens received'),
-- GRN-003 inflows
(1,1,1,10,'2026-10-27','GRN',3,50.00,0.00, 4.5000,  225.0000,'GRN-2026-003: Cables received'),
-- DEL-001 outflows (Acorn Cloud)
(1,1,1,1,'2026-09-17','DELIVERY',1,0.00, 2.00,650.0000,1300.0000,'DEL-2026-001: Laptops to Acorn Cloud'),
(1,1,1,2,'2026-09-17','DELIVERY',1,0.00, 4.00, 55.0000, 220.0000,'DEL-2026-001: Mice to Acorn Cloud'),
(1,1,1,3,'2026-09-17','DELIVERY',1,0.00, 4.00, 60.0000, 240.0000,'DEL-2026-001: Keyboards to Acorn Cloud'),
(1,1,1,9,'2026-09-17','DELIVERY',1,0.00, 2.00, 50.0000, 100.0000,'DEL-2026-001: Docks to Acorn Cloud'),
-- DEL-002 outflows (Blue Horizon)
(1,1,1,7,'2026-10-04','DELIVERY',2,0.00,10.00, 18.0000, 180.0000,'DEL-2026-002: Paper to Blue Horizon'),
(1,1,1,8,'2026-10-04','DELIVERY',2,0.00,10.00,  8.5000,  85.0000,'DEL-2026-002: Pens to Blue Horizon'),
(1,1,1,10,'2026-10-04','DELIVERY',2,0.00,20.00, 4.5000,  90.0000,'DEL-2026-002: Cables to Blue Horizon'),
-- DEL-003 outflows (Pioneer partial)
(1,1,1,7,'2026-10-28','DELIVERY',3,0.00, 5.00, 18.0000,  90.0000,'DEL-2026-003: Paper to Pioneer'),
(1,1,1,8,'2026-10-28','DELIVERY',3,0.00, 5.00,  8.5000,  42.5000,'DEL-2026-003: Pens to Pioneer'),
-- Stock Transfers outflows (WH1 -> WH2)
(1,1,1,7,'2026-09-25','TRANSFER_OUT',1,0.00,10.00,18.0000,180.0000,'TRF-2026-001: Paper to retail depot'),
(1,1,1,8,'2026-09-25','TRANSFER_OUT',1,0.00,10.00, 8.5000, 85.0000,'TRF-2026-001: Pens to retail depot'),
-- Stock Transfers inflows (WH2)
(1,1,2,7,'2026-09-25','TRANSFER_IN',1,10.00,0.00,18.0000,180.0000,'TRF-2026-001: Paper received at retail'),
(1,1,2,8,'2026-09-25','TRANSFER_IN',1,10.00,0.00, 8.5000, 85.0000,'TRF-2026-001: Pens received at retail'),
-- TRF-002
(1,1,1,10,'2026-10-10','TRANSFER_OUT',2,0.00,20.00,4.5000, 90.0000,'TRF-2026-002: Cables to retail'),
(1,1,1, 9,'2026-10-10','TRANSFER_OUT',2,0.00, 3.00,50.0000,150.0000,'TRF-2026-002: Docks to retail'),
(1,1,2,10,'2026-10-10','TRANSFER_IN', 2,20.00,0.00,4.5000, 90.0000,'TRF-2026-002: Cables at retail'),
(1,1,2, 9,'2026-10-10','TRANSFER_IN', 2, 3.00,0.00,50.0000,150.0000,'TRF-2026-002: Docks at retail'),
-- Sales Return inflow
(1,1,1,9,'2026-10-22','SALES_RETURN',1, 2.00,0.00,50.0000,100.0000,'SRR-2026-001: Docks returned by Acorn Cloud'),
-- Purchase Return outflow
(1,1,1,1,'2026-09-20','PURCHASE_RETURN',1,0.00,1.00,650.0000,650.0000,'PRR-2026-001: Defective laptop returned to TechGlobal');

-- ============================================================
-- UPDATE STOCK BALANCES (main warehouse - net result)
-- Starting stock + GRN - Deliveries - Transfers - PurchaseReturn + SalesReturn
-- Product 1 (Laptop): 15 + 5 - 2 - 1(pr_return) = 17
-- Product 2 (Mouse):  40 + 5 - 4 = 41
-- Product 3 (Keyboard): 25 + 5 - 4 = 26
-- Product 4 (Monitor): 12 (no movement)
-- Product 5 (Chair): 8 (no movement)
-- Product 6 (Desk):  6 (no movement)
-- Product 7 (Paper): 65 + 20 - 10 - 5 - 10(transfer) = 60
-- Product 8 (Pens):  50 + 15 - 10 - 5 - 10(transfer) = 40
-- Product 9 (Dock):  20 - 2 - 3(transfer) + 2(sales_return) = 17
-- Product 10 (Cable): 80 + 50 - 20 - 20(transfer) = 90
-- ============================================================
UPDATE `stock_balances` SET `quantity` = 17.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=1;
UPDATE `stock_balances` SET `quantity` = 41.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=2;
UPDATE `stock_balances` SET `quantity` = 26.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=3;
UPDATE `stock_balances` SET `quantity` = 60.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=7;
UPDATE `stock_balances` SET `quantity` = 40.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=8;
UPDATE `stock_balances` SET `quantity` = 17.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=9;
UPDATE `stock_balances` SET `quantity` = 90.00 WHERE `company_id`=1 AND `warehouse_id`=1 AND `product_id`=10;

-- Also update products.current_stock
UPDATE `products` SET `current_stock` = 17.00 WHERE `id`=1;
UPDATE `products` SET `current_stock` = 41.00 WHERE `id`=2;
UPDATE `products` SET `current_stock` = 26.00 WHERE `id`=3;
UPDATE `products` SET `current_stock` = 60.00 WHERE `id`=7;
UPDATE `products` SET `current_stock` = 40.00 WHERE `id`=8;
UPDATE `products` SET `current_stock` = 17.00 WHERE `id`=9;
UPDATE `products` SET `current_stock` = 90.00 WHERE `id`=10;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
INSERT INTO `audit_logs` (`company_id`, `branch_id`, `user_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`) VALUES
(1,1,1,'login',   'Auth',     NULL,    NULL, '{"email":"admin@minierp.com"}',                           '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,1,'create',  'Product',  '1',     NULL, '{"code":"PRD-001","name":"Dell Latitude 5420 Laptop"}',    '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,2,'create',  'PurchaseOrder','1', NULL, '{"po_number":"PO-2026-001","supplier_id":1}',              '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,1,'update',  'PurchaseOrder','1', '{"status":"draft"}', '{"status":"approved"}',                   '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,4,'create',  'GoodsReceipt','1',  NULL, '{"grn_number":"GRN-2026-001"}',                            '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,5,'create',  'SalesOrder','1',    NULL, '{"so_number":"SO-2026-001","customer_id":1}',              '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,4,'create',  'Delivery', '1',     NULL, '{"delivery_number":"DEL-2026-001"}',                       '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,3,'create',  'Invoice',  '1',     NULL, '{"invoice_number":"INV-2026-001","total_amount":2972.16}', '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,3,'create',  'MoneyReceipt','1',  NULL, '{"receipt_number":"RCP-2026-001","amount":2972.16}',       '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,3,'create',  'Voucher',  '11',    NULL, '{"voucher_number":"VR-2026-00011","type":"journal"}',      '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,1,'create',  'User',     '5',     NULL, '{"name":"Sales Executive","email":"sales@minierp.com"}',   '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)'),
(1,1,2,'update',  'LeaveRequest','2',  '{"status":"pending"}','{"status":"approved"}',                   '127.0.0.1','Mozilla/5.0 (Windows NT 10.0)');

-- ============================================================
-- DOCUMENT SEQUENCES
-- ============================================================
INSERT INTO `document_sequences` (`company_id`, `document_type`, `year`, `last_number`, `prefix`) VALUES
(1, 'PR',   2026, 5,  'PR'),
(1, 'PO',   2026, 5,  'PO'),
(1, 'GRN',  2026, 3,  'GRN'),
(1, 'PRR',  2026, 2,  'PRR'),
(1, 'SO',   2026, 5,  'SO'),
(1, 'DEL',  2026, 3,  'DEL'),
(1, 'INV',  2026, 4,  'INV'),
(1, 'RCP',  2026, 2,  'RCP'),
(1, 'SRR',  2026, 2,  'SRR'),
(1, 'TRF',  2026, 3,  'TRF'),
(1, 'VR',   2026, 11, 'VR'),
(1, 'PAY',  2026, 1,  'PAY')
ON DUPLICATE KEY UPDATE `last_number` = VALUES(`last_number`);

SET FOREIGN_KEY_CHECKS = 1;

-- Done! All tables seeded with realistic demo data.
SELECT 'Seed completed successfully!' AS result;
