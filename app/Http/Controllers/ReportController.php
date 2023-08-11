<?php

namespace App\Http\Controllers;

use Amenadiel\JpGraph\Graph;
use Amenadiel\JpGraph\Plot;
use App\Helpers\BotHelper;
use App\Models\BotLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Telegram;

class ReportController extends Controller
{
    /**
     * @throws Exception
     */
    public function dailyActivity(Request $request)
    {
        $chatId = $request->input('chat_id');

        $language = $request->input('language');
        $language = $language ?? "fa";

        $origin = $request->input('origin');
        $origin = $origin ?? "bale";

        $baseQuery = BotLog::whereLanguage($language)
            ->select('chat_id', 'created_at')
            ->whereCommandType('quran')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereChatId($chatId);


        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days readings");

        $this->getGraph($graph, 'last 7 days readings');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();


        $second = $request->input('second');
        if ($second != "true") {
            $bot = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_BALE'), 'bale');
            if ($origin == 'telegram')
                $bot = new Telegram(env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM'), 'telegram');

            $fullUrl = $request->fullUrl();

            BotHelper::sendMessageToSuperAdmin($fullUrl, 'bale');

            BotHelper::sendPhoto($chatId, $fullUrl . "&second=true", "", $bot);
        }

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }


//$sql = $query->toSql();
//$bindings = $query->getBindings();
//
//// Replace the question marks with the actual values
//$fullQuery = str_replace('?', "'%s'", $sql);
//$fullQuery = vsprintf($fullQuery, $bindings);
////        dd($fullQuery);
//$fullQuery = 'daily activity : ' . $fullQuery;
//
//BotHelper::sendMessageToSuperAdmin($fullQuery, 'bale');
//BotHelper::sendMessageToSuperAdmin($fullQuery, 'telegram');


    public function dailySearch(Request $request)
    {
        $chatId = $request->input('chat_id');

        $language = $request->input('language');
        $language = $language ?? "fa";

        $origin = $request->input('origin');
        $origin = $origin ?? "bale";

        $baseQuery = BotLog::whereLanguage($language)
            ->select('chat_id', 'created_at')
            ->whereCommandType('quran_search')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereChatId($chatId);


        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days searches");

        $this->getGraph($graph, 'last 7 days searches');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }

    public function dailyNewUsers(Request $request)
    {
        $baseQuery = BotLog::select('created_at')
            ->whereCommandType('quran')
            ->whereText('/start')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word');


        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days new users");

        $this->getGraph($graph, 'last 7 days new users');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }

    public function dailyReferral(Request $request)
    {

        $chatId = $request->input('chat_id');

        $language = $request->input('language');
        $language = $language ?? "fa";

        $origin = $request->input('origin');
        $origin = $origin ?? "bale";

        $baseQuery = BotLog::whereLanguage($language)
            ->select('created_at')
            ->whereCommandType('quran')
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereText('/start');

        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days new referral");

        $this->getGraph($graph, 'last 7 days new referral');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }

    public function dailyRecite(Request $request)
    {

        $chatId = $request->input('chat_id');

        $language = $request->input('language');
        $language = $language ?? "fa";

        $origin = $request->input('origin');
        $origin = $origin ?? "bale";

        $baseQuery = BotLog::whereLanguage($language)
            ->select('chat_id', 'created_at')
            ->whereCommandType('quran')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereType($origin)
            ->whereChatId($chatId);

        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days recites");

        $this->getGraph($graph, 'last 7 days recites');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }

    public function dailyActiveUsers(Request $request)
    {

        $baseQuery = BotLog::select('created_at')
            ->whereCommandType('quran')
//            ->select(DB::raw('DATE(created_at) as date'), 'chat_id', 'type', DB::raw('COUNT(*) as count'))
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->where('created_at', '>=', now()->subDays(7));

        $graph = new Graph\Graph(350, 250);

        $data = $this->getDataFor7Days($baseQuery);

        $graph->title->Set("Your last 7 days all users activities");

        $this->getGraph($graph, 'last 7 days all users activities');

        // Create the plot line
        $p1 = new Plot\LinePlot($data);
        $graph->Add($p1);

        ob_start();
        $graph->Stroke();
        $image_data = ob_get_contents();
        ob_end_clean();

        return new Response($image_data, 200, ['Content-Type' => 'image/png',]);
    }

    /**
     * @param Graph\Graph $graph
     * @return void
     */
    public function getGraph(Graph\Graph $graph, string $title): void
    {
        $graph->SetScale('intlin');
        $graph->SetMargin(30, 15, 40, 30);
        $graph->SetMarginColor('white');
        $graph->SetFrame(true, 'blue', 3);

        $graph->title->SetFont(FF_ARIAL, FS_BOLD, 12);

        $graph->subtitle->SetFont(FF_ARIAL, FS_NORMAL, 10);
        $graph->subtitle->SetColor('darkred');

        $graph->subtitle->Set($title);

        $graph->SetAxisLabelBackground(LABELBKG_NONE, 'orange', 'red', 'lightblue', 'red');

        // Use Ariel font
        $graph->xaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
        $graph->yaxis->SetFont(FF_ARIAL, FS_NORMAL, 9);
        $graph->xgrid->Show();
    }

    /**
     * @param $baseQuery
     * @return array
     */
    public function getDataFor7Days($baseQuery): array
    {
        $startDate = Carbon::yesterday()->startOfDay(); // تاریخ 12 شب پریشب
        $endDate = Carbon::today()->endOfDay(); // تاریخ 12 شب دیشب

        $query = clone $baseQuery;

        $today = $query->whereBetween('created_at', [$startDate, $endDate])
            ->get()->count();


        $startDate = Carbon::yesterday()->subDays(1)->startOfDay(); // تاریخ 12 شب پریشب
        $endDate = Carbon::today()->subDays(1)->endOfDay(); // تاریخ 12 شب دیشب

        $query = clone $baseQuery;

        $yesterday = $query->whereBetween('created_at', [$startDate->subDays(2), $endDate->subDays(2)])
            ->get()->count();

        $query = clone $baseQuery;

        $aDayBeforeYesterday = $query->whereBetween('created_at', [$startDate->subDays(3), $endDate->subDays(3)])
            ->get()->count();

        $query = clone $baseQuery;

        $twoDaysBeforeYesterday = $query->whereBetween('created_at', [$startDate->subDays(4), $endDate->subDays(4)])
            ->get()->count();

        $query = clone $baseQuery;

        $threeDaysBeforeYesterday = $query->whereBetween('created_at', [$startDate->subDays(5), $endDate->subDays(5)])
            ->get()->count();

        $query = clone $baseQuery;

        $fourDaysBeforeYesterday = $query->whereBetween('created_at', [$startDate->subDays(6), $endDate->subDays(6)])
            ->get()->count();

        $query = clone $baseQuery;

        $fiveDaysBeforeYesterday = $query->whereBetween('created_at', [$startDate->subDays(7), $endDate->subDays(7)])
            ->get()->count();


        // Create the Pie Graph.
        //        dd($all, $fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
        $data = array($fiveDaysBeforeYesterday, $fourDaysBeforeYesterday, $threeDaysBeforeYesterday, $twoDaysBeforeYesterday, $aDayBeforeYesterday, $yesterday, $today);
        return $data;
    }
}
