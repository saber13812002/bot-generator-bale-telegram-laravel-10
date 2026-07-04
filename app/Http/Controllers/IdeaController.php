<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class IdeaController extends Controller
{
    public function create(Request $request): View
    {
        $endpointId = $request->query('bot');
        return view('idea.create', compact('endpointId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint_id' => 'nullable|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'submitter_name' => 'nullable|string|max:100',
            'submitter_contact' => 'nullable|string|max:200',
        ]);

        Idea::create($validated);

        Log::info('💡 New idea submitted', [
            'endpoint_id' => $validated['endpoint_id'] ?? null,
            'title' => $validated['title'],
        ]);

        return redirect()->back()->with('success', '✅ ایده شما با موفقیت ثبت شد. از مشارکت شما سپاسگزاریم!');
    }

    public function thanks(): View
    {
        return view('idea.thanks');
    }
}
