<?php

namespace App\Http\Controllers;

use Amenadiel\JpGraph\Graph;
use Amenadiel\JpGraph\Plot;
use Illuminate\Http\Response;

class ChartController extends Controller
{
    public function chart()
    {
        // Create the Pie Graph.
        $graph = new Graph\PieGraph(350, 250);
        $graph->title->Set("A Simple Pie Plot");
        $graph->SetBox(true);

        $data = array(40, 21, 17, 14, 23);
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
