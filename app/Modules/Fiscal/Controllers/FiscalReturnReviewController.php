<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Fiscal\Models\FiscalReturnReview;
use App\Modules\Fiscal\Requests\StoreFiscalReturnReviewRequest;
use App\Modules\Fiscal\Services\FiscalReturnReviewService;
use App\Modules\Sales\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalReturnReviewController
{
    public function __construct(private readonly FiscalReturnReviewService $reviews) {}

    public function show(int $sale): View
    {
        $sale = $this->sale($sale);
        $review = $this->reviews->evaluate($sale);
        abort_unless($review['has_return'], 404);

        return view('fiscal.return-review', [
            'sale' => $sale,
            'review' => $review,
            'decisionLabels' => FiscalReturnReviewService::DECISION_LABELS,
        ]);
    }

    public function store(StoreFiscalReturnReviewRequest $request, int $sale): RedirectResponse
    {
        $sale = $this->sale($sale);
        $this->reviews->record($sale, $request->user(), $request->validated());

        return redirect()
            ->route('fiscal.returns.show', $sale->id)
            ->with('success', 'Revisão fiscal da devolução registrada. Qualquer mudança posterior tornará esta evidência desatualizada.');
    }

    public function dossier(int $sale, string $review): View
    {
        [$sale, $review] = $this->dossierContext($sale, $review);

        return view('fiscal.return-dossier', [
            'report' => $this->reviews->dossier($sale, $review),
            'sale' => $sale,
            'review' => $review,
        ]);
    }

    public function dossierJson(int $sale, string $review): StreamedResponse
    {
        [$sale, $review] = $this->dossierContext($sale, $review);
        $report = $this->reviews->dossier($sale, $review);
        $saleSlug = Str::slug($sale->code) ?: 'venda-'.$sale->id;
        $fileName = 'vetflow-devolucao-fiscal-'.$saleSlug.'-'.$review->ulid.'.json';

        return response()->streamDownload(
            fn () => print json_encode(
                $report,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ),
            $fileName,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function sale(int $id): Sale
    {
        return Sale::query()
            ->with(['clinic', 'items', 'events', 'fiscalDocuments', 'fiscalReturnReviews.reviewer'])
            ->findOrFail($id);
    }

    /** @return array{Sale, FiscalReturnReview} */
    private function dossierContext(int $saleId, string $reviewUlid): array
    {
        $sale = $this->sale($saleId);
        $review = $sale->fiscalReturnReviews()
            ->with('reviewer')
            ->where('ulid', $reviewUlid)
            ->firstOrFail();

        return [$sale, $review];
    }
}
