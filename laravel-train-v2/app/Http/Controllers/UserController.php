<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use SortDirection;

class UserController extends Controller
{
    public function __construct()
    {

    }

    public function index(Request $request) : View {
        $viewer = $request->user();
        $search = $request->query('q',''); // Tìm từ khóa tìm kiếm (nếu có)
        $sortColumn = $request->query('sort',''); // Tìm cột áp dụng sort (nếu có)
        $statusFilter = $request->query('status', ''); // Lấy bộ lọc áp dụng (nếu có)
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : "asc";
        $query = User::query();
        
        if($viewer->user_type !== AppConstants::TYPE_ADMIN) { // Admin xem được tất cả users, user/manager chỉ xem user cùng phòng ban
            $query->where('department', $viewer->department);
        }

        // Xử lý tìm kiếm users
        if($request->filled('q')){
            $keyword = '%' .$search. '%';
            $query->where(function (Builder $matches) use($keyword) : void {
                $matches->whereRaw("LPAD(id, ?, '0') LIKE ?", [AppConstants::USER_CODE_LENGTH, $keyword]) 
                -> orWhere('id', 'like', $keyword) -> orWhere('full_name', 'like', $keyword);
            });
        }

        // Xử lý lọc users
        if($statusFilter !== ''){
            $query->where('status', $statusFilter);
        }

        if(! in_array($sortColumn, ['id', 'status', 'created_at', 'full_name'], true)){
            $sortColumn='';
            $query->orderBy('created_at'); // Sort mặc định theo thời điểm tạo
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }
        if($sortColumn !== 'id'){
            $query->orderByDesc('id');
        }

        $columns = ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'];
        $users   = $query->paginate(15,$columns); // Lấy danh sách theo từng trang
        if($users->isEmpty() && $users->currentPage() > 1){
            $users = $query->paginate(15,$columns, page: $users->lastPage()); // Lấy dữ liệu trang cuối nếu trang request rỗng
        }
        $users->withQueryString(); // Giữ bộ lọc khi chuyển trang

        return view($request->ajax() ? 'users.partials.list' : 'users.index', [
            'users'         => $users,
            'search'        => $search,
            'statusFilter'  => $statusFilter,
            'sortColumn'    => $sortColumn,
            'sortDirection' => $sortDirection
        ]);
    }
}
