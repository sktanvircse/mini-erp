# Mini ERP REST API Specification

Base URL: `http://localhost:8000/api` (Local PHP Built-in Server) or `http://localhost/mini_erp/backend/public/api` (XAMPP Apache)

All protected endpoints require the HTTP Header:
`Authorization: Bearer <JWT_TOKEN>`
Multi-company / branch context can be optionally passed:
`X-Company-Id: <COMPANY_ID>`
`X-Branch-Id: <BRANCH_ID>`

---

## 1. Authentication
### POST `/auth/login`
- **Rate Limited**: Yes (15 attempts/minute)
- **Request Body**:
```json
{
  "email": "admin@minierp.com",
  "password": "Admin@123"
}
```
- **Response (200 OK)**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "company_id": 1,
      "branch_id": 1,
      "name": "System Administrator",
      "email": "admin@minierp.com",
      "role_name": "Admin",
      "permissions": ["users.view", "users.manage", "master.view", "..."]
    }
  }
}
```

### GET `/auth/me`
- **Permission**: Authenticated
- **Response**: Current user profile, accessible companies, and branches.

### POST `/auth/change-password`
- **Permission**: Authenticated
- **Request Body**: `{"current_password": "...", "new_password": "..."}`

---

## 2. Admin & Security
- `GET /companies`: List companies
- `GET /companies/{id}`: Single company details
- `PUT /companies/{id}`: Update company information
- `GET /branches`: List company branches
- `POST /branches`: Create a branch
- `PUT /branches/{id}`: Update branch
- `DELETE /branches/{id}`: Delete branch
- `GET /roles`: List roles with user count
- `GET /roles/permissions`: List all available permissions grouped by module
- `POST /roles`: Create new role with `{"name": "...", "permission_ids": [1, 2, 3]}`
- `PUT /roles/{id}`: Update role and permissions
- `DELETE /roles/{id}`: Delete custom role
- `GET /users`: List users (search, role_id, is_active, pagination)
- `POST /users`: Create user `{"name": "...", "email": "...", "password": "...", "role_id": 1}`
- `PUT /users/{id}`: Update user
- `DELETE /users/{id}`: Soft delete user
- `GET /audit-logs`: Audit trail (filter by module, action, search)
- `GET /settings`: Key-value settings
- `PUT /settings`: Save key-value settings

---

## 3. Master Data
- `GET /categories`, `POST /categories`, `PUT /categories/{id}`, `DELETE /categories/{id}`
- `GET /units`, `POST /units`, `PUT /units/{id}`, `DELETE /units/{id}`
- `GET /warehouses`, `POST /warehouses`, `PUT /warehouses/{id}`, `DELETE /warehouses/{id}`
- `GET /products`: List products with current_stock, unit, category
- `POST /products`: Create product `{"code": "...", "name": "...", "category_id": 1, "unit_id": 1, "cost_price": 100, "sale_price": 150, "reorder_level": 5}`
- `PUT /products/{id}`: Update product
- `DELETE /products/{id}`: Soft delete product
- `GET /suppliers`, `POST /suppliers`, `PUT /suppliers/{id}`, `DELETE /suppliers/{id}`
- `GET /customers`, `POST /customers`, `PUT /customers/{id}`, `DELETE /customers/{id}`

---

## 4. Purchase Module
### Purchase Requisitions
- `GET /purchase-requisitions`
- `POST /purchase-requisitions`: Draft requisition with item lines
- `POST /purchase-requisitions/{id}/approve`: Approve requisition
- `POST /purchase-requisitions/{id}/reject`: Reject requisition

### Purchase Orders
- `GET /purchase-orders`
- `POST /purchase-orders`: Create PO directly or against `requisition_id`
- `POST /purchase-orders/{id}/approve`: Approve purchase order
- `POST /purchase-orders/{id}/cancel`: Cancel purchase order

### Goods Receipt (GRN)
- `GET /goods-receipts`
- `POST /goods-receipts`: Create and approve GRN.
  - Automatically increases `stock_balances`.
  - Automatically writes `stock_ledger` entries.
  - Automatically posts double-entry payable voucher (Debit Inventory Asset, Credit Accounts Payable).
  - Updates supplier balance.
  - Updates PO received quantity and status.

### Purchase Returns
- `GET /purchase-returns`
- `POST /purchase-returns`: Return goods to supplier.
  - Automatically decreases stock.
  - Reverses payable voucher.
  - Decreases supplier liability.

---

## 5. Inventory Module
- `GET /inventory/balances`: Current stock quantity and value per product & warehouse
- `GET /inventory/ledger`: Transactional movements (qty in, qty out, reference type, reference id, date)
- `GET /inventory/low-stock`: Products with stock <= reorder_level
- `GET /inventory/product-movements`: Summarized inflow and outflow by product
- `GET /stock-transfers`: List transfers
- `POST /stock-transfers`: Draft transfer between two warehouses
- `POST /stock-transfers/{id}/complete`: Atomically deducts source warehouse and credits destination warehouse

---

## 6. Sales Module
### Sales Orders
- `GET /sales-orders`
- `POST /sales-orders`: Create sales order
- `POST /sales-orders/{id}/confirm`: Confirm sales order

### Deliveries
- `GET /deliveries`
- `POST /deliveries`: Dispatch goods to customer.
  - Checks available warehouse stock (blocks negative inventory unless configured).
  - Deducts warehouse stock balance.
  - Writes `DELIVERY` in `stock_ledger`.
  - Updates order status to partially_delivered or delivered.

### Invoices
- `GET /invoices`
- `POST /invoices`: Create sales invoice from delivery or order.
  - Creates balanced Receivable Voucher (Debit Customer Receivable, Credit Sales Revenue).
  - Creates balanced COGS Voucher (Debit Cost of Goods Sold, Credit Inventory Asset).
  - Updates customer receivable balance.

### Money Receipts
- `GET /money-receipts`
- `POST /money-receipts`: Record payment received from customer.
  - Allocates payment across invoice(s).
  - Creates balanced Receipt Voucher (Debit Cash/Bank, Credit Customer Receivable).
  - Updates invoice status to paid or partially_paid.
  - Deducts customer receivable balance.

### Sales Returns
- `GET /sales-returns`
- `POST /sales-returns`: Customer return.
  - Adds stock back to warehouse balance.
  - Reverses income and customer receivable.

---

## 7. Accounts Module & Financial Reports
### Chart of Accounts & Vouchers
- `GET /accounts`: Hierarchical list of asset, liability, equity, income, and expense accounts
- `POST /accounts`: Add new account
- `GET /financial-periods`: Fiscal periods
- `POST /financial-periods/{id}/toggle`: Open or close accounting period
- `GET /vouchers`: List journal, payment, receipt, sales, and purchase vouchers
- `POST /vouchers`: Create balanced double-entry voucher (Total Debit MUST equal Total Credit)
- `POST /vouchers/{id}/post`: Post voucher to financial ledger

### Reports
- `GET /reports/trial-balance?as_of_date=YYYY-MM-DD`: Verifies Total Debit == Total Credit
- `GET /reports/general-ledger?account_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
- `GET /reports/customer-ledger?customer_id=X`
- `GET /reports/supplier-ledger?supplier_id=X`
- `GET /reports/profit-loss?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
- `GET /reports/balance-sheet?as_of_date=YYYY-MM-DD`: Total Assets == Total Liabilities + Equity
- `GET /reports/cash-bank-book?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`

---

## 8. HR & Payroll
- `GET /departments`, `POST /departments`, `PUT /departments/{id}`
- `GET /designations`, `POST /designations`, `PUT /designations/{id}`
- `GET /employees`, `POST /employees`, `PUT /employees/{id}`
- `GET /attendance`, `POST /attendance`, `POST /attendance/bulk`
- `GET /leave-requests`, `POST /leave-requests`, `POST /leave-requests/{id}/approve`
- `GET /payroll-batches`, `POST /payroll-batches/generate`
- `POST /payroll-batches/{id}/approve`: Posts salary expense voucher to accounts
