<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProPurchaseRequest;
use App\Services\ProServiceImpl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProPurchaseController extends Controller
{
    /**
     * تایید درخواست Pro از طریق لینک ایمیل
     */
    public function approve(Request $request, int $id)
    {
        try {
            $purchaseRequest = ProPurchaseRequest::findOrFail($id);
            
            if ($purchaseRequest->status !== 'pending') {
                return redirect('/nova/resources/pro-purchase-requests/' . $id)
                    ->with('error', 'این درخواست قبلاً پردازش شده است');
            }

            $proService = app(ProServiceImpl::class);
            $adminId = auth()->id() ?? 1; // اگر لاگین نبود، از ID 1 استفاده می‌کنیم
            
            $result = $proService->confirmPurchase($id, $adminId);

            if ($result) {
                Log::info('✅ [ProPurchase] Approved via email link', [
                    'request_id' => $id,
                    'admin_id' => $adminId,
                ]);

                return redirect('/nova/resources/pro-purchase-requests/' . $id)
                    ->with('success', 'درخواست با موفقیت تایید شد');
            } else {
                return redirect('/nova/resources/pro-purchase-requests/' . $id)
                    ->with('error', 'خطا در تایید درخواست');
            }
        } catch (\Exception $e) {
            Log::error('❌ [ProPurchase] Error approving via email link', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect('/nova/resources/pro-purchase-requests')
                ->with('error', 'خطا در پردازش درخواست: ' . $e->getMessage());
        }
    }

    /**
     * رد درخواست Pro از طریق لینک ایمیل
     */
    public function reject(Request $request, int $id)
    {
        try {
            $purchaseRequest = ProPurchaseRequest::findOrFail($id);
            
            if ($purchaseRequest->status !== 'pending') {
                return redirect('/nova/resources/pro-purchase-requests/' . $id)
                    ->with('error', 'این درخواست قبلاً پردازش شده است');
            }

            $purchaseRequest->status = 'rejected';
            $purchaseRequest->admin_notes = $request->input('reason', 'Rejected by admin via email link');
            $purchaseRequest->save();

            Log::info('❌ [ProPurchase] Rejected via email link', [
                'request_id' => $id,
                'admin_id' => auth()->id() ?? 1,
            ]);

            return redirect('/nova/resources/pro-purchase-requests/' . $id)
                ->with('success', 'درخواست رد شد');
        } catch (\Exception $e) {
            Log::error('❌ [ProPurchase] Error rejecting via email link', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect('/nova/resources/pro-purchase-requests')
                ->with('error', 'خطا در پردازش درخواست: ' . $e->getMessage());
        }
    }
}
