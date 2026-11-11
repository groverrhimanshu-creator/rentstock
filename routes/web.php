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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    return auth()->user()->role === 'customer' ? redirect()->route('portal') : redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// "role" with no names only checks that the login is still switched on.
Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/page/{screen}', [PageController::class, 'show'])->name('page');

    Route::get('/dashboard', DashboardController::class)->middleware('role:admin,subadmin')->name('dashboard');

    Route::middleware('role:admin,subadmin')->prefix('stock')->group(function () {
        Route::get('/inventory', [StockController::class, 'inventory'])->name('stock.inventory');
        Route::get('/device/{code}', [StockController::class, 'device'])->whereNumber('code')->name('stock.device');
        Route::get('/entries', [StockController::class, 'entries'])->name('stock.entries');
        Route::get('/import', [StockController::class, 'importShow'])->name('stock.import');
        Route::get('/import/sample', [StockController::class, 'importSample'])->name('stock.import.sample');
        Route::post('/import/read', [StockController::class, 'importRead'])->name('stock.import.read');
        Route::post('/import/check', [StockController::class, 'importCheck'])->name('stock.import.check');
        Route::post('/import/commit', [StockController::class, 'importCommit'])->name('stock.import.commit');
        Route::post('/import/errors', [StockController::class, 'importErrors'])->name('stock.import.errors');
    });

    Route::middleware('role:admin,subadmin')->group(function () {
        Route::get('/purchase', [PurchaseController::class, 'index'])->name('purchase.index');
        Route::get('/purchase/new', [PurchaseController::class, 'create'])->name('purchase.create');
        Route::post('/purchase', [PurchaseController::class, 'store'])->name('purchase.store');
        Route::post('/purchase/quick-item', [PurchaseController::class, 'quickItem'])->name('purchase.quick-item');
        Route::get('/purchase/{id}', [PurchaseController::class, 'show'])->whereNumber('id')->name('purchase.show');

        Route::get('/sale', [SaleController::class, 'index'])->name('sale.index');
        Route::get('/sale/new', [SaleController::class, 'create'])->name('sale.create');
        Route::get('/sale/stock', [SaleController::class, 'stock'])->name('sale.stock');
        Route::post('/sale', [SaleController::class, 'store'])->name('sale.store');
        Route::get('/sale/{id}', [SaleController::class, 'show'])->whereNumber('id')->name('sale.show');

        // Rental: challans, and the rental invoices that are made from the Sale screen.
        Route::get('/rental', [RentalController::class, 'index'])->name('rental.index');
        Route::get('/rental/new', [RentalController::class, 'create'])->name('rental.create');
        Route::post('/rental', [RentalController::class, 'store'])->name('rental.store');
        Route::get('/rental/bill', [RentalController::class, 'bill'])->name('rental.bill');
        Route::get('/rental/period', [RentalController::class, 'period'])->name('rental.period');
        Route::get('/rental/{id}', [RentalController::class, 'show'])->whereNumber('id')->name('rental.show');

        Route::get('/return', [ReturnController::class, 'index'])->name('return.index');
        Route::get('/return/new', [ReturnController::class, 'create'])->name('return.create');
        Route::get('/return/items', [ReturnController::class, 'items'])->name('return.items');
        Route::post('/return', [ReturnController::class, 'store'])->name('return.store');

        Route::get('/replacement', [ReplacementController::class, 'index'])->name('replacement.index');
        Route::get('/replacement/new', [ReplacementController::class, 'create'])->name('replacement.create');
        Route::get('/replacement/data', [ReplacementController::class, 'data'])->name('replacement.data');
        Route::post('/replacement', [ReplacementController::class, 'store'])->name('replacement.store');

        Route::get('/consumption', [ConsumptionController::class, 'index'])->name('consumption.index');
        Route::get('/consumption/export', [ConsumptionController::class, 'export'])->name('consumption.export');
        Route::get('/consumption/new', [ConsumptionController::class, 'create'])->name('consumption.create');
        Route::get('/consumption/lookup', [ConsumptionController::class, 'lookup'])->name('consumption.lookup');
        Route::post('/consumption', [ConsumptionController::class, 'store'])->name('consumption.store');

        Route::get('/barcodes', [BarcodeController::class, 'index'])->name('barcodes.index');
        Route::get('/barcodes/label/{code}', [BarcodeController::class, 'label'])->whereNumber('code')->name('barcodes.label');
        Route::post('/barcodes/labels', [BarcodeController::class, 'labels'])->name('barcodes.labels');

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/settings', [PaymentController::class, 'settings'])->name('payments.settings');
        Route::post('/payments/test-mail', [PaymentController::class, 'testMail'])->name('payments.test-mail');
        Route::post('/payments/remind-all', [PaymentController::class, 'remindAll'])->name('payments.remind-all');
        Route::post('/payments/{kind}/{id}/remind', [PaymentController::class, 'remind'])->whereNumber('id')->name('payments.remind');
        Route::post('/payments/{kind}/{id}/paid', [PaymentController::class, 'paid'])->whereNumber('id')->name('payments.paid');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    });

    // The customer's own login: what they have on rent, their documents and returns.
    Route::middleware('role:customer')->group(function () {
        Route::get('/my/rentals', [PortalController::class, 'portal'])->name('portal');
        Route::get('/my/invoices', [PortalController::class, 'invoices'])->name('myinv');
        Route::get('/my/returns', [PortalController::class, 'returns'])->name('myret');
        Route::get('/my/rental/{id}', [RentalController::class, 'mine'])->whereNumber('id')->name('my.rental.show');
        Route::get('/my/sale/{id}', [SaleController::class, 'mine'])->whereNumber('id')->name('my.sale.show');
    });

    // Masters, customer logins and the company profile are for the Admin only.
    Route::middleware('role:admin')->group(function () {
        Route::get('/items/import', [ItemImportController::class, 'show'])->name('items.import');
        Route::get('/items/import/sample', [ItemImportController::class, 'sample'])->name('items.import.sample');
        Route::post('/items/import/read', [ItemImportController::class, 'read'])->name('items.import.read');
        Route::post('/items/import/check', [ItemImportController::class, 'check'])->name('items.import.check');
        Route::post('/items/import/errors', [ItemImportController::class, 'errors'])->name('items.import.errors');
        Route::post('/items/import/commit', [ItemImportController::class, 'commit'])->name('items.import.commit');
        Route::get('/masters/{key}', [MasterController::class, 'index'])->name('masters.index');
        Route::get('/masters/{key}/create', [MasterController::class, 'create'])->name('masters.create');
        Route::get('/masters/{key}/{id}/items', [MasterController::class, 'assoc'])->whereNumber('id')->name('masters.assoc');
        Route::get('/masters/{key}/{id}/items/export', [MasterController::class, 'assocExport'])->whereNumber('id')->name('masters.assoc.export');
        Route::post('/masters/{key}', [MasterController::class, 'store'])->name('masters.store');
        Route::get('/masters/{key}/{id}/edit', [MasterController::class, 'edit'])->whereNumber('id')->name('masters.edit');
        Route::put('/masters/{key}/{id}', [MasterController::class, 'update'])->whereNumber('id')->name('masters.update');
        Route::delete('/masters/{key}/{id}', [MasterController::class, 'destroy'])->whereNumber('id')->name('masters.destroy');

        Route::get('/logins', [LoginsController::class, 'index'])->name('logins.index');
        Route::get('/logins/create', [LoginsController::class, 'create'])->name('logins.create');
        Route::post('/logins', [LoginsController::class, 'store'])->name('logins.store');
        Route::post('/logins/{id}/toggle', [LoginsController::class, 'toggle'])->whereNumber('id')->name('logins.toggle');
        Route::get('/logins/{id}/edit', [LoginsController::class, 'edit'])->whereNumber('id')->name('logins.edit');
        Route::put('/logins/{id}', [LoginsController::class, 'update'])->whereNumber('id')->name('logins.update');
        Route::delete('/logins/{id}', [LoginsController::class, 'destroy'])->whereNumber('id')->name('logins.destroy');

        // Editing and deleting purchases, sales, stock entries and single items is for the Admin only.
        Route::get('/purchase/{id}/edit', [PurchaseController::class, 'edit'])->whereNumber('id')->name('purchase.edit');
        Route::put('/purchase/{id}', [PurchaseController::class, 'update'])->whereNumber('id')->name('purchase.update');
        Route::delete('/purchase/{id}', [PurchaseController::class, 'destroy'])->whereNumber('id')->name('purchase.destroy');
        Route::get('/sale/{id}/edit', [SaleController::class, 'edit'])->whereNumber('id')->name('sale.edit');
        Route::put('/sale/{id}', [SaleController::class, 'update'])->whereNumber('id')->name('sale.update');
        Route::delete('/sale/{id}', [SaleController::class, 'destroy'])->whereNumber('id')->name('sale.destroy');
        Route::get('/stock/device/{code}/edit', [StockController::class, 'deviceEdit'])->whereNumber('code')->name('stock.device.edit');
        Route::put('/stock/device/{code}', [StockController::class, 'deviceUpdate'])->whereNumber('code')->name('stock.device.update');
        Route::delete('/stock/device/{code}', [StockController::class, 'deviceDestroy'])->whereNumber('code')->name('stock.device.destroy');
        Route::get('/stock/entries/{id}/edit', [StockController::class, 'entryEdit'])->whereNumber('id')->name('stock.entry.edit');
        Route::put('/stock/entries/{id}', [StockController::class, 'entryUpdate'])->whereNumber('id')->name('stock.entry.update');
        Route::delete('/stock/entries/{id}', [StockController::class, 'entryDestroy'])->whereNumber('id')->name('stock.entry.destroy');

        Route::get('/rental/{id}/edit', [RentalController::class, 'edit'])->whereNumber('id')->name('rental.edit');
        Route::put('/rental/{id}', [RentalController::class, 'update'])->whereNumber('id')->name('rental.update');
        Route::delete('/rental/{id}', [RentalController::class, 'destroy'])->whereNumber('id')->name('rental.destroy');
        Route::delete('/return/{id}', [ReturnController::class, 'destroy'])->whereNumber('id')->name('return.destroy');
        Route::delete('/replacement/{id}', [ReplacementController::class, 'destroy'])->whereNumber('id')->name('replacement.destroy');
        Route::delete('/consumption/{id}', [ConsumptionController::class, 'destroy'])->whereNumber('id')->name('consumption.destroy');

        Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
    });
});
