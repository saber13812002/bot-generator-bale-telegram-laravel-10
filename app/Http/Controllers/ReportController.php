<?php

namespace App\Http\Controllers;

use Amenadiel\JpGraph\Graph;
use Amenadiel\JpGraph\Plot;
use App\Models\BotLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function dailyActivity(Request $request)
    {

        $chatId = $request->input('chat_id');

        $language = $request->input('language');
        $language = $language ?? "fa";

        $origin = $request->input('origin');
        $origin = $origin ?? "bale";

        $logs = BotLog::whereLanguage($language)
            ->select('chat_id', 'created_at')
            ->whereCommandType('quran')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereChatId($chatId)
            ->distinct('chat_id')
            ->where('created_at', '>=', now()->subDays(8));

        $all = $logs->where('created_at', '>=', now()->subDays(7))
            ->get()->count();
        $all = $all == 0 ? 1 : $all;

        $today = $logs->where('created_at', '>=', now()->subDays())
            ->get()->count() ?? 1;


        $yesterday = $logs->where('created_at', '>=', now()->subDays(2))
            ->where('created_at', '<', now()->subDays())
            ->get()->count() ?? 1;

        $aDayBeforeYesterday = $logs->where('created_at', '>=', now()->subDays(3))
            ->where('created_at', '<', now()->subDays(2))
            ->get()->count() ?? 1;

        $twoDaysBeforeYesterday = $logs->where('created_at', '>=', now()->subDays(4))
            ->where('created_at', '<', now()->subDays(3))
            ->get()->count() ?? 1;

        $threeDaysBeforeYesterday = $logs->where('created_at', '>=', now()->subDays(5))
            ->where('created_at', '<', now()->subDays(4))
            ->get()->count() ?? 1;

        $fourDaysBeforeYesterday = $logs->where('created_at', '>=', now()->subDays(6))
            ->where('created_at', '<', now()->subDays(5))
            ->get()->count() ?? 1;

        $fiveDaysBeforeYesterday = $logs->where('created_at', '>=', now()->subDays(7))
            ->where('created_at', '<', now()->subDays(6))
            ->get()->count() ?? 1;


        // Create the Pie Graph.
        $graph = new Graph\PieGraph(350, 250);
        $graph->title->Set("Your last 7 days readings");
        $graph->SetBox(true);
//        dd($fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
        $data = array($all, $fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
        $p1 = new Plot\PiePlot($data);
        $p1->ShowBorder();
        $p1->SetColor('black');
        $p1->SetSliceColors(array('#1E90FF', '#2E8B57', '#ADFF2F', '#DC143C', '#BA55D3'));

        $graph->Add($p1);
        $graph->Stroke();

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }
}
