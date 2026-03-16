<?php
namespace App\Http\Controllers;
use App\Models\Service;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        try {
            return ApiResponse::success(Service::all(), 'Lấy danh sách service thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function show($id)
    {
        try {
            $service = Service::find($id);
            if (!$service) return ApiResponse::error('Không tìm thấy service', 404);
            return ApiResponse::success($service, 'Lấy dữ liệu thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'ServiceName' => 'required|string|max:100',
                'BaseFee'     => 'required|numeric|min:0',
                'Description' => 'nullable|string',
            ]);
            $service = Service::create($validated);
            return ApiResponse::success($service, 'Tạo service thành công', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Lỗi validate dữ liệu', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $service = Service::find($id);
            if (!$service) return ApiResponse::error('Không tìm thấy service', 404);
            $service->update($request->all());
            return ApiResponse::success($service, 'Cập nhật thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $service = Service::find($id);
            if (!$service) return ApiResponse::error('Không tìm thấy service', 404);
            $service->delete();
            return ApiResponse::success(null, 'Xóa thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }
}