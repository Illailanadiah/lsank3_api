<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankDistrict;
use App\Models\LsankPostcode;
use Illuminate\Http\Request;

class KedahAddressController extends Controller
{
    public function districts()
    {
        $districts = LsankDistrict::query()
            ->where('status', 'active')
            ->orderBy('district_id')
            ->get([
                'district_id',
                'district_name',
                'state_name',
                'postcode_prefix',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'District list retrieved successfully.',
            'data' => $districts,
        ]);
    }

    public function cities(Request $request)
    {
        $request->validate([
            'district_id' => ['nullable', 'integer', 'exists:lsank_districts,district_id'],
            'district_name' => ['nullable', 'string'],
        ]);

        $query = LsankPostcode::query()
            ->with('district:district_id,district_name,state_name')
            ->where('lsank_postcodes.status', 'active');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->filled('district_name')) {
            $query->whereHas('district', function ($q) use ($request) {
                $q->where('district_name', $request->district_name);
            });
        }

        $cities = $query
            ->orderBy('city_name')
            ->orderBy('postcode')
            ->get([
                'postcode_id',
                'district_id',
                'city_name',
                'postcode',
                'state_name',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'City and postcode list retrieved successfully.',
            'data' => $cities,
        ]);
    }

    public function resolve(Request $request)
    {
        $request->validate([
            'district_id' => ['nullable', 'integer', 'exists:lsank_districts,district_id'],
            'district_name' => ['nullable', 'string'],
            'city_name' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string'],
        ]);

        $query = LsankPostcode::query()
            ->with('district:district_id,district_name,state_name,postcode_prefix')
            ->where('lsank_postcodes.status', 'active');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->filled('district_name')) {
            $query->whereHas('district', function ($q) use ($request) {
                $q->where('district_name', $request->district_name);
            });
        }

        if ($request->filled('city_name')) {
            $query->where('city_name', $request->city_name);
        }

        if ($request->filled('postcode')) {
            $query->where('postcode', $request->postcode);
        }

        $result = $query
            ->orderBy('city_name')
            ->orderBy('postcode')
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address resolved successfully.',
            'data' => [
                'district_id' => $result->district?->district_id,
                'district_name' => $result->district?->district_name,
                'city_name' => $result->city_name,
                'postcode' => $result->postcode,
                'state_name' => $result->state_name,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'keyword' => ['required', 'string'],
        ]);

        $keyword = $request->keyword;

        $results = LsankPostcode::query()
            ->with('district:district_id,district_name,state_name')
            ->where('lsank_postcodes.status', 'active')
            ->where(function ($query) use ($keyword) {
                $query->where('city_name', 'like', "%{$keyword}%")
                    ->orWhere('postcode', 'like', "%{$keyword}%")
                    ->orWhereHas('district', function ($q) use ($keyword) {
                        $q->where('district_name', 'like', "%{$keyword}%");
                    });
            })
            ->orderBy('city_name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Search result retrieved successfully.',
            'data' => $results,
        ]);
    }
}