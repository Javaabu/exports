<?php

namespace Javaabu\Exports\Tests\TestSupport\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Javaabu\Exports\Http\Controllers\ExportsController;

class CustomExportsController extends ExportsController
{
    public function index(Request $request): View|Responsable|JsonResponse
    {
        return response()->json(['custom' => true]);
    }
}
