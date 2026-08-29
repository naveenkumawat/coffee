<?php

use App\Http\Controllers\Administrator\DashboardController;
use App\Http\Controllers\Administrator\IngredientBrandController;
use App\Http\Controllers\Administrator\IngredientCategoryController;
use App\Http\Controllers\Administrator\IngredientController;
use App\Http\Controllers\Administrator\InventoryController;
use App\Http\Controllers\Administrator\InventoryRefillRequestController;
use App\Http\Controllers\Administrator\MenuCategoryController;
use App\Http\Controllers\Administrator\MenuItemController;
use App\Http\Controllers\Administrator\ProductCategoryController;
use App\Http\Controllers\Administrator\ProductController;
use App\Http\Controllers\Administrator\ProductFlavourController;
use App\Http\Controllers\Administrator\RecipeController;
use App\Http\Controllers\Administrator\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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

    Route::resource('menu/categories', MenuCategoryController::class)
        ->parameters(['categories' => 'menu_category'])
        ->names('menu.categories');

    Route::resource('menu/items', MenuItemController::class)
        ->parameters(['items' => 'menu_item'])
        ->names('menu.items');

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

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'administrator')
        ->name('logout');
});
