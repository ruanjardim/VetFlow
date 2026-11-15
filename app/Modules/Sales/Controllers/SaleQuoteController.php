<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Requests\StoreSaleQuoteRequest;
use App\Modules\Sales\Services\SaleQuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleQuoteController
{
    public function __construct(private readonly SaleQuoteService $quotes) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'expired', 'converted', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim((string) ($filters['q'] ?? ''));

        $quotes = SaleQuote::query()
            ->with(['tutor', 'patient', 'convertedSale'])
            ->withDisplayStatus($filters['status'] ?? null)
            ->when($term !== '', function ($query) use ($term) {
                $pattern = '%'.addcslashes($term, '%_\\').'%';

                $query->where(function ($query) use ($pattern) {
                    $query->where('code', 'like', $pattern)
                        ->orWhereHas('tutor', fn ($tutor) => $tutor->where('name', 'like', $pattern))
                        ->orWhereHas('patient', fn ($patient) => $patient->where('name', 'like', $pattern));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.quotes.index', [
            'quotes' => $quotes,
            'filters' => $filters,
        ]);
    }

    public function store(StoreSaleQuoteRequest $request): RedirectResponse
    {
        $quote = $this->quotes->create($request->validated());

        return redirect()
            ->route('sales.quotes.show', $quote->id)
            ->with('success', 'Orçamento '.$quote->code.' salvo.');
    }

    public function show(int $id): View
    {
        $quote = SaleQuote::query()
            ->with(['clinic', 'tutor', 'patient', 'seller', 'items', 'convertedSale'])
            ->findOrFail($id);

        return view('sales.quotes.show', [
            'quote' => $quote,
            'whatsappUrl' => $this->quotes->whatsappUrl($quote),
            'activeSale' => $quote->sales()->where('status', '!=', 'cancelled')->latest('id')->first(),
        ]);
    }

    public function update(StoreSaleQuoteRequest $request, int $id): RedirectResponse
    {
        $quote = SaleQuote::query()->findOrFail($id);
        $quote = $this->quotes->update($quote, $request->validated());

        return redirect()
            ->route('sales.quotes.show', $quote->id)
            ->with('success', 'Orçamento '.$quote->code.' atualizado.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $quote = SaleQuote::query()->findOrFail($id);
        $this->quotes->cancel($quote, $validated['reason'] ?? null);

        return redirect()
            ->route('sales.quotes.show', $quote->id)
            ->with('success', 'Orçamento '.$quote->code.' cancelado.');
    }
}
