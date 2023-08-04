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

        $graph = new Graph\Graph(350, 250);

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

        $graph->SetScale('intlin');
        $graph->SetMargin(30, 15, 40, 30);
        $graph->SetMarginColor('white');
        $graph->SetFrame(true, 'blue', 3);

//        $graph->title->Set('Label background');
        $graph->title->SetFont(FF_ARIAL, FS_BOLD, 12);

        $graph->subtitle->SetFont(FF_ARIAL, FS_NORMAL, 10);
        $graph->subtitle->SetColor('darkred');
        $graph->subtitle->Set('last 7 days readings');

        $graph->SetAxisLabelBackground(LABELBKG_NONE, 'orange', 'red', 'lightblue', 'red');

// Use Ariel font
        $graph->xaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
        $graph->yaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
        $graph->xgrid->Show();

// Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

// Output graph
        $graph->Stroke();

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }
}
