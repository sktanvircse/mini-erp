<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AttendanceController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\BranchController;
use App\Controllers\CategoryController;
use App\Controllers\CompanyController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\DeliveryController;
use App\Controllers\DepartmentController;
use App\Controllers\DesignationController;
use App\Controllers\EmployeeController;
use App\Controllers\GoodsReceiptController;
use App\Controllers\InvoiceController;
use App\Controllers\LeaveRequestController;
use App\Controllers\MoneyReceiptController;
use App\Controllers\PayrollController;
use App\Controllers\PeriodController;
use App\Controllers\ProductController;
use App\Controllers\PurchaseOrderController;
use App\Controllers\PurchaseRequisitionController;
use App\Controllers\PurchaseReturnController;
use App\Controllers\ReportController;
use App\Controllers\RoleController;
use App\Controllers\SalesOrderController;
use App\Controllers\SalesReturnController;
use App\Controllers\SettingsController;
use App\Controllers\StockController;
use App\Controllers\StockTransferController;
use App\Controllers\SupplierController;
use App\Controllers\UnitController;
use App\Controllers\UserController;
use App\Controllers\VoucherController;
use App\Controllers\WarehouseController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

return function (Router $router) {
    // Health Check
    $router->get('/api/health', function ($req) {
        \App\Core\Response::success(['status' => 'ok', 'app' => 'Mini ERP API', 'timestamp' => time()]);
    });

    // Public / Auth
    $router->post('/api/auth/login', [AuthController::class, 'login'], [RateLimitMiddleware::class]);

    // Protected Routes Group
    $router->group(['prefix' => '/api', 'middleware' => [AuthMiddleware::class]], function (Router $r) {
        // Auth profile & password
        $r->get('/auth/me', [AuthController::class, 'me']);
        $r->post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Dashboard
        $r->get('/dashboard', [DashboardController::class, 'summary']);
        $r->get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Companies & Branches
        $r->get('/companies', [CompanyController::class, 'index']);
        $r->get('/companies/{id}', [CompanyController::class, 'show']);
        $r->put('/companies/{id}', [CompanyController::class, 'update']);

        $r->get('/branches', [BranchController::class, 'index']);
        $r->post('/branches', [BranchController::class, 'store']);
        $r->get('/branches/{id}', [BranchController::class, 'show']);
        $r->put('/branches/{id}', [BranchController::class, 'update']);
        $r->delete('/branches/{id}', [BranchController::class, 'delete']);

        // Roles & Permissions (both /roles and /admin/roles)
        foreach (['/roles', '/admin/roles'] as $base) {
            $r->get($base, [RoleController::class, 'index']);
            $r->get($base . '/permissions', [RoleController::class, 'permissions']);
            $r->post($base, [RoleController::class, 'store']);
            $r->get($base . '/{id}', [RoleController::class, 'show']);
            $r->put($base . '/{id}', [RoleController::class, 'update']);
            $r->delete($base . '/{id}', [RoleController::class, 'delete']);
            $r->get($base . '/{id}/permissions', [RoleController::class, 'permissionsByRole']);
            $r->put($base . '/{id}/permissions', [RoleController::class, 'syncPermissions']);
        }

        // Users (both /users and /admin/users)
        foreach (['/users', '/admin/users'] as $base) {
            $r->get($base, [UserController::class, 'index']);
            $r->post($base, [UserController::class, 'store']);
            $r->get($base . '/{id}', [UserController::class, 'show']);
            $r->put($base . '/{id}', [UserController::class, 'update']);
            $r->delete($base . '/{id}', [UserController::class, 'delete']);
        }

        // Audit Logs (both /audit-logs and /admin/audit-logs)
        foreach (['/audit-logs', '/admin/audit-logs'] as $base) {
            $r->get($base, [AuditLogController::class, 'index']);
        }

        // Settings (both /settings and /admin/settings)
        foreach (['/settings', '/admin/settings'] as $base) {
            $r->get($base, [SettingsController::class, 'index']);
            $r->put($base, [SettingsController::class, 'update']);
        }

        // Master Data: Categories
        $r->get('/categories', [CategoryController::class, 'index']);
        $r->post('/categories', [CategoryController::class, 'store']);
        $r->get('/categories/{id}', [CategoryController::class, 'show']);
        $r->put('/categories/{id}', [CategoryController::class, 'update']);
        $r->delete('/categories/{id}', [CategoryController::class, 'delete']);

        // Master Data: Units
        $r->get('/units', [UnitController::class, 'index']);
        $r->post('/units', [UnitController::class, 'store']);
        $r->get('/units/{id}', [UnitController::class, 'show']);
        $r->put('/units/{id}', [UnitController::class, 'update']);
        $r->delete('/units/{id}', [UnitController::class, 'delete']);

        // Master Data: Warehouses
        $r->get('/warehouses', [WarehouseController::class, 'index']);
        $r->post('/warehouses', [WarehouseController::class, 'store']);
        $r->get('/warehouses/{id}', [WarehouseController::class, 'show']);
        $r->put('/warehouses/{id}', [WarehouseController::class, 'update']);
        $r->delete('/warehouses/{id}', [WarehouseController::class, 'delete']);

        // Master Data: Products
        $r->get('/products', [ProductController::class, 'index']);
        $r->post('/products', [ProductController::class, 'store']);
        $r->get('/products/{id}', [ProductController::class, 'show']);
        $r->put('/products/{id}', [ProductController::class, 'update']);
        $r->delete('/products/{id}', [ProductController::class, 'delete']);

        // Master Data: Suppliers
        $r->get('/suppliers', [SupplierController::class, 'index']);
        $r->post('/suppliers', [SupplierController::class, 'store']);
        $r->get('/suppliers/{id}', [SupplierController::class, 'show']);
        $r->put('/suppliers/{id}', [SupplierController::class, 'update']);
        $r->delete('/suppliers/{id}', [SupplierController::class, 'delete']);

        // Master Data: Customers
        $r->get('/customers', [CustomerController::class, 'index']);
        $r->post('/customers', [CustomerController::class, 'store']);
        $r->get('/customers/{id}', [CustomerController::class, 'show']);
        $r->put('/customers/{id}', [CustomerController::class, 'update']);
        $r->delete('/customers/{id}', [CustomerController::class, 'delete']);

        // Purchase: Requisitions (both /purchase/requisitions and /purchase-requisitions)
        foreach (['/purchase/requisitions', '/purchase-requisitions'] as $base) {
            $r->get($base, [PurchaseRequisitionController::class, 'index']);
            $r->post($base, [PurchaseRequisitionController::class, 'store']);
            $r->get($base . '/{id}', [PurchaseRequisitionController::class, 'show']);
            $r->put($base . '/{id}', [PurchaseRequisitionController::class, 'update']);
            $r->post($base . '/{id}/approve', [PurchaseRequisitionController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [PurchaseRequisitionController::class, 'approve']);
            $r->post($base . '/{id}/reject', [PurchaseRequisitionController::class, 'reject']);
            $r->patch($base . '/{id}/reject', [PurchaseRequisitionController::class, 'reject']);
            $r->delete($base . '/{id}', [PurchaseRequisitionController::class, 'delete']);
        }

        // Purchase: Orders (both /purchase/orders and /purchase-orders)
        foreach (['/purchase/orders', '/purchase-orders'] as $base) {
            $r->get($base, [PurchaseOrderController::class, 'index']);
            $r->post($base, [PurchaseOrderController::class, 'store']);
            $r->get($base . '/{id}', [PurchaseOrderController::class, 'show']);
            $r->put($base . '/{id}', [PurchaseOrderController::class, 'update']);
            $r->post($base . '/{id}/approve', [PurchaseOrderController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [PurchaseOrderController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
            $r->delete($base . '/{id}', [PurchaseOrderController::class, 'delete']);
        }

        // Purchase: Goods Receipts (both /purchase/grn and /goods-receipts)
        foreach (['/purchase/grn', '/goods-receipts'] as $base) {
            $r->get($base, [GoodsReceiptController::class, 'index']);
            $r->post($base, [GoodsReceiptController::class, 'store']);
            $r->get($base . '/{id}', [GoodsReceiptController::class, 'show']);
            $r->post($base . '/{id}/approve', [GoodsReceiptController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [GoodsReceiptController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [GoodsReceiptController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [GoodsReceiptController::class, 'cancel']);
        }

        // Purchase: Returns (both /purchase/returns and /purchase-returns)
        foreach (['/purchase/returns', '/purchase-returns'] as $base) {
            $r->get($base, [PurchaseReturnController::class, 'index']);
            $r->post($base, [PurchaseReturnController::class, 'store']);
            $r->get($base . '/{id}', [PurchaseReturnController::class, 'show']);
            $r->post($base . '/{id}/approve', [PurchaseReturnController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [PurchaseReturnController::class, 'approve']);
        }

        // Inventory: Balances, Ledger, Reports
        $r->get('/inventory/balances', [StockController::class, 'balances']);
        $r->get('/inventory/ledger', [StockController::class, 'ledger']);
        $r->get('/inventory/low-stock', [StockController::class, 'lowStock']);
        $r->get('/inventory/product-movements', [StockController::class, 'productMovements']);

        // Inventory: Transfers (both /inventory/transfers and /stock-transfers)
        foreach (['/inventory/transfers', '/stock-transfers'] as $base) {
            $r->get($base, [StockTransferController::class, 'index']);
            $r->post($base, [StockTransferController::class, 'store']);
            $r->get($base . '/{id}', [StockTransferController::class, 'show']);
            $r->post($base . '/{id}/complete', [StockTransferController::class, 'complete']);
            $r->patch($base . '/{id}/complete', [StockTransferController::class, 'complete']);
        }

        // Sales: Orders (both /sales/orders and /sales-orders)
        foreach (['/sales/orders', '/sales-orders'] as $base) {
            $r->get($base, [SalesOrderController::class, 'index']);
            $r->post($base, [SalesOrderController::class, 'store']);
            $r->get($base . '/{id}', [SalesOrderController::class, 'show']);
            $r->put($base . '/{id}', [SalesOrderController::class, 'update']);
            $r->post($base . '/{id}/confirm', [SalesOrderController::class, 'confirm']);
            $r->patch($base . '/{id}/confirm', [SalesOrderController::class, 'confirm']);
            $r->post($base . '/{id}/cancel', [SalesOrderController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [SalesOrderController::class, 'cancel']);
            $r->delete($base . '/{id}', [SalesOrderController::class, 'delete']);
        }

        // Sales: Deliveries (both /sales/deliveries and /deliveries)
        foreach (['/sales/deliveries', '/deliveries'] as $base) {
            $r->get($base, [DeliveryController::class, 'index']);
            $r->post($base, [DeliveryController::class, 'store']);
            $r->get($base . '/{id}', [DeliveryController::class, 'show']);
            $r->post($base . '/{id}/approve', [DeliveryController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [DeliveryController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [DeliveryController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [DeliveryController::class, 'cancel']);
        }

        // Sales: Invoices (both /sales/invoices and /invoices)
        foreach (['/sales/invoices', '/invoices'] as $base) {
            $r->get($base, [InvoiceController::class, 'index']);
            $r->post($base, [InvoiceController::class, 'store']);
            $r->get($base . '/{id}', [InvoiceController::class, 'show']);
            $r->post($base . '/{id}/post', [InvoiceController::class, 'approve']);
            $r->patch($base . '/{id}/post', [InvoiceController::class, 'approve']);
            $r->post($base . '/{id}/approve', [InvoiceController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [InvoiceController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [InvoiceController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [InvoiceController::class, 'cancel']);
        }

        // Sales: Money Receipts (both /sales/receipts and /money-receipts)
        foreach (['/sales/receipts', '/money-receipts'] as $base) {
            $r->get($base, [MoneyReceiptController::class, 'index']);
            $r->post($base, [MoneyReceiptController::class, 'store']);
            $r->get($base . '/{id}', [MoneyReceiptController::class, 'show']);
            $r->post($base . '/{id}/approve', [MoneyReceiptController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [MoneyReceiptController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [MoneyReceiptController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [MoneyReceiptController::class, 'cancel']);
        }

        // Sales: Returns (both /sales/returns and /sales-returns)
        foreach (['/sales/returns', '/sales-returns'] as $base) {
            $r->get($base, [SalesReturnController::class, 'index']);
            $r->post($base, [SalesReturnController::class, 'store']);
            $r->get($base . '/{id}', [SalesReturnController::class, 'show']);
            $r->post($base . '/{id}/approve', [SalesReturnController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [SalesReturnController::class, 'approve']);
        }

        // Accounts: Financial Periods (both /accounts/periods and /financial-periods)
        foreach (['/accounts/periods', '/financial-periods'] as $base) {
            $r->get($base, [PeriodController::class, 'index']);
            $r->post($base, [PeriodController::class, 'store']);
            $r->post($base . '/{id}/toggle', [PeriodController::class, 'toggle']);
            $r->patch($base . '/{id}/toggle', [PeriodController::class, 'toggle']);
            $r->post($base . '/{id}/close', [PeriodController::class, 'toggle']);
            $r->patch($base . '/{id}/close', [PeriodController::class, 'toggle']);
            $r->post($base . '/{id}/open', [PeriodController::class, 'toggle']);
            $r->patch($base . '/{id}/open', [PeriodController::class, 'toggle']);
        }

        // Accounts: Vouchers (both /accounts/vouchers and /vouchers)
        foreach (['/accounts/vouchers', '/vouchers'] as $base) {
            $r->get($base, [VoucherController::class, 'index']);
            $r->post($base, [VoucherController::class, 'store']);
            $r->get($base . '/{id}', [VoucherController::class, 'show']);
            $r->put($base . '/{id}', [VoucherController::class, 'update']);
            $r->post($base . '/{id}/post', [VoucherController::class, 'postVoucher']);
            $r->patch($base . '/{id}/post', [VoucherController::class, 'postVoucher']);
            $r->post($base . '/{id}/cancel', [VoucherController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [VoucherController::class, 'cancel']);
            $r->delete($base . '/{id}', [VoucherController::class, 'delete']);
        }

        // Accounts: Chart of Accounts
        $r->get('/accounts', [AccountController::class, 'index']);
        $r->post('/accounts', [AccountController::class, 'store']);
        $r->get('/accounts/{id}', [AccountController::class, 'show']);
        $r->put('/accounts/{id}', [AccountController::class, 'update']);
        $r->delete('/accounts/{id}', [AccountController::class, 'delete']);

        // Accounts: Financial Reports
        $r->get('/reports/trial-balance', [ReportController::class, 'trialBalance']);
        $r->get('/reports/general-ledger', [ReportController::class, 'generalLedger']);
        $r->get('/reports/customer-ledger', [ReportController::class, 'customerLedger']);
        $r->get('/reports/supplier-ledger', [ReportController::class, 'supplierLedger']);
        $r->get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
        $r->get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
        $r->get('/reports/cash-bank-book', [ReportController::class, 'cashBankBook']);

        // HR & Payroll (both /hr/... and root /...)
        foreach (['/departments', '/hr/departments'] as $base) {
            $r->get($base, [DepartmentController::class, 'index']);
            $r->post($base, [DepartmentController::class, 'store']);
            $r->put($base . '/{id}', [DepartmentController::class, 'update']);
            $r->delete($base . '/{id}', [DepartmentController::class, 'delete']);
        }

        foreach (['/designations', '/hr/designations'] as $base) {
            $r->get($base, [DesignationController::class, 'index']);
            $r->post($base, [DesignationController::class, 'store']);
            $r->put($base . '/{id}', [DesignationController::class, 'update']);
            $r->delete($base . '/{id}', [DesignationController::class, 'delete']);
        }

        foreach (['/employees', '/hr/employees'] as $base) {
            $r->get($base, [EmployeeController::class, 'index']);
            $r->post($base, [EmployeeController::class, 'store']);
            $r->get($base . '/{id}', [EmployeeController::class, 'show']);
            $r->put($base . '/{id}', [EmployeeController::class, 'update']);
            $r->delete($base . '/{id}', [EmployeeController::class, 'delete']);
        }

        foreach (['/attendance', '/hr/attendance'] as $base) {
            $r->get($base, [AttendanceController::class, 'index']);
            $r->post($base, [AttendanceController::class, 'store']);
            $r->post($base . '/bulk', [AttendanceController::class, 'bulkStore']);
        }

        foreach (['/leave-requests', '/hr/leave', '/leave'] as $base) {
            $r->get($base, [LeaveRequestController::class, 'index']);
            $r->post($base, [LeaveRequestController::class, 'store']);
            $r->post($base . '/{id}/approve', [LeaveRequestController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [LeaveRequestController::class, 'approve']);
            $r->post($base . '/{id}/reject', [LeaveRequestController::class, 'reject']);
            $r->patch($base . '/{id}/reject', [LeaveRequestController::class, 'reject']);
        }

        foreach (['/payroll-batches', '/hr/payroll', '/payroll'] as $base) {
            $r->get($base, [PayrollController::class, 'index']);
            $r->post($base . '/generate', [PayrollController::class, 'generate']);
            $r->get($base . '/{id}', [PayrollController::class, 'show']);
            $r->post($base . '/{id}/approve', [PayrollController::class, 'approve']);
            $r->patch($base . '/{id}/approve', [PayrollController::class, 'approve']);
            $r->post($base . '/{id}/cancel', [PayrollController::class, 'cancel']);
            $r->patch($base . '/{id}/cancel', [PayrollController::class, 'cancel']);
        }
    });
};
