<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DeskReferenceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->to('dashboard'));

Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');

Route::get('/api/search/spotlight', [SearchController::class, 'spotlight'])
    ->middleware('auth')
    ->name('search.spotlight');

Route::get('/api/search/chain', [SearchController::class, 'chain'])
    ->middleware('auth')
    ->name('search.chain');

Route::get('/workspace/records/{resource}', [WorkspaceController::class, 'records'])
    ->middleware('auth')
    ->name('workspace.records');

Route::get('/shipments/{shipment}/invoice/pdf', [InvoiceController::class, 'shipmentPdf'])
    ->middleware('auth')
    ->name('shipments.invoice.pdf');

Route::post('/desk-reference/acknowledge', [DeskReferenceController::class, 'acknowledge'])
    ->middleware('auth')
    ->name('desk-reference.acknowledge');

Route::fallback(fn () => view('errors.404'));
