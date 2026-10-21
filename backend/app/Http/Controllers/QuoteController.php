<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes)
    {
    }

    /**
     * Create an empty draft quote for the workshop.
     */
    public function store(Request $request)
    {
        $quote = $request->workshop->quotes()->create(['status' => 'draft']);

        return response()->json($this->quotes->present($quote), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote, Request $request)
    {
        $this->ensureOwnQuote($request, $quote);

        return $this->quotes->present($quote);
    }

    public function submit(Quote $quote, Request $request)
    {
        $this->ensureOwnQuote($request, $quote);

        return $this->quotes->present($this->quotes->submit($quote));
    }
}
