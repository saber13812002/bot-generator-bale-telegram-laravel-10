<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Interfaces\Services\ProService;
use App\Models\ProPurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WeatherAlertController extends Controller
{
    private ProService $proService;

    public function __construct(ProService $proService)
    {
        $this->proService = $proService;
    }

    /**
     * لیست درخواست‌های خرید Pro
     */
    public function proPurchaseRequests(Request $request)
    {
        $status = $request->input('status', 'pending');
        
        $requests = ProPurchaseRequest::where('status', $status)
            ->with(['botUser', 'bot'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.weather.pro_purchase_requests', [
            'requests' => $requests,
            'status' => $status
        ]);
    }

    /**
     * تایید درخواست خرید Pro
     */
    public function confirmProPurchase(Request $request, int $requestId)
    {
        $adminId = auth()->id() ?? 1; // باید از authentication استفاده شود
        
        $result = $this->proService->confirmPurchase($requestId, $adminId);
        
        if ($result) {
            return redirect()->back()->with('success', 'درخواست با موفقیت تایید شد');
        }
        
        return redirect()->back()->with('error', 'خطا در تایید درخواست');
    }

    /**
     * رد درخواست خرید Pro
     */
    public function rejectProPurchase(Request $request, int $requestId)
    {
        $purchaseRequest = ProPurchaseRequest::find($requestId);
        
        if (!$purchaseRequest) {
            return redirect()->back()->with('error', 'درخواست یافت نشد');
        }

        $purchaseRequest->status = 'rejected';
        $purchaseRequest->admin_notes = $request->input('admin_notes');
        $purchaseRequest->save();

        Log::info('❌ [Pro] Purchase request rejected', [
            'request_id' => $requestId,
            'admin_id' => auth()->id() ?? 1
        ]);

        return redirect()->back()->with('success', 'درخواست رد شد');
    }
}
