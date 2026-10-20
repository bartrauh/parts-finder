<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'q' => 'string|nullable',
                'brand' => 'string|nullable',
                'condition' => 'string|nullable',
                'per_page' => 'integer|min:1|max:50',
                'sort' => ['string', 'nullable', 'regex:/^.+_.+$/i'],
            ]
        );

        if($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }
        $validated = $validator->validated();
        return Part::query()
            ->when($validated['q'] ?? null, fn($query, $q) => $query->where('name', 'LIKE', '%' . $q . '%')->orWhere('oem', 'LIKE', '%' . $q . '%'))
            ->when($validated['brand'] ?? null, fn($query, $q) => $query->where('name', 'LIKE', '%' . $q . '%'))
            ->when($validated['condition'] ?? null, fn($query, $q) => $query->where('condition', 'LIKE', $q))
            ->when($validated['sort'] ?? null, function($query, $q) {
                $sort = explode('_', $q);
                $query->orderBy($sort[0], $sort[1]);
            })
            ->paginate($validated['per_page'] ?? 50)->withQueryString();
    }

    /**
     * Display the specified resource.
     */
    public function show(Part $part)
    {
        return $part;
    }

    public function brands(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'q' => 'string|nullable',
                'condition' => 'string|nullable',
            ]
        );

        if($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }
        $validated = $validator->validated();
        return Part::query()
            ->when($validated['q'] ?? null, fn($query, $q) => $query->where('name', 'LIKE', '%' . $q . '%')->orWhere('oem', 'LIKE', '%' . $q . '%'))
            ->when($validated['condition'] ?? null, fn($query, $q) => $query->where('condition', 'LIKE', $q))
            ->groupBy('brand')->pluck('brand');
    }
}
