-- Mini ERP Production SQL Dump
-- Contains clean schema and essential seed data only (no demo transactions or demo catalog)
-- Charset: utf8mb4, Engine: InnoDB

-- Step 1: Include Schema
-- (Execute schema.sql before or include tables below)
SOURCE schema.sql;

-- Step 2: Essential System Data
SET FOREIGN_KEY_CHECKS = 0;

-- Default Company & Branch
INSERT INTO `companies` (`id`, `code`, `name`, `email`, `phone`, `address`, `currency`, `is_active`)
VALUES (1, 'COMP01', 'Default Enterprise Inc', 'admin@example.com', '+1 (555) 000-0000', 'Enterprise Headquarters', 'USD', 1);

INSERT INTO `branches` (`id`, `company_id`, `code`, `name`, `email`, `phone`, `address`, `is_active`)
VALUES (1, 1, 'MAIN', 'Main Branch', 'main@example.com', '+1 (555) 000-0001', 'Main Branch Address', 1);

-- System Roles
INSERT INTO `roles` (`id`, `company_id`, `name`, `description`, `is_system`) VALUES
(1, 1, 'Admin', 'Full administrative system access with all permissions', 1),
(2, 1, 'Manager', 'Operational manager with approval and reporting privileges', 1),
(3, 1, 'Accountant', 'Financial operations, journals, ledgers, vouchers and reports', 1),
(4, 1, 'Storekeeper', 'Inventory warehouse management, goods receipts and transfers', 1),
(5, 1, 'Sales', 'Customer relationship, orders, quotations and invoicing', 1);

-- Permissions
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `description`) VALUES
(1, 'users.view', 'View Users', 'Admin', 'View user accounts'),
(2, 'users.manage', 'Manage Users', 'Admin', 'Create, update, delete user accounts'),
(3, 'roles.manage', 'Manage Roles', 'Admin', 'Create and modify roles and permissions'),
(4, 'audit.view', 'View Audit Logs', 'Admin', 'Inspect system audit trails'),
(5, 'settings.manage', 'Manage Settings', 'Admin', 'Modify company and system settings'),
(10, 'master.view', 'View Master Data', 'Master', 'View products, suppliers, customers, categories, units, warehouses'),
(11, 'products.manage', 'Manage Products', 'Master', 'Create and edit products'),
(12, 'suppliers.manage', 'Manage Suppliers', 'Master', 'Create and edit suppliers'),
(13, 'customers.manage', 'Manage Customers', 'Master', 'Create and edit customers'),
(14, 'warehouses.manage', 'Manage Warehouses', 'Master', 'Create and edit warehouses'),
(20, 'purchase.view', 'View Purchase', 'Purchase', 'View requisitions, POs, GRNs and returns'),
(21, 'purchase.create', 'Create Purchase', 'Purchase', 'Create requisitions and purchase orders'),
(22, 'purchase.approve', 'Approve Purchase', 'Purchase', 'Approve purchase requisitions and orders'),
(23, 'purchase.grn', 'Manage GRN', 'Purchase', 'Receive goods receipts against orders'),
(24, 'purchase.return', 'Manage Purchase Returns', 'Purchase', 'Process purchase returns to suppliers'),
(30, 'inventory.view', 'View Inventory', 'Inventory', 'View stock balances and stock movements'),
(31, 'inventory.transfer', 'Stock Transfer', 'Inventory', 'Execute warehouse stock transfers'),
(32, 'inventory.reports', 'Inventory Reports', 'Inventory', 'Generate stock ledger and low stock reports'),
(40, 'sales.view', 'View Sales', 'Sales', 'View sales orders, deliveries and invoices'),
(41, 'sales.create', 'Create Sales', 'Sales', 'Create sales orders and quotations'),
(42, 'sales.approve', 'Approve Sales', 'Sales', 'Approve and confirm sales orders'),
(43, 'sales.deliver', 'Manage Deliveries', 'Sales', 'Create and process customer deliveries'),
(44, 'sales.invoice', 'Manage Invoices', 'Sales', 'Create and send sales invoices'),
(45, 'sales.receipt', 'Manage Money Receipts', 'Sales', 'Record and allocate customer payments'),
(46, 'sales.return', 'Manage Sales Returns', 'Sales', 'Process customer sales returns'),
(50, 'accounts.view', 'View Accounts', 'Accounts', 'View chart of accounts, vouchers and trial balance'),
(51, 'accounts.coa', 'Manage Chart of Accounts', 'Accounts', 'Create and update financial accounts'),
(52, 'accounts.voucher', 'Create Vouchers', 'Accounts', 'Draft journal, payment and receipt vouchers'),
(53, 'accounts.post', 'Post Vouchers', 'Accounts', 'Post vouchers to financial ledger'),
(54, 'accounts.reports', 'Financial Reports', 'Accounts', 'View Balance Sheet, P&L, Trial Balance, Ledgers'),
(55, 'accounts.periods', 'Financial Periods', 'Accounts', 'Manage fiscal years and accounting periods'),
(60, 'hr.view', 'View HR', 'HR', 'View employees, attendance and leave'),
(61, 'hr.manage', 'Manage HR', 'HR', 'Create employees, departments and designations'),
(62, 'hr.attendance', 'Manage Attendance', 'HR', 'Record and update daily attendance'),
(63, 'hr.leave', 'Manage Leave', 'HR', 'Approve or reject leave requests'),
(64, 'hr.payroll', 'Manage Payroll', 'HR', 'Generate and approve monthly payroll batches');

-- Role Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `code` NOT IN ('roles.manage', 'settings.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `module` IN ('Accounts', 'Sales', 'Purchase') OR `code` IN ('master.view', 'hr.view', 'hr.payroll');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `module` = 'Inventory' OR `code` IN ('master.view', 'products.manage', 'purchase.view', 'purchase.grn', 'sales.view', 'sales.deliver');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `id` FROM `permissions` WHERE `module` = 'Sales' OR `code` IN ('master.view', 'customers.manage', 'inventory.view');

-- Production Admin User (Requires password change upon initial login)
INSERT INTO `users` (`id`, `company_id`, `branch_id`, `role_id`, `name`, `email`, `password`, `phone`, `is_active`, `force_password_change`)
VALUES (1, 1, 1, 1, 'System Administrator', 'admin@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 000-000-0000', 1, 1);

-- Standard Chart of Accounts
INSERT INTO `chart_of_accounts` (`id`, `company_id`, `code`, `name`, `type`, `parent_id`, `is_active`) VALUES
(1, 1, '1000', 'Current Assets', 'asset', NULL, 1),
(2, 1, '1010', 'Cash in Hand', 'asset', 1, 1),
(3, 1, '1020', 'Operating Bank Account', 'asset', 1, 1),
(4, 1, '1030', 'Accounts Receivable (Customers)', 'asset', 1, 1),
(5, 1, '1040', 'Inventory / Stock Asset', 'asset', 1, 1),
(6, 1, '1500', 'Fixed Assets', 'asset', NULL, 1),
(7, 1, '1510', 'Office Equipment & Computers', 'asset', 6, 1),
(8, 1, '2000', 'Current Liabilities', 'liability', NULL, 1),
(9, 1, '2010', 'Accounts Payable (Suppliers)', 'liability', 8, 1),
(10, 1, '2020', 'Salaries & Wages Payable', 'liability', 8, 1),
(11, 1, '2030', 'Sales Tax Payable', 'liability', 8, 1),
(12, 1, '3000', 'Equity', 'equity', NULL, 1),
(13, 1, '3010', 'Owner Capital Investment', 'equity', 12, 1),
(14, 1, '3020', 'Retained Earnings', 'equity', 12, 1),
(15, 1, '4000', 'Operating Revenue', 'income', NULL, 1),
(16, 1, '4010', 'Sales Revenue', 'income', 15, 1),
(17, 1, '4020', 'Sales Discounts Allowed', 'income', 15, 1),
(18, 1, '4030', 'Other Operating Income', 'income', 15, 1),
(19, 1, '5000', 'Direct Expenses', 'expense', NULL, 1),
(20, 1, '5010', 'Cost of Goods Sold (COGS)', 'expense', 19, 1),
(21, 1, '5100', 'Administrative & Operating Expenses', 'expense', NULL, 1),
(22, 1, '5110', 'Salaries & Wages Expense', 'expense', 21, 1),
(23, 1, '5120', 'Office Rent Expense', 'expense', 21, 1),
(24, 1, '5130', 'Electricity & Utilities Expense', 'expense', 21, 1),
(25, 1, '5140', 'Office Supplies Expense', 'expense', 21, 1);

-- Default Settings
INSERT INTO `settings` (`company_id`, `key_name`, `key_value`) VALUES
(1, 'company_name', 'Default Enterprise Inc'),
(1, 'currency_code', 'USD'),
(1, 'currency_symbol', '$'),
(1, 'tax_rate_default', '0.00'),
(1, 'allow_negative_stock', '0'),
(1, 'auto_post_vouchers', '1');

-- Document Sequences
INSERT INTO `document_sequences` (`company_id`, `document_type`, `year`, `last_number`, `prefix`) VALUES
(1, 'PR', YEAR(CURRENT_DATE), 0, 'PR'),
(1, 'PO', YEAR(CURRENT_DATE), 0, 'PO'),
(1, 'GRN', YEAR(CURRENT_DATE), 0, 'GRN'),
(1, 'SO', YEAR(CURRENT_DATE), 0, 'SO'),
(1, 'DEL', YEAR(CURRENT_DATE), 0, 'DEL'),
(1, 'INV', YEAR(CURRENT_DATE), 0, 'INV'),
(1, 'MR', YEAR(CURRENT_DATE), 0, 'MR'),
(1, 'VR', YEAR(CURRENT_DATE), 0, 'VR'),
(1, 'ST', YEAR(CURRENT_DATE), 0, 'ST'),
(1, 'PRT', YEAR(CURRENT_DATE), 0, 'PRT'),
(1, 'SRT', YEAR(CURRENT_DATE), 0, 'SRT'),
(1, 'PAY', YEAR(CURRENT_DATE), 0, 'PAY');

SET FOREIGN_KEY_CHECKS = 1;
