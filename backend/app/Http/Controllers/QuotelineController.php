<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotelineRequest;
use App\Http\Requests\UpdateQuotelineRequest;
use App\Models\Part;
use App\Models\Quote;
use App\Models\Quoteline;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuotelineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Add a part to the quote, merging with an existing line of the same colour.
     */
    public function store(StoreQuotelineRequest $request, Quote $quote, QuoteService $quotes)
    {
        $validated = $request->validated();

        $quotes->addLine(
            $quote,
            Part::findOrFail($validated['partId']),
            $validated['quantity'],
            $validated['colour'] ?? null,
        );

        // Respond with the whole quote so the client never has to recompute totals
        return $quotes->present($quote);
    }

    /**
     * Display the specified resource.
     */
    public function show(Quoteline $quoteline)
    {
        //
    }

    /**
     * Change a line's quantity and/or colour.
     */
    public function update(UpdateQuotelineRequest $request, Quote $quote, Quoteline $quoteline, QuoteService $quotes)
    {
        $validated = $request->validated();

        $quotes->updateLine(
            $quoteline,
            $validated['quantity'] ?? $quoteline->quantity,
            array_key_exists('colour', $validated) ? $validated['colour'] : $quoteline->colour,
        );

        return $quotes->present($quote);
    }

    /**
     * Remove a line from the quote.
     */
    public function destroy(Request $request, Quote $quote, Quoteline $quoteline, QuoteService $quotes)
    {
        $this->ensureOwnQuote($request, $quote, $quoteline);

        $quotes->removeLine($quoteline);

        return $quotes->present($quote);
    }
}
