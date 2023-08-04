<?php

namespace App\Http\Controllers;

use Amenadiel\JpGraph\Graph;
use Amenadiel\JpGraph\Plot;
use App\Helpers\BotHelper;
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

//        dd($language, $origin, $chatId);

        $query = BotLog::whereLanguage($language)
            ->select('chat_id', 'created_at')
            ->whereCommandType('quran')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereChatId($chatId)
            ->where('created_at', '>=', now()->subDays(8));

        $sql = 'daily activity : ' . $query->toSql();

        BotHelper::sendMessageToSuperAdmin($sql, 'bale');
        BotHelper::sendMessageToSuperAdmin($sql, 'telegram');
//        dd($sql);

//        dd($query->build());
        $all = $query->where('created_at', '>=', now()->subDays(7))
            ->get()->count();

        $graph = new Graph\PieGraph(350, 250);

        if ($all > 0) {

            $today = $query->where('created_at', '>=', now()->subDays())
                ->get()->count();


            $yesterday = $query->where('created_at', '>=', now()->subDays(2))
                ->where('created_at', '<', now()->subDays())
                ->get()->count();

            $aDayBeforeYesterday = $query->where('created_at', '>=', now()->subDays(3))
                ->where('created_at', '<', now()->subDays(2))
                ->get()->count();

            $twoDaysBeforeYesterday = $query->where('created_at', '>=', now()->subDays(4))
                ->where('created_at', '<', now()->subDays(3))
                ->get()->count();

            $threeDaysBeforeYesterday = $query->where('created_at', '>=', now()->subDays(5))
                ->where('created_at', '<', now()->subDays(4))
                ->get()->count();

            $fourDaysBeforeYesterday = $query->where('created_at', '>=', now()->subDays(6))
                ->where('created_at', '<', now()->subDays(5))
                ->get()->count();

            $fiveDaysBeforeYesterday = $query->where('created_at', '>=', now()->subDays(7))
                ->where('created_at', '<', now()->subDays(6))
                ->get()->count();


            // Create the Pie Graph.
//        dd($all, $fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
            $data = array($fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
            $graph->title->Set("Your last 7 days readings");
        } else {
            $data = array(1, 0);
            $graph->title->Set("No data available for you in last 7 days");
        }
        $graph->SetBox(true);
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
