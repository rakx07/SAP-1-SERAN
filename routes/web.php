<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SAPController;
use App\Http\Controllers\OpcodeController;


Route::get('/', function () {
    return redirect()->route('sap.view');
});

// MAIN DASHBOARD VIEW
Route::get('/sap', [SAPController::class, 'view'])->name('sap.view');

// STEP-BY-STEP SIMULATION
Route::post('/sap/step', [SAPController::class, 'step'])->name('sap.step');

// RUN ALL INSTRUCTIONS
Route::post('/sap/run-all', [SAPController::class, 'runAll'])->name('sap.runAll');

// UPLOAD EXCEL FILE
Route::get('/sap/upload', [SAPController::class, 'uploadForm'])->name('sap.upload.form');
Route::post('/sap/upload', [SAPController::class, 'upload'])->name('sap.upload');

// OPCODE EDIT INLINE (used in sap.blade.php)
Route::post('/sap/opcodes/update', [OpcodeController::class, 'update'])->name('sap.opcodes.update');
Route::get('/sap/opcodes', [OpcodeController::class, 'form'])->name('sap.opcodes.form');
Route::post('/sap/opcodes/add', [OpcodeController::class, 'add'])->name('sap.opcodes.add');

Route::post('/sap/clear-out', function () {
    session()->forget('out_display');
    return redirect()->route('sap.view');
})->name('sap.clear.out');
Route::post('/sap/reset', [SAPController::class, 'reset'])->name('sap.reset');

Route::post('/sap/flow/clear', [SAPController::class, 'clearFlow'])->name('sap.flow.clear');