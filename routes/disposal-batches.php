<?php

use App\Http\Controllers\DisposalBatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:disposal-management'])
    ->group(function (): void {

        Route::get('disposal-batches', [DisposalBatchController::class, 'index'])
            ->name('disposal-batches.index');

        Route::get('disposal-batches/{batch}', [DisposalBatchController::class, 'show'])
            ->name('disposal-batches.show');

        Route::patch('disposal-batches/{batch}', [DisposalBatchController::class, 'update'])
            ->name('disposal-batches.update');

        Route::post('disposal-batches/{batch}/complete', [DisposalBatchController::class, 'complete'])
            ->name('disposal-batches.complete');

        Route::get('disposal-batches/{batch}/available-files', [DisposalBatchController::class, 'availableFiles'])
            ->name('disposal-batches.available-files');

        Route::post('disposal-batches/{batch}/add-files', [DisposalBatchController::class, 'addFiles'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.add-files');

        Route::delete('disposal-batches/{batch}/remove-file/{fileId}', [DisposalBatchController::class, 'removeFile'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.remove-file');

        Route::delete('disposal-batches/{batch}', [DisposalBatchController::class, 'destroy'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.destroy');

        Route::post('disposal-batches/{batch}/schedule-inspection', [DisposalBatchController::class, 'scheduleInspection'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.schedule-inspection');

        Route::post('disposal-batches/{batch}/mark-as-inspected', [DisposalBatchController::class, 'markAsInspected'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.mark-as-inspected');

        Route::post('disposal-batches/{batch}/nap-authority', [DisposalBatchController::class, 'confirmNapAuthority'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-authority.confirm');

        Route::post('disposal-batches/{batch}/report-submission', [DisposalBatchController::class, 'confirmCoaSubmission'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.report-submission.confirm');

        Route::post('disposal-batches/{batch}/approve', [DisposalBatchController::class, 'approve'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.approve');

        Route::get('disposal-batches/{batch}/endorsement-letter/finalize', [DisposalBatchController::class, 'endorsementFinalize'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.endorsement.finalize');

        Route::post('disposal-batches/{batch}/endorsement-letter/finalize', [DisposalBatchController::class, 'endorsementFinalizeSubmit'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.endorsement.finalize.submit');

        Route::post('disposal-batches/{batch}/reject', [DisposalBatchController::class, 'reject'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.reject');

        Route::post('disposal-batches/{batch}/return-to-draft', [DisposalBatchController::class, 'returnToDraft'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.return-to-draft');

        Route::get('disposal-batches/{batch}/nap-form-1/preview', [DisposalBatchController::class, 'napForm1Preview'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-1.preview');

        Route::post('disposal-batches/{batch}/nap-form-1/generate', [DisposalBatchController::class, 'napForm1Generate'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-1.generate');

        Route::get('disposal-batches/{batch}/nap-form-1/download', [DisposalBatchController::class, 'napForm1Download'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-1.download');

        Route::get('disposal-batches/{batch}/nap-form-3/preview', [DisposalBatchController::class, 'napForm3Preview'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-3.preview');

        Route::post('disposal-batches/{batch}/nap-form-3/generate', [DisposalBatchController::class, 'napForm3Generate'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-3.generate');

        Route::get('disposal-batches/{batch}/nap-form-3/download', [DisposalBatchController::class, 'napForm3Download'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.nap-form-3.download');

        Route::get('disposal-batches/{batch}/request-letter/preview', [DisposalBatchController::class, 'requestLetterPreview'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.request-letter.preview');

        Route::post('disposal-batches/{batch}/request-letter/generate', [DisposalBatchController::class, 'requestLetterGenerate'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.request-letter.generate');

        Route::get('disposal-batches/{batch}/request-letter/download', [DisposalBatchController::class, 'requestLetterDownload'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.request-letter.download');

        Route::post('disposal-batches/{batch}/endorsement-letter/preview', [DisposalBatchController::class, 'approvalLetterPreview'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.endorsement-letter.preview');

        Route::get('disposal-batches/{batch}/endorsement-letter/download', [DisposalBatchController::class, 'approvalLetterDownload'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.endorsement-letter.download');

        Route::post('disposal-batches/{batch}/endorsement-letter/generate', [DisposalBatchController::class, 'approvalLetterGenerate'])
            ->middleware(['auth', 'verified'])
            ->name('disposal-batches.endorsement-letter.generate');

    });
