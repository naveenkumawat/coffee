<?php

use App\Http\Controllers\Administrator\AddOnController;
use App\Http\Controllers\Administrator\CafeScheduleController;
use App\Http\Controllers\Administrator\CafeTableController;
use App\Http\Controllers\Administrator\CampaignController;
use App\Http\Controllers\Administrator\DashboardController;
use App\Http\Controllers\Administrator\DiningSessionController;
use App\Http\Controllers\Administrator\FinancialReportController;
use App\Http\Controllers\Administrator\HomeSectionController;
use App\Http\Controllers\Administrator\IngredientBrandController;
use App\Http\Controllers\Administrator\IngredientCategoryController;
use App\Http\Controllers\Administrator\IngredientController;
use App\Http\Controllers\Administrator\InventoryController;
use App\Http\Controllers\Administrator\InventoryProductReportController;
use App\Http\Controllers\Administrator\InventoryRefillRequestController;
use App\Http\Controllers\Administrator\MenuCategoryController;
use App\Http\Controllers\Administrator\MenuItemController;
use App\Http\Controllers\Administrator\OperationalPerformanceReportController;
use App\Http\Controllers\Administrator\OrderController;
use App\Http\Controllers\Administrator\ProductCategoryController;
use App\Http\Controllers\Administrator\ProductController;
use App\Http\Controllers\Administrator\ProductFlavourController;
use App\Http\Controllers\Administrator\ProductRatingController;
use App\Http\Controllers\Administrator\ProductTagController;
use App\Http\Controllers\Administrator\PromotionController;
use App\Http\Controllers\Administrator\RecipeController;
use App\Http\Controllers\Administrator\ReferralController;
use App\Http\Controllers\Administrator\SocialLinkController;
use App\Http\Controllers\Administrator\UserController;
use App\Http\Controllers\Administrator\WebsiteSettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Internal\StaffNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('administrator')) {
        return redirect()->route('administrator.dashboard');
    }

    return redirect()->route('administrator.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'administrator')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'administrator')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:owner,manager'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('reports/financial', [FinancialReportController::class, 'index'])->name('reports.financial.index');
    Route::get('reports/financial/export', [FinancialReportController::class, 'export'])->name('reports.financial.export');
    Route::get('reports/inventory-products', [InventoryProductReportController::class, 'index'])->name('reports.inventory-products.index');
    Route::get('reports/inventory-products/export/ingredient-movements', [InventoryProductReportController::class, 'exportIngredientMovements'])->name('reports.inventory-products.export.ingredient-movements');
    Route::get('reports/inventory-products/export/product-sales', [InventoryProductReportController::class, 'exportProductSales'])->name('reports.inventory-products.export.product-sales');
    Route::get('reports/operational-performance', [OperationalPerformanceReportController::class, 'index'])->name('reports.operational-performance.index');
    Route::get('reports/operational-performance/export/preparations', [OperationalPerformanceReportController::class, 'exportPreparations'])->name('reports.operational-performance.export.preparations');
    Route::get('reports/operational-performance/export/dining', [OperationalPerformanceReportController::class, 'exportDining'])->name('reports.operational-performance.export.dining');

    Route::get('home-sections', [HomeSectionController::class, 'index'])->name('home-sections.index');
    Route::get('home-sections/create', [HomeSectionController::class, 'create'])->name('home-sections.create');
    Route::post('home-sections', [HomeSectionController::class, 'store'])->name('home-sections.store');
    Route::get('home-sections/{homeSection}/edit', [HomeSectionController::class, 'edit'])->name('home-sections.edit');
    Route::put('home-sections/{homeSection}', [HomeSectionController::class, 'update'])->name('home-sections.update');
    Route::delete('home-sections/{homeSection}', [HomeSectionController::class, 'destroy'])->name('home-sections.destroy');
    Route::patch('home-sections/{homeSection}/toggle', [HomeSectionController::class, 'toggle'])->name('home-sections.toggle');
    Route::patch('home-sections/{homeSection}/move-up', [HomeSectionController::class, 'moveUp'])->name('home-sections.move-up');
    Route::patch('home-sections/{homeSection}/move-down', [HomeSectionController::class, 'moveDown'])->name('home-sections.move-down');
    Route::get('home-sections/{homeSection}/products', [HomeSectionController::class, 'products'])->name('home-sections.products');
    Route::post('home-sections/{homeSection}/products', [HomeSectionController::class, 'attachProduct'])->name('home-sections.products.attach');
    Route::delete('home-sections/{homeSection}/products/{product}', [HomeSectionController::class, 'detachProduct'])->name('home-sections.products.detach');
    Route::patch('home-sections/{homeSection}/products/{product}/move-up', [HomeSectionController::class, 'moveProductUp'])->name('home-sections.products.move-up');
    Route::patch('home-sections/{homeSection}/products/{product}/move-down', [HomeSectionController::class, 'moveProductDown'])->name('home-sections.products.move-down');

    Route::resource('menu/categories', MenuCategoryController::class)
        ->parameters(['categories' => 'menu_category'])
        ->names('menu.categories');

    Route::resource('menu/items', MenuItemController::class)
        ->parameters(['items' => 'menu_item'])
        ->names('menu.items');

    Route::get('dining-sessions', [DiningSessionController::class, 'index'])->name('dining-sessions.index');
    Route::get('dining-sessions/{dining_session}', [DiningSessionController::class, 'show'])->name('dining-sessions.show');
    Route::post('dining-sessions/{dining_session}/close', [DiningSessionController::class, 'close'])->name('dining-sessions.close');
    Route::post('dining-sessions/{dining_session}/reopen', [DiningSessionController::class, 'reopen'])->name('dining-sessions.reopen');
    Route::post('dining-sessions/{dining_session}/payment-method', [DiningSessionController::class, 'changePaymentMethod'])->name('dining-sessions.payment-method');
    Route::post('dining-sessions/{dining_session}/payment/confirm', [DiningSessionController::class, 'confirmPayment'])->name('dining-sessions.payment.confirm');
    Route::post('dining-sessions/{dining_session}/cash/receive', [DiningSessionController::class, 'markCashReceived'])->name('dining-sessions.cash.receive');
    Route::post('dining-sessions/{dining_session}/rounds/{order}/served', [DiningSessionController::class, 'markRoundServed'])->name('dining-sessions.rounds.served');
    Route::post('dining-sessions/{dining_session}/rounds/{order}/cancel', [DiningSessionController::class, 'cancelRound'])->name('dining-sessions.rounds.cancel');
    Route::post('dining-sessions/{dining_session}/payment-proof/reject', [DiningSessionController::class, 'rejectPaymentProof'])->name('dining-sessions.payment-proof.reject');
    Route::get('dining-sessions/{dining_session}/payment-proof', [DiningSessionController::class, 'paymentProof'])->name('dining-sessions.payment-proof.show');
    Route::get('dining-sessions/{dining_session}/invoice', [DiningSessionController::class, 'invoice'])->name('dining-sessions.invoice');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::post('orders/{order}/cash/receive', [OrderController::class, 'markCashReceived'])->name('orders.cash.receive');
    Route::post('orders/{order}/payment-proof/reject', [OrderController::class, 'rejectPaymentProof'])->name('orders.payment-proof.reject');
    Route::get('orders/{order}/payment-proof', [OrderController::class, 'paymentProof'])->name('orders.payment-proof.show');
    Route::get('orders/{order}/invoice/pdf', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.pdf');
    Route::get('orders/{order}/invoice/print', [OrderController::class, 'printInvoice'])->name('orders.invoice.print');
    Route::get('orders/{order}/invoice/receipt', [OrderController::class, 'printReceipt'])->name('orders.invoice.receipt');

    Route::post('notifications/read-all', [StaffNotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::resource('ingredients/categories', IngredientCategoryController::class)
        ->parameters(['categories' => 'ingredient_category'])
        ->names('ingredients.categories');

    Route::resource('ingredients/brands', IngredientBrandController::class)
        ->parameters(['brands' => 'ingredient_brand'])
        ->names('ingredients.brands');

    Route::resource('ingredients', IngredientController::class)
        ->parameters(['ingredients' => 'ingredient'])
        ->names('ingredients');

    Route::resource('products/categories', ProductCategoryController::class)
        ->parameters(['categories' => 'product_category'])
        ->names('products.categories');

    Route::resource('products/flavours', ProductFlavourController::class)
        ->parameters(['flavours' => 'product_flavour'])
        ->names('products.flavours');

    Route::resource('products/tags', ProductTagController::class)
        ->except(['show'])
        ->parameters(['tags' => 'product_tag'])
        ->names('products.tags');

    Route::resource('add-ons', AddOnController::class)
        ->except(['show'])
        ->parameters(['add-ons' => 'add_on'])
        ->names('add-ons');

    Route::get('products/ratings', [ProductRatingController::class, 'index'])->name('products.ratings.index');
    Route::get('products/ratings/{productRating}', [ProductRatingController::class, 'show'])->name('products.ratings.show');
    Route::patch('products/ratings/{productRating}/hide', [ProductRatingController::class, 'hide'])->name('products.ratings.hide');
    Route::patch('products/ratings/{productRating}/publish', [ProductRatingController::class, 'publish'])->name('products.ratings.publish');
    Route::delete('products/ratings/{productRating}', [ProductRatingController::class, 'destroy'])->name('products.ratings.destroy');

    Route::resource('products', ProductController::class)
        ->parameters(['products' => 'product'])
        ->names('products');

    Route::resource('recipes', RecipeController::class)
        ->parameters(['recipes' => 'recipe'])
        ->names('recipes');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::get('inventory/movements/create', [InventoryController::class, 'createMovement'])->name('inventory.movements.create');
    Route::post('inventory/movements', [InventoryController::class, 'storeMovement'])->name('inventory.movements.store');
    Route::get('inventory/refill-requests', [InventoryRefillRequestController::class, 'index'])->name('inventory.refill-requests.index');
    Route::get('inventory/refill-requests/{inventoryRefillRequest}', [InventoryRefillRequestController::class, 'show'])->name('inventory.refill-requests.show');
    Route::patch('inventory/refill-requests/{inventoryRefillRequest}/approve', [InventoryRefillRequestController::class, 'approve'])->name('inventory.refill-requests.approve');
    Route::patch('inventory/refill-requests/{inventoryRefillRequest}/reject', [InventoryRefillRequestController::class, 'reject'])->name('inventory.refill-requests.reject');

    Route::resource('users', UserController::class);
    Route::post('users/{user}/block-ordering', [UserController::class, 'blockOrdering'])->name('users.block-ordering');
    Route::post('users/{user}/unblock-ordering', [UserController::class, 'unblockOrdering'])->name('users.unblock-ordering');

    Route::get('website-settings', [WebsiteSettingController::class, 'edit'])->name('website-settings.edit');
    Route::put('website-settings', [WebsiteSettingController::class, 'update'])->name('website-settings.update');

    Route::get('social-links', [SocialLinkController::class, 'index'])->name('social-links.index');
    Route::get('social-links/create', [SocialLinkController::class, 'create'])->name('social-links.create');
    Route::post('social-links', [SocialLinkController::class, 'store'])->name('social-links.store');
    Route::get('social-links/{social_link}/edit', [SocialLinkController::class, 'edit'])->name('social-links.edit');
    Route::put('social-links/{social_link}', [SocialLinkController::class, 'update'])->name('social-links.update');
    Route::delete('social-links/{social_link}', [SocialLinkController::class, 'destroy'])->name('social-links.destroy');
    Route::patch('social-links/{social_link}/toggle', [SocialLinkController::class, 'toggle'])->name('social-links.toggle');
    Route::patch('social-links/{social_link}/move-up', [SocialLinkController::class, 'moveUp'])->name('social-links.move-up');
    Route::patch('social-links/{social_link}/move-down', [SocialLinkController::class, 'moveDown'])->name('social-links.move-down');

    Route::get('cafe-tables', [CafeTableController::class, 'index'])->name('cafe-tables.index');
    Route::get('cafe-tables/create', [CafeTableController::class, 'create'])->name('cafe-tables.create');
    Route::post('cafe-tables', [CafeTableController::class, 'store'])->name('cafe-tables.store');
    Route::get('cafe-tables/{cafe_table}/edit', [CafeTableController::class, 'edit'])->name('cafe-tables.edit');
    Route::put('cafe-tables/{cafe_table}', [CafeTableController::class, 'update'])->name('cafe-tables.update');
    Route::delete('cafe-tables/{cafe_table}', [CafeTableController::class, 'destroy'])->name('cafe-tables.destroy');
    Route::patch('cafe-tables/{cafe_table}/toggle', [CafeTableController::class, 'toggle'])->name('cafe-tables.toggle');
    Route::patch('cafe-tables/{cafe_table}/move-up', [CafeTableController::class, 'moveUp'])->name('cafe-tables.move-up');
    Route::patch('cafe-tables/{cafe_table}/move-down', [CafeTableController::class, 'moveDown'])->name('cafe-tables.move-down');

    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::patch('campaigns/{campaign}/status/{status}', [CampaignController::class, 'setStatus'])->name('campaigns.status');
    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');

    Route::get('cafe-schedule', [CafeScheduleController::class, 'index'])->name('cafe-schedule.index');
    Route::get('cafe-schedule/hours', [CafeScheduleController::class, 'editHours'])->name('cafe-schedule.hours.edit');
    Route::put('cafe-schedule/hours', [CafeScheduleController::class, 'updateHours'])->name('cafe-schedule.hours.update');
    Route::get('cafe-schedule/closures/create', [CafeScheduleController::class, 'createClosure'])->name('cafe-schedule.closures.create');
    Route::post('cafe-schedule/closures', [CafeScheduleController::class, 'storeClosure'])->name('cafe-schedule.closures.store');
    Route::get('cafe-schedule/closures/{cafe_closure}/edit', [CafeScheduleController::class, 'editClosure'])->name('cafe-schedule.closures.edit');
    Route::put('cafe-schedule/closures/{cafe_closure}', [CafeScheduleController::class, 'updateClosure'])->name('cafe-schedule.closures.update');
    Route::patch('cafe-schedule/closures/{cafe_closure}/toggle', [CafeScheduleController::class, 'toggleClosure'])->name('cafe-schedule.closures.toggle');
    Route::delete('cafe-schedule/closures/{cafe_closure}', [CafeScheduleController::class, 'destroyClosure'])->name('cafe-schedule.closures.destroy');
    Route::post('cafe-schedule/close-ordering', [CafeScheduleController::class, 'closeOrdering'])->name('cafe-schedule.close');
    Route::post('cafe-schedule/reopen-ordering', [CafeScheduleController::class, 'reopenOrdering'])->name('cafe-schedule.reopen');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'administrator')
        ->name('logout');
});
