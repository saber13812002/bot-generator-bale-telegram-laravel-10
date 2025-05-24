<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Http\Requests\NovaRequest;

class VoiceUser extends Resource
{
    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'صوت';

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\VoiceUser>
     */
    public static $model = \App\Models\VoiceUser::class;

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'voice-users';
    }
    
    public static $title = 'alias_name';
    
    public static $search = [
        'id', 'chat_id', 'alias_name'
    ];
    
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            
            Text::make('چت آیدی', 'chat_id')
                ->sortable(),
                
            Text::make('شناسه ربات', 'bot_id')
                ->sortable(),
                
            Select::make('وضعیت', 'status')
                ->options([
                    'suspend' => 'معلق',
                    'active' => 'فعال'
                ])
                ->displayUsingLabels(),
                
            Select::make('پیام رسان', 'origin')
                ->options([
                    'bale' => 'بله',
                    'telegram' => 'تلگرام', 
                    'gap' => 'گپ',
                    'soroosh' => 'سروش'
                ])
                ->displayUsingLabels(),
                
            Text::make('نام مستعار', 'alias_name')
                ->nullable(),
                
            Code::make('تنظیمات', 'settings')
                ->json()
                ->nullable(),
                
            BelongsToMany::make('پروژه‌ها', 'projects', Projects::class)
                ->fields(function () {
                    return [
                        Select::make('وضعیت', 'status')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غیرفعال'
                            ]),
                        Code::make('تنظیمات', 'settings')
                            ->json()
                            ->nullable()
                    ];
                }),
        ];
    }
} 