// ─── Core Entities ──────────────────────────────────────────────────────────

export interface User {
  id: number;
  name: string;
  email: string;
  role_id: number;
  role_name: string;
  branch_id: number;
  branch_name: string;
  is_active: number;
  permissions: string[];
}

export interface Role {
  id: number;
  name: string;
  description: string;
  permissions: string[];
}

export interface Permission {
  key: string;
  label: string;
  module: string;
}

export interface Branch {
  id: number;
  company_id: number;
  name: string;
  code: string;
  address: string;
  phone: string;
  is_active: number;
}

export interface Company {
  id: number;
  name: string;
  code: string;
  address: string;
  phone: string;
  email: string;
  currency: string;
  logo_url: string;
}

// ─── Master ─────────────────────────────────────────────────────────────────

export interface Category { id: number; name: string; description: string; }
export interface Unit     { id: number; name: string; symbol: string; }
export interface Warehouse { id: number; branch_id: number; name: string; code: string; location: string; is_active: number; }

export interface Product {
  id: number;
  code: string;
  name: string;
  category_id: number; category_name: string;
  unit_id: number; unit_name: string;
  purchase_price: number;
  sale_price: number;
  reorder_level: number;
  is_active: number;
  description: string;
}

export interface Supplier {
  id: number;
  name: string;
  code: string;
  contact_person: string;
  phone: string;
  email: string;
  address: string;
  credit_limit: number;
  credit_days: number;
  opening_balance: number;
  current_balance: number;
  is_active: number;
}

export interface Customer {
  id: number;
  name: string;
  code: string;
  contact_person: string;
  phone: string;
  email: string;
  address: string;
  credit_limit: number;
  credit_days: number;
  opening_balance: number;
  current_balance: number;
  is_active: number;
}

// ─── Inventory ───────────────────────────────────────────────────────────────

export interface StockBalance {
  product_id: number;
  product_code: string;
  product_name: string;
  warehouse_id: number;
  warehouse_name: string;
  quantity: number;
  reorder_level: number;
}

export interface StockLedgerEntry {
  id: number;
  product_id: number;
  product_name: string;
  warehouse_id: number;
  warehouse_name: string;
  transaction_type: string;
  reference_type: string;
  reference_id: number;
  reference_number: string;
  quantity_in: number;
  quantity_out: number;
  balance: number;
  unit_cost: number;
  created_at: string;
}

export interface StockTransfer {
  id: number;
  transfer_number: string;
  from_warehouse_id: number; from_warehouse_name: string;
  to_warehouse_id: number;   to_warehouse_name: string;
  transfer_date: string;
  status: string;
  notes: string;
  items: StockTransferItem[];
  created_at: string;
}
export interface StockTransferItem {
  product_id: number; product_name: string; product_code: string;
  quantity: number;
}

// ─── Purchase ────────────────────────────────────────────────────────────────

export interface PurchaseRequisition {
  id: number;
  pr_number: string;
  branch_id: number; branch_name: string;
  requested_by: number; requested_by_name: string;
  request_date: string;
  required_date: string;
  status: 'draft'|'submitted'|'approved'|'rejected'|'closed';
  notes: string;
  items: PRItem[];
  created_at: string;
}
export interface PRItem {
  product_id: number; product_name: string;
  quantity: number; unit_name: string; estimated_price: number; notes: string;
}

export interface PurchaseOrder {
  id: number;
  po_number: string;
  supplier_id: number; supplier_name: string;
  branch_id: number; branch_name: string;
  order_date: string;
  expected_date: string;
  status: 'draft'|'approved'|'partial'|'received'|'cancelled';
  subtotal: number; tax_amount: number; discount_amount: number; total_amount: number;
  notes: string;
  items: POItem[];
  created_at: string;
}
export interface POItem {
  id: number; product_id: number; product_name: string;
  quantity: number; unit_price: number; tax_rate: number; discount: number; total_price: number;
  received_qty: number; unit_name: string;
}

export interface GoodsReceipt {
  id: number;
  grn_number: string;
  po_id: number; po_number: string;
  supplier_id: number; supplier_name: string;
  warehouse_id: number; warehouse_name: string;
  receipt_date: string;
  status: string;
  total_amount: number;
  notes: string;
  items: GRNItem[];
  created_at: string;
}
export interface GRNItem {
  product_id: number; product_name: string;
  quantity_ordered: number; quantity_received: number;
  unit_price: number; total_price: number; unit_name: string;
}

export interface PurchaseReturn {
  id: number;
  return_number: string;
  grn_id: number; grn_number: string;
  supplier_id: number; supplier_name: string;
  warehouse_id: number; warehouse_name: string;
  return_date: string;
  total_amount: number;
  reason: string;
  status: string;
  items: PurchaseReturnItem[];
  created_at: string;
}
export interface PurchaseReturnItem {
  product_id: number; product_name: string;
  quantity: number; unit_price: number; total_price: number;
}

// ─── Sales ────────────────────────────────────────────────────────────────────

export interface SalesOrder {
  id: number;
  so_number: string;
  customer_id: number; customer_name: string;
  branch_id: number; branch_name: string;
  order_date: string;
  delivery_date: string;
  status: 'draft'|'confirmed'|'partial'|'delivered'|'cancelled';
  subtotal: number; tax_amount: number; discount_amount: number; total_amount: number;
  notes: string;
  items: SOItem[];
  created_at: string;
}
export interface SOItem {
  id: number; product_id: number; product_name: string;
  quantity: number; unit_price: number; tax_rate: number; discount: number; total_price: number;
  delivered_qty: number; unit_name: string;
}

export interface Delivery {
  id: number;
  delivery_number: string;
  so_id: number; so_number: string;
  customer_id: number; customer_name: string;
  warehouse_id: number; warehouse_name: string;
  delivery_date: string;
  status: string;
  notes: string;
  items: DeliveryItem[];
  created_at: string;
}
export interface DeliveryItem {
  product_id: number; product_name: string;
  quantity_ordered: number; quantity_delivered: number;
  unit_name: string;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  so_id: number; so_number: string;
  customer_id: number; customer_name: string;
  invoice_date: string;
  due_date: string;
  status: 'draft'|'posted'|'partial'|'paid'|'cancelled';
  subtotal: number; tax_amount: number; discount_amount: number;
  total_amount: number; paid_amount: number; balance_due: number;
  notes: string;
  items: InvoiceItem[];
  created_at: string;
}
export interface InvoiceItem {
  product_id: number; product_name: string;
  quantity: number; unit_price: number; tax_rate: number; discount: number; total_price: number;
}

export interface MoneyReceipt {
  id: number;
  receipt_number: string;
  customer_id: number; customer_name: string;
  receipt_date: string;
  amount: number;
  payment_method: string;
  reference_number: string;
  bank_account_id: number;
  notes: string;
  allocations: ReceiptAllocation[];
  created_at: string;
}
export interface ReceiptAllocation {
  invoice_id: number; invoice_number: string;
  allocated_amount: number;
}

export interface SalesReturn {
  id: number;
  return_number: string;
  invoice_id: number; invoice_number: string;
  customer_id: number; customer_name: string;
  warehouse_id: number; warehouse_name: string;
  return_date: string;
  total_amount: number;
  reason: string;
  status: string;
  items: SalesReturnItem[];
  created_at: string;
}
export interface SalesReturnItem {
  product_id: number; product_name: string;
  quantity: number; unit_price: number; total_price: number;
}

// ─── Accounts ────────────────────────────────────────────────────────────────

export interface Account {
  id: number;
  code: string;
  name: string;
  account_type: 'asset'|'liability'|'equity'|'income'|'expense';
  parent_id: number | null;
  is_active: number;
  opening_balance: number;
  balance: number;
  children?: Account[];
  level?: number;
}

export interface Period {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  status: 'open'|'closed';
}

export interface Voucher {
  id: number;
  voucher_number: string;
  voucher_type: 'JV'|'PV'|'RV'|'CV';
  voucher_date: string;
  period_id: number; period_name: string;
  reference: string;
  narration: string;
  status: 'draft'|'posted';
  total_debit: number; total_credit: number;
  lines: VoucherLine[];
  created_at: string;
}
export interface VoucherLine {
  id: number;
  account_id: number; account_name: string; account_code: string;
  debit: number; credit: number; narration: string;
}

// ─── HR ───────────────────────────────────────────────────────────────────────

export interface Department { id: number; name: string; description: string; }
export interface Designation { id: number; department_id: number; department_name: string; name: string; }

export interface Employee {
  id: number;
  employee_code: string;
  name: string;
  email: string;
  phone: string;
  department_id: number; department_name: string;
  designation_id: number; designation_name: string;
  branch_id: number; branch_name: string;
  join_date: string;
  basic_salary: number;
  status: 'active'|'inactive'|'terminated';
}

export interface AttendanceRecord {
  id: number;
  employee_id: number; employee_name: string;
  date: string;
  status: 'present'|'absent'|'half_day'|'leave';
  in_time: string; out_time: string;
  overtime_hours: number;
  notes: string;
}

export interface LeaveRequest {
  id: number;
  employee_id: number; employee_name: string;
  leave_type: string;
  from_date: string; to_date: string;
  days: number;
  reason: string;
  status: 'pending'|'approved'|'rejected';
  created_at: string;
}

export interface Payroll {
  id: number;
  payroll_number: string;
  employee_id: number; employee_name: string;
  period_id: number; month: number; year: number;
  basic_salary: number;
  total_days: number; present_days: number; absent_days: number; half_days: number; leave_days: number;
  overtime_hours: number; overtime_amount: number;
  gross_salary: number;
  deductions: number; net_salary: number;
  status: 'draft'|'approved'|'paid';
  created_at: string;
}

// ─── Reports ─────────────────────────────────────────────────────────────────

export interface TrialBalanceRow {
  account_id: number; account_code: string; account_name: string;
  account_type: string;
  opening_debit: number; opening_credit: number;
  period_debit: number; period_credit: number;
  closing_debit: number; closing_credit: number;
}

export interface GeneralLedgerEntry {
  date: string; voucher_number: string; narration: string;
  debit: number; credit: number; balance: number;
}

export interface PnLRow { account_name: string; amount: number; level: number; }
export interface BalanceSheetSection { label: string; amount: number; level: number; }

// ─── Pagination ─────────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
}
