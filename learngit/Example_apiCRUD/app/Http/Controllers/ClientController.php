<?php
namespace App\Http\Controllers;
use App\Models\Client;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        try {
            return ApiResponse::success(Client::all(), 'Lấy danh sách client thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function show($id)
    {
        try {
            $client = Client::find($id);
            if (!$client) return ApiResponse::error('Không tìm thấy client', 404);
            return ApiResponse::success($client, 'Lấy dữ liệu thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'FullName' => 'required|string|max:100',
                'Email'    => 'nullable|email',
                'Phone'    => 'nullable|string',
                'SSN'      => 'nullable|string|unique:Clients,SSN',
                'Address'  => 'nullable|string',
            ]);
            $client = Client::create($validated);
            return ApiResponse::success($client, 'Tạo client thành công', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Lỗi validate dữ liệu', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $client = Client::find($id);
            if (!$client) return ApiResponse::error('Không tìm thấy client', 404);
            $client->update($request->all());
            return ApiResponse::success($client, 'Cập nhật thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $client = Client::find($id);
            if (!$client) return ApiResponse::error('Không tìm thấy client', 404);
            $client->delete();
            return ApiResponse::success(null, 'Xóa thành công');
        } catch (\Exception $e) {
            return ApiResponse::error('Lỗi server', 500);
        }
    }
}