<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
        $this->middleware('can:create,' .User::class)->only(['create','store']);
        $this->middleware('can:update,user')->only(['edit', 'update', 'lock', 'unlock']);
        $this->middleware('can:bulkLock,'.User::class)->only('bulkLock');
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
                $matches->whereRaw("LPAD(id, ?, '0') LIKE ?", [AppConstants::USER_CODE_LENGTH, $keyword])-> orWhere('full_name', 'like', $keyword);
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
        $columns = ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'];
        $users   = $query->paginate(15,$columns); // Lấy danh sách theo từng trang
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
            'user'        => $user,
            'userTypes'   => $options['userTypes'],
            'departments' => $options['departments']
        ]);
    }

    // Hiện form sửa user
    public function edit(Request $request, User $user) : View {
        $options = $this->formOptions($request->user(), $user);

        return view('users.form', [
            'user'        => $user,
            'userTypes'   => $options['userTypes'],
            'departments' => $options['departments']
        ]);
    }

    // Xử lý thêm user mới vào database
    public function store(Request $request) : JsonResponse {
        $options = $this->formOptions($request->user());
        $data = $request->validate([
            'username'   => 'required|unique:users,username',
            'full_name'  => 'required',
            'email'      => ['required', 'email', Rule::unique('users', 'email')],
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'user_type'  => ['required', Rule::in(array_keys($options['userTypes']))],
            'department' => ['required', Rule::in(array_keys($options['departments']))]
        ]);

        DB::beginTransaction();
        try {
            User::create($data + [ // ghép các dữ liệu để lưu vào database
                'password_hash' => Hash::make('123456'),
                'status'        => AppConstants::STATUS_ACTIVE,
                'created_at'    => now()
            ]);
            DB::commit();
        } catch(Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()->json(['redirect' => route('users.index')]);
    }

    // Cập nhật thông tin user
    public function update(Request $request, User $user) : JsonResponse {
        $options = $this->formOptions($request->user(), $user);
        $data = $request->validate([
            'full_name'  => 'required',
            'password'   => ['nullable', Password::min(8)->letters()->numbers()->symbols()],
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'user_type'  => ['required', Rule::in(array_keys($options['userTypes']))],
            'department' => ['required', Rule::in(array_keys($options['departments']))],
        ]);

        if (filled($data['password'] ?? null)) { // Nếu mật khẩu không rỗng hoặc null
            $data['password_hash'] = Hash::make($data['password']); // Mã hóa mật khẩu mới
        }
        unset($data['password']); // Xóa mật khẩu gốc khỏi data

        DB::beginTransaction();
        try {
            $user = User::where('id', $user->id)->lockForUpdate()->first(); // Tìm, khóa và lấy user tìm được
            abort_if($user === null, 404, 'Không tìm thấy tài khoản.');
            $user->update($data + ['update_at' => now()]); // Ghi các thay đổi vào db

            DB::commit();
        } catch(Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()->json(['redirect' => route('users.index')]);
    }

    // Xử lý khóa tài khoản
    public function lock(Request $request, User $user) : JsonResponse {
        DB::beginTransaction();
        try{
            $user = User::where('id', $user->id)->lockForUpdate()->first(); // Lấy và khóa user đầu tiên tìm được
            abort_if($user === null, 404, 'Không tìm thấy tài khoản.');
            $user->update(['status' => AppConstants::STATUS_LOCKED]);

            DB::commit();
        } catch (Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()->json(['redirect' => $user->id === $request->user()->id ? route('login') : null]);
    }

    // Xử lý mở khóa tài khoản
    public function unlock(User $user) : JsonResponse {
        DB::beginTransaction();
        try{
            $user = User::where('id', $user->id)->lockForUpdate()->first();
            abort_if($user === null, 404, 'Không tìm thấy tài khoản.');
            $user->update(['status' => AppConstants::STATUS_ACTIVE]);

            DB::commit();
        } catch (Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()->json([]);
    }

    // Xử lý khóa nhiều tài khoản
    public function bulkLock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $ids = $data['user_ids'];
        $viewer = $request->user();

        DB::beginTransaction();
        try {
            $query = User::whereIn('id', $ids);
            if ($viewer->user_type === AppConstants::TYPE_MANAGER) {
                $query->where('user_type', AppConstants::TYPE_USER)
                    ->where('department', $viewer->department);
            }

            $users = $query->orderBy('id')->lockForUpdate()->get();

            abort_if(
                $users->count() !== count($ids),
                404,
                'Không tìm thấy một hoặc nhiều tài khoản trong phạm vi quản lý.'
            );

            User::whereIn('id', $users->modelKeys())->update(['status' => AppConstants::STATUS_LOCKED]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return response()->json(['redirect' => $users->contains('id', $viewer->id) ? route('login') : null]);
    }

    // Danh sách lựa chọn vai trò và phòng ban khi thêm, sửa user
    public function formOptions(User $viewer, ?User $user = null) : array {
        $userTypes = [
        AppConstants::TYPE_USER    => 'User',
        AppConstants::TYPE_MANAGER => 'Manager',
        ];
        $departments = AppConstants::DEPARTMENTS;

        if($viewer->user_type === AppConstants::TYPE_MANAGER){ // Nếu người sửa là manager
            $userTypes   = [AppConstants::TYPE_USER => 'User']; // Chỉ chọn vai trò user
            $departments = Arr::only($departments, [$viewer->department]); // Chỉ chọn cùng phòng ban
        }
        if($user?->user_type === AppConstants::TYPE_ADMIN){ // Nếu đang sửa user Admin
            $userTypes = [AppConstants::TYPE_ADMIN => 'Admin']; // Chỉ chọn vai trò admin
        }
        return [
            'userTypes'   => $userTypes,
            'departments' => $departments
        ];
    }
}
