<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
                ->withAuthenticationRoutes()
                ->withPasswordResetRoutes()
                ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array
     */
    protected function dashboards()
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    protected function resources()
    {
        Nova::resources([
            \App\Nova\User::class,
            \App\Nova\Bot::class,
            \App\Nova\BlogUser::class,
            \App\Nova\BotHadithItem::class,
            \App\Nova\BotKid::class,
            \App\Nova\BotLog::class,
            \App\Nova\BotUploadedBankFile::class,
            \App\Nova\BotUsers::class,
            \App\Nova\LaravelFulltext::class,
            \App\Nova\Projects::class,
            \App\Nova\QuranScanPage::class,
            \App\Nova\QuranSurah::class,
            \App\Nova\QuranTranslation::class,
            \App\Nova\RssBusiness::class,
            \App\Nova\RssBusinessUserAdmins::class,
            \App\Nova\RssChannel::class,
            \App\Nova\RssChannelOrigin::class,
            \App\Nova\RssFeedWebOrigin::class,
            \App\Nova\RssItem::class,
            \App\Nova\RssPostItem::class,
            \App\Nova\RssPostItemTranslation::class,
            \App\Nova\RssPostItemTranslationQueue::class,
            \App\Nova\RssSocialPost::class,
            \App\Nova\SharabeBeheshtiMp3::class,
            \App\Nova\SongsaraPost::class,
            \App\Nova\TranslationPublish::class,
            \App\Nova\VoiceUser::class,
            \App\Nova\Projects::class,
            
        ]);
    }
}
