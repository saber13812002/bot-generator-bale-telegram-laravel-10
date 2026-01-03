<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contribution;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContributionRequest;
use App\Http\Requests\UpdateContributionRequest;

class ContributionController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function design()
    {
        $totalContributions = 1326; // Example data
        $mostContributions = 5; // Example data
        $mostContributionsDate = 'Oct 3, 2022'; // Example data
        $longestStreak = 4; // Example data
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'jhh']; // Example months

        return view('contributions.contribute', compact('totalContributions', 'mostContributions', 'mostContributionsDate', 'longestStreak', 'months'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function calendarData()
    {
        $data = ActivityLog::select('date', \DB::raw('SUM(page_count) as total'))
            ->groupBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->total,
                ];
            });

        // Check if data is empty
        if ($data->isEmpty()) {
            return response()->json(['message' => 'No data available', 'data' => []]);
        }

        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     */

    public function calendar()
    {
        return view('contributions.calendar');
    }

}
