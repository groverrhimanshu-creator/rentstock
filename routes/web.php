<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConsumptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemImportController;
use App\Http\Controllers\LoginsController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\ReplacementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    return auth()->user()->role === 'customer' ? redirect()->route('portal') : redirect()->route('dashboard');
});

Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/offline', [\App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// "role" with no names only checks that the login is still switched on.
Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/page/{screen}', [PageController::class, 'show'])->name('page');

    Route::get('/dashboard', DashboardController::class)->middleware('role:admin,subadmin')->name('dashboard');
    Route::middleware('role:admin,subadmin')->group(function () {
        $T = \App\Http\Controllers\TasksController::class;
        Route::get('/tasks', [$T, 'index'])->middleware('perm:tasks.view')->name('tasks.index');
        Route::get('/tasks/history', [$T, 'history'])->middleware('perm:tasks.view')->name('tasks.history');
        Route::post('/tasks', [$T, 'store'])->middleware('perm:tasks.create')->name('tasks.store');
        Route::get('/tasks/{id}/edit', [$T, 'edit'])->whereNumber('id')->middleware('perm:tasks.edit')->name('tasks.edit');
        Route::put('/tasks/{id}', [$T, 'update'])->whereNumber('id')->middleware('perm:tasks.edit')->name('tasks.update');
        Route::post('/tasks/{id}/done', [$T, 'done'])->whereNumber('id')->middleware('perm:tasks.edit')->name('tasks.done');
        Route::post('/tasks/{id}/reopen', [$T, 'reopen'])->whereNumber('id')->middleware('perm:tasks.edit')->name('tasks.reopen');
        Route::post('/tasks/{id}/reschedule', [$T, 'reschedule'])->whereNumber('id')->middleware('perm:tasks.edit')->name('tasks.reschedule');
        Route::delete('/tasks/{id}', [$T, 'destroy'])->whereNumber('id')->middleware('perm:tasks.delete')->name('tasks.destroy');
    });

    /*
     * Staff screens. Each route says which permission it needs (perm:module.action). The Admin passes every check;
     * a SubAdmin passes the ones the Admin switched on for that login on the Users page. "a|b" means either one is enough.
     */
    Route::middleware('role:admin,subadmin')->group(function () {
        Route::get('/scan', [\App\Http\Controllers\ScanController::class, 'index'])->middleware('perm:stock.view')->name('scan');
        Route::post('/scan/lookup', [\App\Http\Controllers\ScanController::class, 'lookup'])->middleware('perm:stock.view')->name('scan.lookup');

        // Stock
        Route::prefix('stock')->group(function () {
            Route::middleware('perm:stock.view')->group(function () {
                Route::get('/inventory', [StockController::class, 'inventory'])->name('stock.inventory');
                Route::get('/check', [StockController::class, 'check'])->name('stock.check');
                Route::get('/device/{code}', [StockController::class, 'device'])->whereNumber('code')->name('stock.device');
                Route::get('/entries', [StockController::class, 'entries'])->name('stock.entries');
                Route::get('/entries/{id}/export', [StockController::class, 'entryExport'])->whereNumber('id')->name('stock.entry.export');
            });
            Route::middleware('perm:stock.repair')->group(function () {
                Route::post('/device/{code}/repaired', [StockController::class, 'repaired'])->whereNumber('code')->name('stock.device.repaired');
                Route::post('/repaired', [StockController::class, 'repairedMany'])->name('stock.repaired');
                Route::post('/device/{code}/dead', [StockController::class, 'dead'])->whereNumber('code')->name('stock.device.dead');
                Route::post('/dead', [StockController::class, 'deadMany'])->name('stock.dead');
                Route::post('/device/{code}/revive', [StockController::class, 'revive'])->whereNumber('code')->name('stock.device.revive');
            });
            Route::middleware('perm:stock.import')->group(function () {
                Route::get('/import', [StockController::class, 'importShow'])->name('stock.import');
                Route::get('/import/sample', [StockController::class, 'importSample'])->name('stock.import.sample');
                Route::post('/import/read', [StockController::class, 'importRead'])->name('stock.import.read');
                Route::post('/import/check', [StockController::class, 'importCheck'])->name('stock.import.check');
                Route::post('/import/commit', [StockController::class, 'importCommit'])->name('stock.import.commit');
                Route::post('/import/errors', [StockController::class, 'importErrors'])->name('stock.import.errors');
            });
            Route::middleware('perm:stock.edit')->group(function () {
                Route::get('/device/{code}/edit', [StockController::class, 'deviceEdit'])->whereNumber('code')->name('stock.device.edit');
                Route::put('/device/{code}', [StockController::class, 'deviceUpdate'])->whereNumber('code')->name('stock.device.update');
                Route::get('/entries/{id}/edit', [StockController::class, 'entryEdit'])->whereNumber('id')->name('stock.entry.edit');
                Route::put('/entries/{id}', [StockController::class, 'entryUpdate'])->whereNumber('id')->name('stock.entry.update');
            });
            Route::middleware('perm:stock.delete')->group(function () {
                Route::delete('/device/{code}', [StockController::class, 'deviceDestroy'])->whereNumber('code')->name('stock.device.destroy');
                Route::delete('/entries/{id}', [StockController::class, 'entryDestroy'])->whereNumber('id')->name('stock.entry.destroy');
            });
        });

        // Purchase
        Route::get('/purchase', [PurchaseController::class, 'index'])->middleware('perm:purchase.view')->name('purchase.index');
        Route::middleware('perm:purchase.create')->group(function () {
            Route::get('/purchase/new', [PurchaseController::class, 'create'])->name('purchase.create');
            Route::post('/purchase', [PurchaseController::class, 'store'])->name('purchase.store');
            Route::get('/purchase/import-sample', [PurchaseController::class, 'importSample'])->name('purchase.import.sample');
            Route::post('/purchase/import-rows', [PurchaseController::class, 'importRows'])->name('purchase.import');
            Route::post('/purchase/quick-item', [PurchaseController::class, 'quickItem'])->name('purchase.quick-item');
        });
        Route::get('/purchase/{id}', [PurchaseController::class, 'show'])->whereNumber('id')->middleware('perm:purchase.view')->name('purchase.show');
        Route::get('/purchase/{id}/invoice', [PurchaseController::class, 'invoice'])->whereNumber('id')->middleware('perm:purchase.view')->name('purchase.invoice');
        Route::get('/purchase/{id}/edit', [PurchaseController::class, 'edit'])->whereNumber('id')->middleware('perm:purchase.edit')->name('purchase.edit');
        Route::put('/purchase/{id}', [PurchaseController::class, 'update'])->whereNumber('id')->middleware('perm:purchase.edit')->name('purchase.update');
        Route::delete('/purchase/{id}', [PurchaseController::class, 'destroy'])->whereNumber('id')->middleware('perm:purchase.delete')->name('purchase.destroy');

        // Delivery copies (the signed challan / return / replacement papers)
        Route::get('/dc/{kind}/{id}', [\App\Http\Controllers\DcCopyController::class, 'show'])->whereNumber('id')->middleware('perm:rental.view|return.view|replacement.view|sale.view')->name('dc.show');
        Route::post('/dc/{kind}/{id}', [\App\Http\Controllers\DcCopyController::class, 'upload'])->whereNumber('id')->middleware('perm:rental.create|return.create|replacement.create|sale.create')->name('dc.upload');
        Route::delete('/dc/{kind}/{id}', [\App\Http\Controllers\DcCopyController::class, 'destroy'])->whereNumber('id')->middleware('perm:rental.create|return.create|replacement.create|sale.create')->name('dc.destroy');

        // Sale and rental invoice
        Route::get('/sale', [SaleController::class, 'index'])->middleware('perm:sale.view')->name('sale.index');
        Route::get('/sale/new', [SaleController::class, 'create'])->middleware('perm:sale.create')->name('sale.create');
        Route::get('/sale/stock', [SaleController::class, 'stock'])->middleware('perm:sale.create|rental.create')->name('sale.stock');
        Route::post('/sale', [SaleController::class, 'store'])->middleware('perm:sale.create')->name('sale.store');
        Route::get('/sale/{id}', [SaleController::class, 'show'])->whereNumber('id')->middleware('perm:sale.view')->name('sale.show');
        Route::get('/sale/{id}/edit', [SaleController::class, 'edit'])->whereNumber('id')->middleware('perm:sale.edit')->name('sale.edit');
        Route::put('/sale/{id}', [SaleController::class, 'update'])->whereNumber('id')->middleware('perm:sale.edit')->name('sale.update');
        Route::delete('/sale/{id}', [SaleController::class, 'destroy'])->whereNumber('id')->middleware('perm:sale.delete')->name('sale.destroy');

        // Rental: challans, and the rental invoices that are made from the Sale screen.
        Route::get('/rental', [RentalController::class, 'index'])->middleware('perm:rental.view')->name('rental.index');
        Route::get('/rental/new', [RentalController::class, 'create'])->middleware('perm:rental.create')->name('rental.create');
        Route::post('/rental', [RentalController::class, 'store'])->middleware('perm:rental.create')->name('rental.store');
        Route::get('/rental/bill', [RentalController::class, 'bill'])->middleware('perm:sale.create|rental.create')->name('rental.bill');
        Route::get('/rental/period', [RentalController::class, 'period'])->middleware('perm:sale.create|rental.create')->name('rental.period');
        Route::get('/rental/{id}', [RentalController::class, 'show'])->whereNumber('id')->middleware('perm:rental.view|sale.view')->name('rental.show');
        Route::get('/rental/{id}/edit', [RentalController::class, 'edit'])->whereNumber('id')->middleware('perm:rental.edit|sale.edit')->name('rental.edit');
        Route::put('/rental/{id}', [RentalController::class, 'update'])->whereNumber('id')->middleware('perm:rental.edit|sale.edit')->name('rental.update');
        Route::delete('/rental/{id}', [RentalController::class, 'destroy'])->whereNumber('id')->middleware('perm:rental.delete|sale.delete')->name('rental.destroy');

        // Return
        Route::get('/return', [ReturnController::class, 'index'])->middleware('perm:return.view')->name('return.index');
        Route::get('/return/{id}', [ReturnController::class, 'show'])->whereNumber('id')->middleware('perm:return.view')->name('return.show');
        Route::get('/return/new', [ReturnController::class, 'create'])->middleware('perm:return.create')->name('return.create');
        Route::get('/return/items', [ReturnController::class, 'items'])->middleware('perm:return.create')->name('return.items');
        Route::post('/return', [ReturnController::class, 'store'])->middleware('perm:return.create')->name('return.store');
        Route::delete('/return/{id}', [ReturnController::class, 'destroy'])->whereNumber('id')->middleware('perm:return.delete')->name('return.destroy');

        // Replacement
        Route::get('/replacement', [ReplacementController::class, 'index'])->middleware('perm:replacement.view')->name('replacement.index');
        Route::get('/replacement/{id}', [ReplacementController::class, 'show'])->whereNumber('id')->middleware('perm:replacement.view')->name('replacement.show');
        Route::get('/replacement/new', [ReplacementController::class, 'create'])->middleware('perm:replacement.create')->name('replacement.create');
        Route::get('/replacement/data', [ReplacementController::class, 'data'])->middleware('perm:replacement.create')->name('replacement.data');
        Route::post('/replacement', [ReplacementController::class, 'store'])->middleware('perm:replacement.create')->name('replacement.store');
        Route::delete('/replacement/{id}', [ReplacementController::class, 'destroy'])->whereNumber('id')->middleware('perm:replacement.delete')->name('replacement.destroy');

        // Upgrade assets
        Route::get('/consumption', [ConsumptionController::class, 'index'])->middleware('perm:consumption.view')->name('consumption.index');
        Route::get('/consumption/export', [ConsumptionController::class, 'export'])->middleware('perm:consumption.view')->name('consumption.export');
        Route::get('/consumption/new', [ConsumptionController::class, 'create'])->middleware('perm:consumption.create')->name('consumption.create');
        Route::get('/consumption/lookup', [ConsumptionController::class, 'lookup'])->middleware('perm:consumption.create')->name('consumption.lookup');
        Route::post('/consumption', [ConsumptionController::class, 'store'])->middleware('perm:consumption.create')->name('consumption.store');
        Route::delete('/consumption/{id}', [ConsumptionController::class, 'destroy'])->whereNumber('id')->middleware('perm:consumption.delete')->name('consumption.destroy');

        // Print barcodes
        Route::middleware('perm:barcodes.view')->group(function () {
            Route::get('/barcodes', [BarcodeController::class, 'index'])->name('barcodes.index');
            Route::get('/barcodes/label/{code}', [BarcodeController::class, 'label'])->whereNumber('code')->name('barcodes.label');
            Route::post('/barcodes/labels', [BarcodeController::class, 'labels'])->name('barcodes.labels');
            Route::post('/barcodes/labels.pdf', [BarcodeController::class, 'labelsPdf'])->name('barcodes.labels.pdf');
            Route::get('/barcodes/purchase/{id}', [BarcodeController::class, 'purchase'])->whereNumber('id')->name('barcodes.purchase');
        });

        // Payments and reminders
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('perm:payments.view')->name('payments.index');
        Route::middleware('perm:payments.manage')->group(function () {
            Route::post('/payments/settings', [PaymentController::class, 'settings'])->name('payments.settings');
            Route::post('/payments/test-mail', [PaymentController::class, 'testMail'])->name('payments.test-mail');
            Route::post('/payments/remind-all', [PaymentController::class, 'remindAll'])->name('payments.remind-all');
            Route::post('/payments/{kind}/{id}/remind', [PaymentController::class, 'remind'])->whereNumber('id')->name('payments.remind');
            Route::post('/payments/{kind}/{id}/paid', [PaymentController::class, 'paid'])->whereNumber('id')->name('payments.paid');
        });

        // Reports
        Route::middleware('perm:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
            Route::get('/reports/on-rent/{id}', [ReportController::class, 'onRent'])->whereNumber('id')->name('reports.onrent');
            Route::get('/reports/on-rent/{id}/export', [ReportController::class, 'onRentExport'])->whereNumber('id')->name('reports.onrent.export');
            Route::get('/reports/purchase/{id}', [ReportController::class, 'purchaseOne'])->whereNumber('id')->name('reports.purchase.one');
            Route::post('/reports/purchase-pay', [ReportController::class, 'payStore'])->middleware('perm:purchase.edit')->name('reports.purchase.pay');
            Route::delete('/reports/purchase-pay/{id}', [ReportController::class, 'payDestroy'])->whereNumber('id')->middleware('perm:purchase.edit')->name('reports.purchase.pay.del');
            Route::get('/reports/purchase-lines/csv', [ReportController::class, 'purchaseLinesCsv'])->name('reports.purchase.lines');
        });

        // Masters (brand, category, unit, store, customer, supplier, item, bank)
        Route::get('/masters/item/export', [MasterController::class, 'itemsExport'])->middleware('perm:masters.view')->name('items.export');
        Route::middleware('perm:masters.create')->group(function () {
            Route::get('/brands/import', [\App\Http\Controllers\BrandImportController::class, 'show'])->name('brands.import');
            Route::get('/brands/import/sample', [\App\Http\Controllers\BrandImportController::class, 'sample'])->name('brands.import.sample');
            Route::post('/brands/import/read', [\App\Http\Controllers\BrandImportController::class, 'read'])->name('brands.import.read');
            Route::post('/brands/import/commit', [\App\Http\Controllers\BrandImportController::class, 'commit'])->name('brands.import.commit');
            Route::get('/items/import', [ItemImportController::class, 'show'])->name('items.import');
            Route::get('/items/import/sample', [ItemImportController::class, 'sample'])->name('items.import.sample');
            Route::post('/items/import/read', [ItemImportController::class, 'read'])->name('items.import.read');
            Route::post('/items/import/check', [ItemImportController::class, 'check'])->name('items.import.check');
            Route::post('/items/import/errors', [ItemImportController::class, 'errors'])->name('items.import.errors');
            Route::post('/items/import/commit', [ItemImportController::class, 'commit'])->name('items.import.commit');
            Route::get('/masters/{key}/create', [MasterController::class, 'create'])->name('masters.create');
            Route::post('/masters/{key}', [MasterController::class, 'store'])->name('masters.store');
        });
        Route::get('/masters/{key}', [MasterController::class, 'index'])->middleware('perm:masters.view')->name('masters.index');
        Route::get('/masters/{key}/{id}/items', [MasterController::class, 'assoc'])->whereNumber('id')->middleware('perm:masters.view')->name('masters.assoc');
        Route::get('/masters/{key}/{id}/items/export', [MasterController::class, 'assocExport'])->whereNumber('id')->middleware('perm:masters.view')->name('masters.assoc.export');
        Route::get('/masters/{key}/{id}/edit', [MasterController::class, 'edit'])->whereNumber('id')->middleware('perm:masters.edit')->name('masters.edit');
        Route::put('/masters/{key}/{id}', [MasterController::class, 'update'])->whereNumber('id')->middleware('perm:masters.edit')->name('masters.update');
        Route::delete('/masters/{key}/{id}', [MasterController::class, 'destroy'])->whereNumber('id')->middleware('perm:masters.delete')->name('masters.destroy');
    });

    // The customer's own login: what they have on rent, their documents and returns.
    Route::middleware('role:customer')->group(function () {
        Route::get('/my/rentals', [PortalController::class, 'portal'])->name('portal');
        Route::get('/my/invoices', [PortalController::class, 'invoices'])->name('myinv');
        Route::get('/my/returns', [PortalController::class, 'returns'])->name('myret');
        Route::get('/my/rental/{id}', [RentalController::class, 'mine'])->whereNumber('id')->name('my.rental.show');
        Route::get('/my/sale/{id}', [SaleController::class, 'mine'])->whereNumber('id')->name('my.sale.show');
    });

    // Users and their permissions, customer logins and the company profile are for the Admin only.
    Route::middleware('role:admin')->group(function () {
        Route::get('/activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');
        Route::get('/activity/export', [\App\Http\Controllers\ActivityController::class, 'csv'])->name('activity.csv');
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [UsersController::class, 'edit'])->whereNumber('id')->name('users.edit');
        Route::put('/users/{id}', [UsersController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::post('/users/{id}/toggle', [UsersController::class, 'toggle'])->whereNumber('id')->name('users.toggle');
        Route::delete('/users/{id}', [UsersController::class, 'destroy'])->whereNumber('id')->name('users.destroy');

        Route::get('/logins', [LoginsController::class, 'index'])->name('logins.index');
        Route::get('/logins/create', [LoginsController::class, 'create'])->name('logins.create');
        Route::post('/logins', [LoginsController::class, 'store'])->name('logins.store');
        Route::post('/logins/{id}/toggle', [LoginsController::class, 'toggle'])->whereNumber('id')->name('logins.toggle');
        Route::get('/logins/{id}/edit', [LoginsController::class, 'edit'])->whereNumber('id')->name('logins.edit');
        Route::put('/logins/{id}', [LoginsController::class, 'update'])->whereNumber('id')->name('logins.update');
        Route::delete('/logins/{id}', [LoginsController::class, 'destroy'])->whereNumber('id')->name('logins.destroy');

        Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
    });
});
