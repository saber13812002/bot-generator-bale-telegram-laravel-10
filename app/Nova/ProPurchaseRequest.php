<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;

class ProPurchaseRequest extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\ProPurchaseRequest>
     */
    public static $model = \App\Models\ProPurchaseRequest::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'user_identifier',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Bot User', 'botUser', BotUsers::class)
                ->sortable()
                ->rules('required'),

            BelongsTo::make('Bot', 'bot', Bot::class)
                ->sortable()
                ->rules('required'),

            Text::make('User Identifier', 'user_identifier')
                ->sortable()
                ->rules('required', 'max:255'),

            Select::make('Payment Method', 'payment_method')
                ->options([
                    'card' => 'Card',
                    'crypto' => 'Crypto',
                    'other' => 'Other',
                ])
                ->displayUsingLabels()
                ->nullable(),

            Textarea::make('Payment Info', 'payment_info')
                ->nullable(),

            Select::make('Status', 'status')
                ->options([
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'rejected' => 'Rejected',
                ])
                ->displayUsingLabels()
                ->default('pending')
                ->sortable()
                ->rules('required'),

            Textarea::make('Admin Notes', 'admin_notes')
                ->nullable(),

            Text::make('Approved By', 'approved_by')
                ->nullable()
                ->readonly(),

            DateTime::make('Approved At', 'approved_at')
                ->nullable()
                ->sortable()
                ->readonly(),

            DateTime::make('Created At', 'created_at')
                ->sortable()
                ->readonly(),

            DateTime::make('Updated At', 'updated_at')
                ->sortable()
                ->readonly(),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            new Actions\ApproveProPurchase,
            new Actions\RejectProPurchase,
        ];
    }
}
