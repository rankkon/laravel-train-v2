<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Mail\LeaveCreatedMail;
use App\Mail\LeaveProcessedMail;
use App\Models\Leave;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\InArray;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
    }

    // Hiển thị danh sách đơn theo quyền xem
    public function index(Request $request) : View {
        $viewer = $request->user();
        $search = $request->query('q',''); // Tìm từ khóa tìm kiếm (nếu có)
        $sortColumn = $request->query('sort',''); // Tìm cột áp dụng sort (nếu có)
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : "asc";
        $query = Leave::join('users', 'users.id', '=', 'leave.user_id');
        
        if($viewer->user_type !== AppConstants::TYPE_ADMIN){ // Admin được xem tất cả đơn, user/manager xem đơn cùng phòng ban
            $query->where('users.department', $viewer->department); 
        }

        // Xử lý tìm kiếm đơn
        if($request->filled('q')){
            $keyword = '%' .$search. '%';
            $query->where(function(Builder $matches) use($keyword): void {
                $matches->where('users.full_name', 'like', $keyword)->orwhere('leave.title', 'like', $keyword);
                foreach(AppConstants::LEAVE_TYPES as $type => $name){
                    $matches->orwhereRaw('(leave.leave_type = ? AND ? LIKE ?)', [$type, $name, $keyword]);
                }
            });
        }

        // Giới hạn những cột được phép sort
        if(! in_array($sortColumn, ['stt', 'full_name', 'title', 'leave_type', 'created_at', 'end_date', 'status'], true)) {
            $sortColumn = '';
        }

        if($sortColumn === '' || $sortColumn === 'stt' && $sortDirection === 'asc'){
            $query->orderBy('leave.status')->orderByDesc('leave.created_at')->orderByDesc('leave.id'); // Bộ sort mặc định 
        } elseif($sortColumn === 'stt'){
            $query->orderByDesc('leave.status')->orderBy('leave.created_at')->orderBy('leave.id');
        } else {
            $dbColumn = $sortColumn === 'full_name' ? 'users.full_name' : 'leave.' .$sortColumn;
            $query->orderBy($dbColumn, $sortDirection)->orderByDesc('leave.id');
        }

        $columns = ['leave.id', 'users.full_name', 'leave.user_id', 'leave.approver_id', 'leave.leave_type', 'leave.created_at', 'leave.end_date', 'leave.title', 'leave.status'];
        $leaves = $query->paginate(15, $columns); // Lấy danh sách đơn theo từng trang
        $leaves->withQueryString(); // Giữ bộ lọc khi chuyển trang

        return view($request->ajax() ? 'leaves.partials.list' : 'leaves.index', [
            'leaves'        => $leaves,
            'search'        => $search,
            'sortColumn'    => $sortColumn,
            'sortDirection' => $sortDirection
        ]); 
    }

    // Hiện form tạo đơn
    public function create(Request $request) : View {
        $leave = new Leave(['start_date' => today(), 'end_date' => today()]);

        return view('leaves.form', [
            'leave'     => $leave,
            'approvers' => $this->approvers($request->user())
        ]);
    }

    // Hiện form sửa đơn
    public function edit(Request $request, Leave $leave) : View {
        return view('leaves.form', [
            'leave'     => $leave,
            'approvers' => $this->approvers($request->user())
        ]);
    }
    
    // Xử lý lưu đơn
    public function store(Request $request, Leave $leave) : JsonResponse {
        $data = $request->validate([
            'title'       => 'required',
            'content'     => 'nullable',
            'approver_id' => ['required', Rule::in($this->approvers($request->user())->modelKeys())],
            'leave_type'  => ['required', Rule::in(array_keys(AppConstants::LEAVE_TYPES))],
            'start_date'  => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date'    => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'attachment'  => 'nullable|mimes:jpg,jpeg,png,gif'
        ]);
        unset($data['attachment']);

        DB::beginTransaction();
        try {
            $attachment = $this->storeAttachment($request);
            $leave = Leave::create($data + $attachment + [
                'user_id'                  => $request->user()->id,
                'status'                   => AppConstants::STATUS_NEW,
                'created_at'               => now(),
                'attachment_original_name' => '',
                'attachment_path'          => ''
            ]);
            DB::commit();
        } catch (Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()->json(['redirect' => route('leaves.index')]);
    }

    // Xử lý sửa đơn
    public function update(Request $request, Leave $leave) : JsonResponse {
        $data = $request->validate([
            'title'       => 'required',
            'content'     => 'nullable',
            'approver_id' => ['required', Rule::in($this->approvers($request->user())->modelKeys())],
            'leave_type'  => ['required', Rule::in(array_keys(AppConstants::LEAVE_TYPES))],
            'start_date'  => 'required|date_format:Y-m-d',
            'end_date'    => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'attachment'  => 'nullable|mimes:jpg,jpeg,png,gif'
        ]);
        unset($data['attachment']);

        DB::beginTransaction();
        try {
            $leave = Leave::where('id', $leave->id)->lockForUpdate()->First();
            $attachment = $this->storeAttachment($request);
            $oldPath    = $leave->attachment_path;
            $leave->update($data + $attachment);

            if($oldPath && $oldPath !== $leave->attachment_path){
                DB::afterCommit(fn() => $this->DeleteUnusedAttachment($oldPath));
            }
            DB::commit();
        } catch (Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()-> json(['redirect' => route('leaves.index')]);
    }

    private function approvers(User $user) : Collection {
        $query = User::where('status', AppConstants::STATUS_ACTIVE);
        if($user->user_type === AppConstants::TYPE_USER){
            $query->where('user_type', AppConstants::TYPE_MANAGER)->where('department', $user->department);
        } elseif($user->user_type === AppConstants::TYPE_MANAGER){
            $query->where('user_type', AppConstants::TYPE_ADMIN);
        }
        return $query->orderBy('id')->get(['id', 'full_name']);
    }
}
