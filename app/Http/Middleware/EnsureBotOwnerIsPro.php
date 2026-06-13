<?php

namespace App\Http\Middleware;

use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBotOwnerIsPro
{
    public function handle(Request $request, Closure $next): Response
    {
        $owner = app(BotOwnerAuthServiceInterface::class)->currentOwner();

        if (!$owner || !$owner->is_pro) {
            return redirect()->route('bot-owner.dashboard')
                ->with('error', trans('bot-owner.pro_required'));
        }

        return $next($request);
    }
}
