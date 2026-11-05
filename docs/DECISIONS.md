# Mini ERP Architectural & Design Decisions

This document records sensible defaults, technical decisions, and business logic considerations made throughout the development of the Mini ERP system.

## 1. Architecture & Tech Stack
- **Backend Architecture**: Vanilla PHP 8+ custom lightweight MVC architecture with clean PSR-4-like autoloader, PDO database singleton with prepared statements, custom regex-based fast router with route group and middleware support, structured controllers, reusable models, and centralized validation.
- **Security & Auth**: HS256 JWT tokens with 24-hour expiration, stored securely in browser localStorage, automatically attached via Angular HTTP Interceptor. Passwords hashed using bcrypt via `password_hash($password, PASSWORD_DEFAULT)`.
- **Database Engine**: MariaDB/MySQL 10.11+ using InnoDB tables with strict Foreign Key constraints, UTF8mb4 encoding, transaction wrapping (`PDO::beginTransaction`, `commit`, `rollBack`), and automatic audit logging.
- **Frontend Architecture**: Angular 19+ standalone components, Tailwind CSS styling, reactive forms with reusable field error handling, shared data table with pagination, sorting, search and CSV export, dynamic print layouts, and role-based navigation guards.

## 2. Business Logic Sensible Defaults
- **Multi-Tenancy & Scoping**: The ERP supports multi-company and multi-branch operations. User accounts are assigned to companies and branches. Requests carry company_id and branch_id context (with defaults set during login/session).
- **Document Auto-Numbering**: Format `<PREFIX>-<YYYY>-<00000>` (e.g. `PO-2026-00001`, `GRN-2026-00001`, `INV-2026-00001`, `VR-2026-00001`). Sequence resets per year and document type.
- **Inventory & Costing**: Moving Weighted Average Costing / standard cost with stock ledger as single source of truth (`stock_ledger`). `stock_balances` cache current quantities by product and warehouse in the same atomic database transaction. Disallow negative inventory by default.
- **Accounting Integration**: Automated double-entry vouchers created on:
  - GRN Approval: Debit Inventory Asset, Credit Accounts Payable (Supplier).
  - Invoice Approval: Debit Accounts Receivable (Customer), Credit Sales Revenue; AND Debit Cost of Goods Sold, Credit Inventory Asset.
  - Money Receipt Approval: Debit Bank / Cash Account, Credit Accounts Receivable (Customer).
  - Purchase Return: Debit Accounts Payable, Credit Inventory Asset.
  - Sales Return: Debit Sales Returns & Inventory, Credit Accounts Receivable & COGS.
  - Payroll Approval: Debit Salaries & Wages Expense, Credit Payroll Payable / Cash.
- **Double Entry Enforcement**: Total debit must exactly equal total credit before voucher can be saved or posted. Posted vouchers cannot be edited or deleted.
- **Soft Deletes**: Master entities (Products, Customers, Suppliers, Accounts, Employees) use `is_active` / `deleted_at` to maintain historical relational integrity with ledger entries and vouchers.
