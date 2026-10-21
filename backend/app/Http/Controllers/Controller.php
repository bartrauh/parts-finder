<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Quoteline;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function ensureOwnQuote(Request $request, Quote $quote, ?Quoteline $line = null): void
    {
        abort_unless($quote->workshop_id === $request->workshop->id, 404);
        abort_if($line && $line->quote_id !== $quote->id, 404);
    }
}
