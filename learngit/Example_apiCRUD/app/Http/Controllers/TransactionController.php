<?php
namespace App\Http\Controllers;
use App\Models\Transaction;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        try {
            $transactions = Transaction::with(['notary', 'client', 'service'])->get();
            return ApiResponse::success($transactions, 'Lấy danh sách transaction thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function show($id)
    {
        try {
            $transaction = Transaction::with(['notary', 'client', 'service'])->find($id);
            if (!$transaction) return ApiResponse::error('Không tìm thấy transaction', 404);
            return ApiResponse::success($transaction, 'Lấy dữ liệu thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'NotaryId'  => 'required|integer|exists:Notaries,Id',
                'ClientId'  => 'required|integer|exists:Clients,Id',
                'ServiceId' => 'required|integer|exists:Services,Id',
                'TotalFee'  => 'required|numeric|min:0',
                'Status'    => 'nullable|in:Pending,Completed,Cancelled',
                'Notes'     => 'nullable|string',
            ]);
            $transaction = Transaction::create($validated);
            return ApiResponse::success($transaction, 'Tạo transaction thành công', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Lỗi validate dữ liệu', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) return ApiResponse::error('Không tìm thấy transaction', 404);
            $transaction->update($request->all());
            return ApiResponse::success($transaction, 'Cập nhật thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) return ApiResponse::error('Không tìm thấy transaction', 404);
            $transaction->delete();
            return ApiResponse::success(null, 'Xóa thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }
}