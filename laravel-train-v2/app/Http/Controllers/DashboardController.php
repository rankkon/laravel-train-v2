<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct() {
        $this->middleware([ActiveUser::class, 'auth']);
    }

    // Hiển thị danh sách đơn theo phạm vi người dùng và phân trang
    public function index(Request $request) : View {
        $query = Leave::join('users','users.id','=','leave.user_id'); // Khởi tạo truy vấn
        if($request->user()->user_type !== AppConstants::TYPE_ADMIN){
            $query->where('user.department', $request->user()->department); // Lấy đơn theo phòng ban nếu user không phải admin
        }

        $query->orderBy('leave.status')->orderByDesc('leave.created_at')->orderBy('leave.id'); // Sắp xếp đơn theo trạng thái, thời điểm tạo, id

        $columns = ['leave.id','leave.leave_type','users.full_name','leave.created_at','leave.status','leave.end_date','leave.title']; // 
        $leaves = $query->paginate(15, $columns); // Lấy danh sách đơn chia theo từng trang
        if($leaves->isEmpty() && $leaves->currentPage() > 1){
            $leaves = $query->paginate(15, $columns, page:$leaves->lastPage()); // Lấy trang cuối khi trang request trống
        }
        return view('dashboard.index', [
            'leaves'      => $leaves,
            'leaveTypes'  => AppConstants::LEAVE_TYPES,
            'leaveStatuses' => AppConstants::LEAVE_STATUSES,
        ]);
    }
}
