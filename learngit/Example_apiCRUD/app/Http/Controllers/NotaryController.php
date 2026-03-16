<?php
namespace App\Http\Controllers;
use App\Models\Notary;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class NotaryController extends Controller
{
    public function index()
    {
        try {
            $notaries = Notary::all();
            return ApiResponse::success($notaries, 'Lấy danh sách notary thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function show($id)
    {
        try {
            $notary = Notary::find($id);
            if (!$notary) return ApiResponse::error('Không tìm thấy notary', 404);
            return ApiResponse::success($notary, 'Lấy dữ liệu thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'FullName'          => 'required|string|max:100',
                'LicenseNumber'     => 'required|string|unique:Notaries,LicenseNumber',
                'Email'             => 'required|email|unique:Notaries,Email',
                'Phone'             => 'nullable|string',
                'StateCommissioned' => 'nullable|string',
            ]);
            $notary = Notary::create($validated);
            return ApiResponse::success($notary, 'Tạo notary thành công', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Lỗi validate dữ liệu', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $notary = Notary::find($id);
            if (!$notary) return ApiResponse::error('Không tìm thấy notary', 404);
            $notary->update($request->all());
            return ApiResponse::success($notary, 'Cập nhật thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $notary = Notary::find($id);
            if (!$notary) return ApiResponse::error('Không tìm thấy notary', 404);
            $notary->delete();
            return ApiResponse::success(null, 'Xóa thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }
}