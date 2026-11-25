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
        $this->middleware([ActiveUser::class, 'auth']);
        $this->middleware('can:create,' .User::class)->only(['create','store']);
        $this->middleware('can:update,user')->only(['edit', 'update']);
    }

    // Hiển thị danh sách user theo quyền người xem
    public function index(Request $request) : View {
        $viewer        = $request->user();
        $search        = $request->query('q',''); // Tìm từ khóa tìm kiếm (nếu có)
        $sortColumn    = $request->query('sort',''); // Tìm cột áp dụng sort (nếu có)
        $statusFilter  = $request->query('status', ''); // Lấy bộ lọc áp dụng (nếu có)
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : "asc";
        $query         = User::query();
        
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

    // Hiện form thêm user mới
    public function create(Request $request) : View {
        $user    = new User(['birth_date' => today()]); // Đặt ngày sinh mặc định là hôm nay
        $options = $this->formOptions($request->user());

        return view('users.form', [
            'user'       => $user,
            'userTypes'  => $options['userTypes'],
            'department' => $options['department']
        ]);
    }

    // Hiện form sửa user
    public function edit(Request $request, User $user) : View {
        $options = $this->formOptions($request->user(), $user);

        return view('users.form', [
            'user'       => $user,
            'userTypes'  => $options['userTypes'],
            'department' => $options['department']
        ]);
    }

    // Xử lý thêm user mới vào database
    public function store(Request $request) : JsonResponse {
        DB::transaction(function() use($request) : void {
            User::create($this->validatedData($request) + [ // Validate và ghép các dữ liệu để lưu vào database
                'password_hash' => Hash::make('123456'),
                'status'        => AppConstants::STATUS_ACTIVE,
                'created_at'    => now()
            ]);
        });
        return response()->json(['redirect' => route('users.index')]);
    }

    // Cập nhật thông tin user
    public function update(Request $request, User $user) : JsonResponse {
        DB::transaction(function() use($request, $user) : void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail(); // Tìm, khóa và lấy user tìm được
            Gate::authorize('update', $user); // Kiểm tra quyền quản lý user
            $user->update($this-> validatedData($request, $user) + ['update_at' => now()]); // Ghi các thay đổi vào db
        });
        return response()->json(['redirect' => route('users.index')]);
    }

    // Danh sách lựa chọn vai trò và phòng ban khi thêm, sửa user
    public function formOptions(User $viewer, ?User $user = null) : array {
        $userTypes   = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_MANAGER, AppConstants::TYPE_USER]);
        $departments = AppConstants::DEPARTMENTS;

        if($viewer->user_type === AppConstants::TYPE_MANAGER){ // Nếu là manager
            $userTypes   = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_USER]); // Chỉ chọn vai trò user
            $departments = Arr::only(AppConstants::DEPARTMENTS, [$viewer->department]); // Chỉ chọn cùng phòng ban
        }

        if($user?->user_type === AppConstants::TYPE_ADMIN){ // Nếu đang sửa user Admin
            $userTypes = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_ADMIN]); // Chỉ chọn vai trò admin
        }

        return [
            'userTypes'   => $userTypes,
            'departments' => $departments
        ];
    }

    public function validatedData(Request $request, ?User $user = null) : array {
        $options = $this->formOptions($request->user(), $user);
        $data    = $request->validate([
            'username'   => $user ? 'exclude' : 'required|unique:users,username',
            'full_name'  => 'required',
            'password'   => [$user ? 'nullable' : 'exclude', Password::min(8)->letters()->numbers()->symbols()],
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user)], 
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'user_type'  => $user?->user_type === AppConstants::TYPE_ADMIN ? 'exclude' : ['required', Rule::in(array_keys($options['userTypes']))],
            'department' => ['required', Rule::in(array_keys($options['departments']))]
        ]);

        if(filled($data['password'] ?? null)){ // Nếu mật khẩu không rỗng hoặc null
            $data['password_hash'] = Hash::make($data['password']); // Mã hóa mật khẩu mới
        }
        unset($data['password']); // Xóa mật khẩu gốc khỏi data

        return $data;
    }
}
