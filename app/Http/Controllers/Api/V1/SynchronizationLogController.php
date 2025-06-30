<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SynchronizationLogResource;
use App\Models\SynchronizationLog;
use Illuminate\Http\Request;

class SynchronizationLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Pobieramy logi posortowane od najnowszego
        $query = SynchronizationLog::latest();

        // Możliwość filtrowania po statusie
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Możliwość filtrowania po typie zasobu
        if ($request->has('resource_type')) {
            $query->where('resource_type', $request->query('resource_type'));
        }

        // Paginacja wyników
        $logs = $query->paginate(25);

        return SynchronizationLogResource::collection($logs);
    }

    /**
     * Display the specified resource.
     */
    public function show(SynchronizationLog $synchronizationLog)
    {
        return new SynchronizationLogResource($synchronizationLog);
    }
}
