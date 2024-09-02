<?php

namespace App\Http\Controllers;

use App\Models\SharabeBeheshtiMp3;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSharabeBeheshtiMp3Request;
use App\Http\Requests\UpdateSharabeBeheshtiMp3Request;

class SharabeBeheshtiMp3Controller extends Controller
{
    public static function getMp3Url(mixed $postLink)
    {
        $id = self::getId($postLink);

        if ($id < 89 && $id > 0) {
            $sharab = SharabeBeheshtiMp3::find($id);
            return $sharab->link;
        }
    }

    /**
     * @param mixed $postLink
     * @param $params
     * @return null
     */
    public static function getId(mixed $postLink): null
    {
//        $url = "sharabebeheshti.ir/shb5?random_id=6838&id=63&utm_source=saber&utm_medium=messenger&utm_campaign=campaign_khoda&utm_term=term_zohoor&utm_content=emamzaman";

// تجزیه URL و استخراج کوئری استرینگ
        $queryString = parse_url($postLink, PHP_URL_QUERY);

// تبدیل کوئری استرینگ به آرایه
        parse_str($queryString, $params);

// استخراج مقدار id
        return isset($params['id']) ? $params['id'] : null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSharabeBeheshtiMp3Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SharabeBeheshtiMp3 $sharabeBeheshtiMp3)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SharabeBeheshtiMp3 $sharabeBeheshtiMp3)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSharabeBeheshtiMp3Request $request, SharabeBeheshtiMp3 $sharabeBeheshtiMp3)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SharabeBeheshtiMp3 $sharabeBeheshtiMp3)
    {
        //
    }
}
