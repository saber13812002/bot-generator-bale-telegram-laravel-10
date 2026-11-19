<?php

namespace App\Http\Middleware;

use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBotOwnerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = app(BotOwnerAuthServiceInterface::class);
        if (!$auth->currentOwner()) {
            return redirect()->route('bot-owner.login')
                ->with('error', trans('bot-owner.login_required'));
        }

        return $next($request);
    }
}
