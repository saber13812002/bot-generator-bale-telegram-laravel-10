<?php

namespace App\Http\Controllers;

use App\Services\LogViewerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogViewerController extends Controller
{
    public function index(Request $request, LogViewerService $viewer): Response|\Illuminate\Contracts\View\View
    {
        $secret = (string) config('observability.viewer_secret', '');
        if ($secret === '') {
            abort(404);
        }

        $botId = $request->filled('bot_id') ? (int) $request->query('bot_id') : null;
        if ($botId !== null && $botId < 1) {
            $botId = null;
        }

        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min(1000, $limit));
        $source = $request->query('source', 'db');
        $format = $request->query('format');

        $logs = $source === 'file'
            ? collect()
            : $viewer->latestLogs($botId, $limit);

        $fileTail = $source === 'file'
            ? $viewer->tailLogFile($limit, $botId)
            : '';

        $text = $source === 'file'
            ? $fileTail
            : $viewer->formatLogsAsText($logs);

        if ($format === 'text') {
            return response($text, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return view('observability.log-viewer', [
            'botId' => $botId,
            'limit' => $limit,
            'source' => $source === 'file' ? 'file' : 'db',
            'logs' => $logs,
            'fileTail' => $fileTail,
            'copyText' => $text,
            'health' => $viewer->healthSummary($botId),
        ]);
    }
}
