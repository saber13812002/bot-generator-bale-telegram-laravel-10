<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use App\Modules\BotOwner\Services\BotOwnerProService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotOwnerProController extends Controller
{
    public function approve(Request $request, int $id): RedirectResponse
    {
        try {
            $proRequest = BotOwnerProRequest::findOrFail($id);

            if ($proRequest->status !== 'pending') {
                return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                    ->with('error', trans('bot-owner.pro_already_processed'));
            }

            $months = (int) $request->query('months', 3);
            [$valid] = BotOwnerProService::validateMonths($months);
            if (!$valid) {
                return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                    ->with('error', trans('bot-owner.pro_invalid_months'));
            }

            $proService = app(BotOwnerProServiceInterface::class);
            $adminId = auth()->id() ?? 1;

            if ($proService->confirmPro($id, $adminId, $months)) {
                Log::info('BotOwner Pro approved via web link', [
                    'request_id' => $id,
                    'months' => $months,
                ]);

                return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                    ->with('success', trans('bot-owner.pro_approved'));
            }

            return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                ->with('error', trans('bot-owner.pro_approve_failed'));
        } catch (\Exception $e) {
            Log::error('BotOwner Pro approve error', ['request_id' => $id, 'error' => $e->getMessage()]);

            return redirect('/nova/resources/bot-owner-pro-requests')
                ->with('error', $e->getMessage());
        }
    }

    public function reject(int $id): RedirectResponse
    {
        try {
            $proRequest = BotOwnerProRequest::findOrFail($id);

            if ($proRequest->status !== 'pending') {
                return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                    ->with('error', trans('bot-owner.pro_already_processed'));
            }

            app(BotOwnerProServiceInterface::class)->rejectPro($id, 'Rejected via web link');

            return redirect('/nova/resources/bot-owner-pro-requests/' . $id)
                ->with('success', trans('bot-owner.pro_rejected'));
        } catch (\Exception $e) {
            return redirect('/nova/resources/bot-owner-pro-requests')
                ->with('error', $e->getMessage());
        }
    }
}
