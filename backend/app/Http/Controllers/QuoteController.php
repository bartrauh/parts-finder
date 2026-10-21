<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;

class QuoteController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        $quote = new Quote(['totals' => ['total' => 0, 'btw' => 0, 'subtotal' => 0], 'status' => 'draft']);
        $request->workshop->quotes()->save($quote);

        return $quote;
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote, Request $request)
    {
        if($quote->workshop_id != $request->workshop->id) {
            abort(404);
        }
        return $request->workshop->quotes()->findOrFail($quote->id);
    }

    public function submit(Quote $quote)
    {
        // submit
    }
}
