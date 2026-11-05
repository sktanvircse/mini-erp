-- Mini ERP Seed Data
-- Default Company, Branch, Roles, Permissions, Admin User, COA, Master Data, and Sample Transactions

SET FOREIGN_KEY_CHECKS = 0;

-- 1. COMPANY
INSERT INTO `companies` (`id`, `code`, `name`, `email`, `phone`, `address`, `currency`, `is_active`)
VALUES (1, 'COMP01', 'Apex Enterprises Ltd', 'info@apexenterprises.com', '+1 (555) 019-2834', '100 Enterprise Way, Suite 400, New York, NY 10001', 'USD', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. BRANCH
INSERT INTO `branches` (`id`, `company_id`, `code`, `name`, `email`, `phone`, `address`, `is_active`)
VALUES (1, 1, 'BR01', 'Main Branch - Headquarters', 'main@apexenterprises.com', '+1 (555) 019-2835', '100 Enterprise Way, Floor 1, New York, NY 10001', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 3. ROLES
INSERT INTO `roles` (`id`, `company_id`, `name`, `description`, `is_system`) VALUES
(1, 1, 'Admin', 'Full administrative system access with all permissions', 1),
(2, 1, 'Manager', 'Operational manager with approval and reporting privileges', 1),
(3, 1, 'Accountant', 'Financial operations, journals, ledgers, vouchers and reports', 1),
(4, 1, 'Storekeeper', 'Inventory warehouse management, goods receipts and transfers', 1),
(5, 1, 'Sales', 'Customer relationship, orders, quotations and invoicing', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 4. PERMISSIONS
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `description`) VALUES
-- Admin
(1, 'users.view', 'View Users', 'Admin', 'View user accounts'),
(2, 'users.manage', 'Manage Users', 'Admin', 'Create, update, delete user accounts'),
(3, 'roles.manage', 'Manage Roles', 'Admin', 'Create and modify roles and permissions'),
(4, 'audit.view', 'View Audit Logs', 'Admin', 'Inspect system audit trails'),
(5, 'settings.manage', 'Manage Settings', 'Admin', 'Modify company and system settings'),
-- Master Data
(10, 'master.view', 'View Master Data', 'Master', 'View products, suppliers, customers, categories, units, warehouses'),
(11, 'products.manage', 'Manage Products', 'Master', 'Create and edit products'),
(12, 'suppliers.manage', 'Manage Suppliers', 'Master', 'Create and edit suppliers'),
(13, 'customers.manage', 'Manage Customers', 'Master', 'Create and edit customers'),
(14, 'warehouses.manage', 'Manage Warehouses', 'Master', 'Create and edit warehouses'),
-- Purchase
(20, 'purchase.view', 'View Purchase', 'Purchase', 'View requisitions, POs, GRNs and returns'),
(21, 'purchase.create', 'Create Purchase', 'Purchase', 'Create requisitions and purchase orders'),
(22, 'purchase.approve', 'Approve Purchase', 'Purchase', 'Approve purchase requisitions and orders'),
(23, 'purchase.grn', 'Manage GRN', 'Purchase', 'Receive goods receipts against orders'),
(24, 'purchase.return', 'Manage Purchase Returns', 'Purchase', 'Process purchase returns to suppliers'),
-- Inventory
(30, 'inventory.view', 'View Inventory', 'Inventory', 'View stock balances and stock movements'),
(31, 'inventory.transfer', 'Stock Transfer', 'Inventory', 'Execute warehouse stock transfers'),
(32, 'inventory.reports', 'Inventory Reports', 'Inventory', 'Generate stock ledger and low stock reports'),
-- Sales
(40, 'sales.view', 'View Sales', 'Sales', 'View sales orders, deliveries and invoices'),
(41, 'sales.create', 'Create Sales', 'Sales', 'Create sales orders and quotations'),
(42, 'sales.approve', 'Approve Sales', 'Sales', 'Approve and confirm sales orders'),
(43, 'sales.deliver', 'Manage Deliveries', 'Sales', 'Create and process customer deliveries'),
(44, 'sales.invoice', 'Manage Invoices', 'Sales', 'Create and send sales invoices'),
(45, 'sales.receipt', 'Manage Money Receipts', 'Sales', 'Record and allocate customer payments'),
(46, 'sales.return', 'Manage Sales Returns', 'Sales', 'Process customer sales returns'),
-- Accounts
(50, 'accounts.view', 'View Accounts', 'Accounts', 'View chart of accounts, vouchers and trial balance'),
(51, 'accounts.coa', 'Manage Chart of Accounts', 'Accounts', 'Create and update financial accounts'),
(52, 'accounts.voucher', 'Create Vouchers', 'Accounts', 'Draft journal, payment and receipt vouchers'),
(53, 'accounts.post', 'Post Vouchers', 'Accounts', 'Post vouchers to financial ledger'),
(54, 'accounts.reports', 'Financial Reports', 'Accounts', 'View Balance Sheet, P&L, Trial Balance, Ledgers'),
(55, 'accounts.periods', 'Financial Periods', 'Accounts', 'Manage fiscal years and accounting periods'),
-- HR & Payroll
(60, 'hr.view', 'View HR', 'HR', 'View employees, attendance and leave'),
(61, 'hr.manage', 'Manage HR', 'HR', 'Create employees, departments and designations'),
(62, 'hr.attendance', 'Manage Attendance', 'HR', 'Record and update daily attendance'),
(63, 'hr.leave', 'Manage Leave', 'HR', 'Approve or reject leave requests'),
(64, 'hr.payroll', 'Manage Payroll', 'HR', 'Generate and approve monthly payroll batches')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 5. ROLE PERMISSIONS
-- Admin has all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Manager permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `code` NOT IN ('roles.manage', 'settings.manage');

-- Accountant permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `module` IN ('Accounts', 'Sales', 'Purchase') OR `code` IN ('master.view', 'hr.view', 'hr.payroll');

-- Storekeeper permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `module` = 'Inventory' OR `code` IN ('master.view', 'products.manage', 'purchase.view', 'purchase.grn', 'sales.view', 'sales.deliver');

-- Sales permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `id` FROM `permissions` WHERE `module` = 'Sales' OR `code` IN ('master.view', 'customers.manage', 'inventory.view');

-- 6. USERS (Admin user: admin / Admin@123)
INSERT INTO `users` (`id`, `company_id`, `branch_id`, `role_id`, `name`, `email`, `password`, `phone`, `is_active`, `force_password_change`)
VALUES
(1, 1, 1, 1, 'System Administrator', 'admin@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 555-0100', 1, 0),
(2, 1, 1, 2, 'Operations Manager', 'manager@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 555-0101', 1, 0),
(3, 1, 1, 3, 'Chief Accountant', 'accountant@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 555-0102', 1, 0),
(4, 1, 1, 4, 'Head Storekeeper', 'store@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 555-0103', 1, 0),
(5, 1, 1, 5, 'Sales Executive', 'sales@minierp.com', '$2y$12$HVTZL141fw7XuBzuwBtyw.XJqt3IaOPESFarOm0l6GKp6eo8ZDCzi', '+1 555-0104', 1, 0)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 7. CHART OF ACCOUNTS
INSERT INTO `chart_of_accounts` (`id`, `company_id`, `code`, `name`, `type`, `parent_id`, `is_active`) VALUES
-- Assets
(1, 1, '1000', 'Current Assets', 'asset', NULL, 1),
(2, 1, '1010', 'Cash in Hand', 'asset', 1, 1),
(3, 1, '1020', 'Operating Bank Account (Chase)', 'asset', 1, 1),
(4, 1, '1030', 'Accounts Receivable (Customers)', 'asset', 1, 1),
(5, 1, '1040', 'Inventory / Stock Asset', 'asset', 1, 1),
(6, 1, '1500', 'Fixed Assets', 'asset', NULL, 1),
(7, 1, '1510', 'Office Equipment & Computers', 'asset', 6, 1),
-- Liabilities
(8, 1, '2000', 'Current Liabilities', 'liability', NULL, 1),
(9, 1, '2010', 'Accounts Payable (Suppliers)', 'liability', 8, 1),
(10, 1, '2020', 'Salaries & Wages Payable', 'liability', 8, 1),
(11, 1, '2030', 'Sales Tax Payable', 'liability', 8, 1),
-- Equity
(12, 1, '3000', 'Equity', 'equity', NULL, 1),
(13, 1, '3010', 'Owner Capital Investment', 'equity', 12, 1),
(14, 1, '3020', 'Retained Earnings', 'equity', 12, 1),
-- Income
(15, 1, '4000', 'Operating Revenue', 'income', NULL, 1),
(16, 1, '4010', 'Sales Revenue', 'income', 15, 1),
(17, 1, '4020', 'Sales Discounts Allowed', 'income', 15, 1),
(18, 1, '4030', 'Other Operating Income', 'income', 15, 1),
-- Expenses
(19, 1, '5000', 'Direct Expenses', 'expense', NULL, 1),
(20, 1, '5010', 'Cost of Goods Sold (COGS)', 'expense', 19, 1),
(21, 1, '5100', 'Administrative & Operating Expenses', 'expense', NULL, 1),
(22, 1, '5110', 'Salaries & Wages Expense', 'expense', 21, 1),
(23, 1, '5120', 'Office Rent Expense', 'expense', 21, 1),
(24, 1, '5130', 'Electricity & Utilities Expense', 'expense', 21, 1),
(25, 1, '5140', 'Office Supplies Expense', 'expense', 21, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 8. FINANCIAL PERIOD
INSERT INTO `financial_periods` (`id`, `company_id`, `name`, `start_date`, `end_date`, `status`)
VALUES (1, 1, 'Fiscal Year 2026', '2026-01-01', '2026-12-31', 'open')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 9. CATEGORIES
INSERT INTO `categories` (`id`, `company_id`, `code`, `name`, `description`, `is_active`) VALUES
(1, 1, 'CAT01', 'Electronics & Hardware', 'Computers, displays, cables and peripherals', 1),
(2, 1, 'CAT02', 'Office Supplies', 'Stationery, paper, pens and desk accessories', 1),
(3, 1, 'CAT03', 'Office Furniture', 'Ergonomic chairs, desks and storage cabinets', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 10. UNITS
INSERT INTO `units` (`id`, `company_id`, `name`, `symbol`, `is_active`) VALUES
(1, 1, 'Pieces', 'PCS', 1),
(2, 1, 'Box', 'BOX', 1),
(3, 1, 'Kilogram', 'KG', 1),
(4, 1, 'Pack', 'PK', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 11. WAREHOUSES
INSERT INTO `warehouses` (`id`, `company_id`, `branch_id`, `code`, `name`, `location`, `is_active`) VALUES
(1, 1, 1, 'WH-MAIN', 'Central Main Warehouse', 'Building A, Industrial Park, NY', 1),
(2, 1, 1, 'WH-RETAIL', 'Downtown Retail Depot', '45 Broadway Avenue, NY', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 12. PRODUCTS (10 products)
INSERT INTO `products` (`id`, `company_id`, `code`, `name`, `category_id`, `unit_id`, `cost_price`, `sale_price`, `reorder_level`, `current_stock`, `description`, `is_active`) VALUES
(1, 1, 'PRD-001', 'Dell Latitude 5420 Laptop i7 16GB', 1, 1, 650.0000, 899.0000, 5.00, 15.00, 'Enterprise business laptop with 16GB RAM and 512GB SSD', 1),
(2, 1, 'PRD-002', 'Logitech MX Master 3S Mouse', 1, 1, 55.0000, 99.0000, 10.00, 40.00, 'Wireless precision mouse with quiet clicks', 1),
(3, 1, 'PRD-003', 'Keychron K2 Mechanical Keyboard', 1, 1, 60.0000, 95.0000, 8.00, 25.00, 'Wireless 75% compact mechanical keyboard', 1),
(4, 1, 'PRD-004', 'Dell UltraSharp 27-inch 4K Monitor', 1, 1, 240.0000, 380.0000, 4.00, 12.00, 'IPS 4K monitor with USB-C Hub', 1),
(5, 1, 'PRD-005', 'Herman Miller Sayl Ergonomic Chair', 3, 1, 380.0000, 590.0000, 3.00, 8.00, 'Ergonomic task chair with adjustable lumbar support', 1),
(6, 1, 'PRD-006', 'Dual-Motor Electric Standing Desk 140cm', 3, 1, 210.0000, 350.0000, 3.00, 6.00, 'Height-adjustable standing desk with preset memory', 1),
(7, 1, 'PRD-007', 'Multipurpose A4 Copy Paper 80gsm (Box of 5 Reams)', 2, 2, 18.0000, 32.0000, 20.00, 65.00, 'High-white acid-free office paper box', 1),
(8, 1, 'PRD-008', 'Pilot G2 0.7mm Black Gel Pens (Pack of 12)', 2, 4, 8.5000, 16.0000, 15.00, 50.00, 'Smooth refillable dynamic gel roller pens', 1),
(9, 1, 'PRD-009', 'Anker 10-in-1 USB-C Docking Station', 1, 1, 50.0000, 89.0000, 8.00, 20.00, 'Universal triple display USB-C docking hub', 1),
(10, 1, 'PRD-010', 'Cat6 Snagless Gigabit Ethernet Cable 15m', 1, 1, 4.5000, 12.0000, 15.00, 80.00, 'RJ45 high speed network patch cable', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 13. SUPPLIERS (5 suppliers)
INSERT INTO `suppliers` (`id`, `company_id`, `code`, `name`, `email`, `phone`, `address`, `tax_number`, `opening_balance`, `current_balance`, `is_active`) VALUES
(1, 1, 'SUP-001', 'TechGlobal Distribution Inc', 'sales@techglobal.com', '+1 (555) 201-1122', '450 Silicon Blvd, San Jose, CA', 'US-948172635', 0.0000, 0.0000, 1),
(2, 1, 'SUP-002', 'OfficeSmart Wholesale Supplies', 'orders@officesmart.com', '+1 (555) 302-2233', '88 Paper Mill Rd, Scranton, PA', 'US-102938475', 0.0000, 0.0000, 1),
(3, 1, 'SUP-003', 'ErgoPro Furniture Manufacturing', 'contact@ergopro.com', '+1 (555) 403-3344', '1200 Craft Way, Grand Rapids, MI', 'US-564738291', 0.0000, 0.0000, 1),
(4, 1, 'SUP-004', 'Nexus Peripherals Supply Co', 'info@nexusperipherals.com', '+1 (555) 504-4455', '300 Tech Park, Austin, TX', 'US-778899001', 0.0000, 0.0000, 1),
(5, 1, 'SUP-005', 'PrimeCable Network Logistics', 'logistics@primecable.com', '+1 (555) 605-5566', '77 Fiber Drive, Atlanta, GA', 'US-334455667', 0.0000, 0.0000, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 14. CUSTOMERS (5 customers)
INSERT INTO `customers` (`id`, `company_id`, `code`, `name`, `email`, `phone`, `address`, `tax_number`, `credit_limit`, `opening_balance`, `current_balance`, `is_active`) VALUES
(1, 1, 'CUST-001', 'Acorn Cloud Solutions LLC', 'procurement@acorncloud.io', '+1 (555) 701-9901', '101 Innovation Way, Seattle, WA', 'CUST-TAX-101', 50000.0000, 0.0000, 0.0000, 1),
(2, 1, 'CUST-002', 'Blue Horizon Media Partners', 'finance@bluehorizon.com', '+1 (555) 702-9902', '55 Sunset Blvd, Los Angeles, CA', 'CUST-TAX-102', 30000.0000, 0.0000, 0.0000, 1),
(3, 1, 'CUST-003', 'Summit Healthcare Systems', 'accounting@summithealth.org', '+1 (555) 703-9903', '200 Medical Center Dr, Boston, MA', 'CUST-TAX-103', 75000.0000, 0.0000, 0.0000, 1),
(4, 1, 'CUST-004', 'Vanguard Legal Partners LLP', 'admin@vanguardlaw.com', '+1 (555) 704-9904', '50 Wall Street, New York, NY', 'CUST-TAX-104', 40000.0000, 0.0000, 0.0000, 1),
(5, 1, 'CUST-005', 'Pioneer Logistics & Freight', 'ops@pioneerfreight.com', '+1 (555) 705-9905', '800 Interstate Pkwy, Chicago, IL', 'CUST-TAX-105', 25000.0000, 0.0000, 0.0000, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 15. DEPARTMENTS & DESIGNATIONS
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `is_active`) VALUES
(1, 1, 'Information Technology & Engineering', 'DEP-IT', 1),
(2, 1, 'Finance & Accounting', 'DEP-ACC', 1),
(3, 1, 'Operations & Logistics', 'DEP-OPS', 1),
(4, 1, 'Sales & Business Development', 'DEP-SALES', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `designations` (`id`, `company_id`, `name`, `code`, `is_active`) VALUES
(1, 1, 'Principal Software Architect', 'DES-01', 1),
(2, 1, 'Senior Financial Controller', 'DES-02', 1),
(3, 1, 'Supply Chain Specialist', 'DES-03', 1),
(4, 1, 'Key Account Director', 'DES-04', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 16. EMPLOYEES
INSERT INTO `employees` (`id`, `company_id`, `branch_id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department_id`, `designation_id`, `joining_date`, `basic_salary`, `house_rent`, `medical_allowance`, `other_allowance`, `bank_account`, `is_active`) VALUES
(1, 1, 1, 'EMP-001', 'Ethan', 'Hunt', 'ethan.hunt@apexenterprises.com', '+1 (555) 880-1001', 1, 1, '2024-01-15', 5500.0000, 1200.0000, 400.0000, 200.0000, 'CHASE-CHECKING-9911', 1),
(2, 1, 1, 'EMP-002', 'Sarah', 'Connor', 'sarah.connor@apexenterprises.com', '+1 (555) 880-1002', 2, 2, '2024-03-01', 4800.0000, 1000.0000, 350.0000, 150.0000, 'BOA-CHECKING-8822', 1),
(3, 1, 1, 'EMP-003', 'Marcus', 'Brody', 'marcus.brody@apexenterprises.com', '+1 (555) 880-1003', 3, 3, '2024-06-10', 3600.0000, 800.0000, 250.0000, 100.0000, 'WF-CHECKING-7733', 1)
ON DUPLICATE KEY UPDATE `first_name`=VALUES(`first_name`);

-- 17. INITIAL STOCK BALANCES & STOCK LEDGER
INSERT INTO `stock_balances` (`company_id`, `warehouse_id`, `product_id`, `quantity`, `reserved_qty`, `avg_unit_cost`) VALUES
(1, 1, 1, 15.00, 0.00, 650.0000),
(1, 1, 2, 40.00, 0.00, 55.0000),
(1, 1, 3, 25.00, 0.00, 60.0000),
(1, 1, 4, 12.00, 0.00, 240.0000),
(1, 1, 5, 8.00, 0.00, 380.0000),
(1, 1, 6, 6.00, 0.00, 210.0000),
(1, 1, 7, 65.00, 0.00, 18.0000),
(1, 1, 8, 50.00, 0.00, 8.5000),
(1, 1, 9, 20.00, 0.00, 50.0000),
(1, 1, 10, 80.00, 0.00, 4.5000)
ON DUPLICATE KEY UPDATE `quantity`=VALUES(`quantity`);

-- Insert Initial Stock Ledger records
INSERT INTO `stock_ledger` (`company_id`, `branch_id`, `warehouse_id`, `product_id`, `date`, `reference_type`, `reference_id`, `qty_in`, `qty_out`, `unit_cost`, `total_cost`, `remarks`) VALUES
(1, 1, 1, 1, '2026-01-01', 'ADJUSTMENT', 1, 15.00, 0.00, 650.0000, 9750.0000, 'Opening inventory balance'),
(1, 1, 1, 2, '2026-01-01', 'ADJUSTMENT', 1, 40.00, 0.00, 55.0000, 2200.0000, 'Opening inventory balance'),
(1, 1, 1, 3, '2026-01-01', 'ADJUSTMENT', 1, 25.00, 0.00, 60.0000, 1500.0000, 'Opening inventory balance'),
(1, 1, 1, 4, '2026-01-01', 'ADJUSTMENT', 1, 12.00, 0.00, 240.0000, 2880.0000, 'Opening inventory balance'),
(1, 1, 1, 5, '2026-01-01', 'ADJUSTMENT', 1, 8.00, 0.00, 380.0000, 3040.0000, 'Opening inventory balance'),
(1, 1, 1, 6, '2026-01-01', 'ADJUSTMENT', 1, 6.00, 0.00, 210.0000, 1260.0000, 'Opening inventory balance'),
(1, 1, 1, 7, '2026-01-01', 'ADJUSTMENT', 1, 65.00, 0.00, 18.0000, 1170.0000, 'Opening inventory balance'),
(1, 1, 1, 8, '2026-01-01', 'ADJUSTMENT', 1, 50.00, 0.00, 8.5000, 425.0000, 'Opening inventory balance'),
(1, 1, 1, 9, '2026-01-01', 'ADJUSTMENT', 1, 20.00, 0.00, 50.0000, 1000.0000, 'Opening inventory balance'),
(1, 1, 1, 10, '2026-01-01', 'ADJUSTMENT', 1, 80.00, 0.00, 4.5000, 360.0000, 'Opening inventory balance');

-- 18. OPENING JOURNAL VOUCHER (To balance inventory asset with owner equity)
-- Total initial stock = 9750 + 2200 + 1500 + 2880 + 3040 + 1260 + 1170 + 425 + 1000 + 360 = 23585
-- Plus Initial Cash in Bank $50,000 against Owner Capital $73,585
INSERT INTO `vouchers` (`id`, `company_id`, `branch_id`, `voucher_number`, `voucher_type`, `date`, `narration`, `total_debit`, `total_credit`, `status`, `created_by`, `posted_by`, `posted_at`)
VALUES
(1, 1, 1, 'VR-2026-00001', 'journal', '2026-01-01', 'Opening balances for inventory stock and cash capital', 73585.0000, 73585.0000, 'posted', 1, 1, '2026-01-01 09:00:00')
ON DUPLICATE KEY UPDATE `voucher_number`=VALUES(`voucher_number`);

INSERT INTO `voucher_lines` (`voucher_id`, `account_id`, `party_type`, `party_id`, `debit`, `credit`, `description`) VALUES
(1, 5, 'none', NULL, 23585.0000, 0.0000, 'Opening inventory stock on hand'),
(1, 3, 'none', NULL, 50000.0000, 0.0000, 'Initial operating bank deposit'),
(1, 13, 'none', NULL, 0.0000, 73585.0000, 'Owner initial equity capital');

-- 19. DOCUMENT SEQUENCES
INSERT INTO `document_sequences` (`company_id`, `document_type`, `year`, `last_number`, `prefix`) VALUES
(1, 'PR', 2026, 1, 'PR'),
(1, 'PO', 2026, 1, 'PO'),
(1, 'GRN', 2026, 1, 'GRN'),
(1, 'SO', 2026, 1, 'SO'),
(1, 'DEL', 2026, 1, 'DEL'),
(1, 'INV', 2026, 1, 'INV'),
(1, 'MR', 2026, 1, 'MR'),
(1, 'VR', 2026, 1, 'VR'),
(1, 'ST', 2026, 0, 'ST'),
(1, 'PRT', 2026, 0, 'PRT'),
(1, 'SRT', 2026, 0, 'SRT'),
(1, 'PAY', 2026, 0, 'PAY')
ON DUPLICATE KEY UPDATE `last_number`=VALUES(`last_number`);

-- 20. SETTINGS
INSERT INTO `settings` (`company_id`, `key_name`, `key_value`) VALUES
(1, 'company_name', 'Apex Enterprises Ltd'),
(1, 'currency_code', 'USD'),
(1, 'currency_symbol', '$'),
(1, 'tax_rate_default', '0.00'),
(1, 'allow_negative_stock', '0'),
(1, 'auto_post_vouchers', '1')
ON DUPLICATE KEY UPDATE `key_value`=VALUES(`key_value`);

SET FOREIGN_KEY_CHECKS = 1;
