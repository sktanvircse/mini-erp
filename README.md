# 🏢 Mini ERP - Full-Stack Enterprise Resource Planning System

[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Angular](https://img.shields.io/badge/Angular-22%20Standalone-DD0031?logo=angular&logoColor=white)](https://angular.dev/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Database](https://img.shields.io/badge/MariaDB%20%2F%20MySQL-InnoDB-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A complete, production-grade **Mini ERP (Enterprise Resource Planning)** system built from scratch without bloated third-party frameworks. Featuring a high-performance **custom PHP 8.5+ MVC backend** and a modern **Angular 22 standalone frontend** styled with **Tailwind CSS**.

---

## 🌟 Core Modules & Capabilities

### 1. 👥 Multi-Company & RBAC Administration
- **Multi-Tenant Ready**: Multi-company and multi-branch database architecture.
- **Role-Based Access Control (RBAC)**: Fine-grained permission matrix with 36 distinct operational capabilities.
- **Interactive Permissions Matrix**: Visual permission editor with instant sync and live module-level active counters.
- **JWT Authentication**: Secure stateless authentication with automatic refresh and route guards.
- **Audit Logging**: Comprehensive system activity and audit trail for all business-critical actions.

### 2. 🗄️ Master Data Management
- **Products & Items**: SKU, barcode, unit pricing, cost tracking, reorder alert levels, and category classification.
- **Suppliers & Vendors**: Contact details, tax identifiers, opening balances, and real-time ledger tracking.
- **Customers & Clients**: Credit limits, contact registry, balance tracking, and customer statements.
- **Warehouses & Depots**: Multi-warehouse support with bin locations and inter-warehouse logistics.
- **Categories & Units**: Hierarchical product categories and measurement units (PCS, BOX, KG, etc.).

### 3. 📦 Inventory & Stock Control
- **Strict Negative Inventory Protection**: Database transactions roll back automatically if stock would drop below zero.
- **Stock Ledger Movement Tracking**: Complete audit history for every inventory inflow and outflow (GRN, Delivery, Transfers, Returns).
- **Inter-Warehouse Transfers**: Two-step warehouse transfer workflows with approval tracking.
- **Low Stock Alerts**: Real-time identification of products breaching minimum threshold levels.

### 4. 🛒 Procurement & Purchase
- **Purchase Requisitions**: Internal department requests with manager approval stages.
- **Purchase Orders (PO)**: Supplier order generation with multi-item tax calculation and tracking.
- **Goods Receipt Notes (GRN)**: Receiving validation against PO quantities, automatic physical stock increment, and automatic generation of balanced Accounts Payable GL vouchers.
- **Purchase Returns**: Processing damaged or rejected goods with automatic stock deduction and credit note voucher adjustments.

### 5. 💰 Sales & Distribution
- **Sales Orders (SO)**: Multi-item quotations, customer orders, and confirmation workflows.
- **Deliveries (Delivery Orders)**: Warehouse dispatch management with automatic physical stock deductions.
- **Sales Invoicing**: Tax invoices with status tracking (`unpaid`, `partially_paid`, `paid`).
- **Money Receipts**: Customer payment recording (Wire, Cheque, Cash) with multiple invoice allocation.
- **Sales Returns**: Customer return processing with warehouse stock return and GL credit note generation.

### 6. ⚖️ Accounts & Financial Accounting (Double-Entry GL)
- **Standard Chart of Accounts (COA)**: Five-tier structure (Assets, Liabilities, Equity, Revenue, Expenses).
- **Double-Entry Enforcement**: Every transaction mandates that $\sum \text{Debit} = \sum \text{Credit}$.
- **Financial Periods**: Fiscal year and month control with period opening and closing locking.
- **Voucher Immutability**: Posted vouchers cannot be edited or deleted, ensuring strict regulatory compliance.
- **Automated GL Integration**: Operational actions (GRN, Deliveries, Invoices, Receipts, Payroll) automatically generate balanced GL vouchers.

### 7. 📊 Financial & Operational Reports
- **Trial Balance**: Instant verification of debit-credit equality with difference checks.
- **Balance Sheet (Statement of Financial Position)**: Real-time Assets = Liabilities + Equity calculation.
- **Profit & Loss (Income Statement)**: Revenue vs Operating Expenses and COGS breakdown.
- **General Ledger**: Statement for any individual COA account with opening and running balances.
- **Customer Ledger**: Statement of account showing all invoices, payments, and balances due.
- **Supplier Ledger**: Vendor statements showing purchases, receipts, and payable balances.
- **Cash & Bank Book**: Detailed cash in hand and operating bank account transactions.

### 8. 👨‍💼 HR & Payroll
- **Employee Management**: Comprehensive records with department, designation, salary structures, and allowances.
- **Daily Attendance**: Track check-in, check-out, status (`present`, `absent`, `late`, `half_day`, `on_leave`).
- **Leave Management**: Leave requests with manager approval workflows.
- **Monthly Payroll Processing**: Automated batch salary calculation, deductions, and posting to accounting.

---

## 🛠️ Architecture & Tech Stack

```
mini_erp/
├── backend/                  # PHP 8.5+ Custom MVC Framework
│   ├── public/               # Public webroot (index.php, .htaccess)
│   ├── src/
│   │   ├── Core/             # Router, Database, Request, Response, JWT, Validator, AuditLogger
│   │   ├── Middleware/       # AuthMiddleware, PermissionMiddleware, CorsMiddleware, RateLimit
│   │   ├── Controllers/      # 28 Modular Business Controllers
│   │   └── Models/           # Data Models & Repositories
│   ├── tests/                # Automated Business Logic Test Suite (13 tests)
│   └── logs/                 # Application error & audit logs
│
├── frontend/                 # Angular 22 Standalone Architecture
│   ├── src/
│   │   ├── app/
│   │   │   ├── core/         # ApiService, AuthService, Guards, Interceptors, Models
│   │   │   ├── layout/       # Shell, Responsive Sidebar, Header, Breadcrumbs
│   │   │   ├── shared/       # DataTable, PageHeader, StatusBadge, Toast
│   │   │   └── features/     # Master Data, Purchase, Sales, Inventory, Accounts, HR, Reports, Admin
│   │   └── styles.css        # Tailwind CSS styling & custom scrollbars
│
└── database/                 # MariaDB / MySQL Schemas & Seed Data
    ├── schema.sql            # Complete 48-table relational schema
    ├── seed.sql              # Master organization & COA seed
    ├── seed_transactions.sql # Comprehensive realistic business transaction seed
    └── run_seeds.php         # Automated CLI migration & seeding runner
```

---

## 🚀 Quick Start Guide

### Prerequisites
- **PHP**: 8.2 or higher (recommended PHP 8.5+) with `pdo_mysql` enabled.
- **Database**: MariaDB 10.4+ or MySQL 8.0+.
- **Node.js**: v18.0.0 or higher.
- **Composer** (optional) / NPM.

---

### Step 1: Clone the Repository
```bash
git clone https://github.com/<your-username>/mini-erp.git
cd mini-erp
```

---

### Step 2: Database Setup & Seeding
1. Start your MySQL/MariaDB server (e.g. via XAMPP or native service).
2. Create the database:
   ```sql
   CREATE DATABASE mini_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Run the automated seed runner to load the schema and realistic demo data:
   ```bash
   php database/run_seeds.php --reset
   ```
   *This automatically builds all 48 tables and inserts demo transactions with balanced accounting vouchers.*

---

### Step 3: Backend Configuration & Startup
1. Copy the environment configuration:
   ```bash
   cd backend
   cp .env.example .env
   ```
2. Verify database credentials in `backend/.env`:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mini_erp
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Start the built-in PHP development server:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
   *The backend API is now running at `http://127.0.0.1:8000/api`.*

---

### Step 4: Run the Backend Automated Test Suite
Verify that all core business logic rules pass:
```bash
php backend/tests/run_tests.php
```
```
========================================================
       MINI ERP AUTOMATED BUSINESS LOGIC TEST SUITE      
========================================================
 [PASS] Admin user exists in database
 [PASS] Admin user password verifies with Admin@123
 [PASS] JWT token encodes and decodes accurately
 [PASS] Storekeeper has 'purchase.grn' permission
 [PASS] Storekeeper does NOT have 'roles.manage' permission
 [PASS] Permission denied accurately identified for unauthorized role
 [PASS] GRN increases stock_balances correctly
 [PASS] GRN accounting voucher balances debit == credit
 [PASS] Delivery successfully reduces physical stock
 [PASS] System strictly blocks negative inventory and rolls back
 [PASS] Unbalanced voucher (Debit != Credit) is strictly rejected
 [PASS] Posted voucher exists in system
 [PASS] Editing or deleting a posted voucher is strictly prohibited
========================================================
 TEST SUITE SUMMARY: 13 PASSED, 0 FAILED
========================================================
```

---

### Step 5: Frontend Setup & Startup
1. Open a new terminal and navigate to the frontend directory:
   ```bash
   cd frontend
   npm install
   ```
2. Start the Angular development server:
   ```bash
   npm start
   ```
3. Open your browser and navigate to:
   ```
   http://localhost:4200
   ```

---

## 🔐 Default Demo Accounts

All demo accounts share the password: **`Admin@123`**

| Role | Email | Capabilities |
|:---|:---|:---|
| **System Administrator** | `admin@minierp.com` | Complete access to all 8 modules & system settings |
| **Operations Manager** | `manager@minierp.com` | PO & PR approvals, deliveries, and employee management |
| **Chief Accountant** | `accountant@minierp.com` | Vouchers, GL, financial reports, payroll, and receipts |
| **Head Storekeeper** | `store@minierp.com` | Warehouse stock, GRN receiving, transfers, deliveries |
| **Sales Executive** | `sales@minierp.com` | Customer management, quotations, and sales orders |

---

## 📄 License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
