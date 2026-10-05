<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HeaderCheckController extends Controller
{
    public function check(Request $request)
    {
        return response()->json([
            'has_authorization' => $request->hasHeader('Authorization'),
            'bearer_token_present' => $request->bearerToken() !== null,
        ]);
    }
}  