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
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\InArray;
use Illuminate\View\View;
use Psy\Util\Json;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function PHPUnit\Framework\throwException;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
    }

    // Hiển thị danh sách đơn theo quyền xem
    public function index(Request $request) : View {
        $viewer = $request->user();
        $search = $request->query('search',''); // Tìm từ khóa tìm kiếm (nếu có)
        $sortColumn = $request->query('sort',''); // Tìm cột áp dụng sort (nếu có)
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : "asc";
        $query = Leave::join('users', 'users.id', '=', 'leave.user_id');
        
        if($viewer->user_type !== AppConstants::TYPE_ADMIN){ // Admin được xem tất cả đơn, user/manager xem đơn cùng phòng ban
            $query->where('users.department', $viewer->department); 
        }

        // Xử lý tìm kiếm đơn
        if($request->filled('search')){
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
        unset($data['attachment']); // Bỏ khóa file đính kèm

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
        unset($data['attachment']); // Bỏ khóa file đính kèm

        DB::beginTransaction();
        try {
            $leave = Leave::where('id', $leave->id)->lockForUpdate()->First();
            $attachment = $this->storeAttachment($request);
            $oldPath    = $leave->attachment_path; // Lấy đường dẫn file cũ
            $leave->update($data + $attachment);

            if($oldPath && $oldPath !== $leave->attachment_path){
                DB::afterCommit(fn() => $this->DeleteUnusedAttachment($oldPath)); // Xóa đường dẫn file cũ nếu upload file mới
            }
            DB::commit();
        } catch (Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return response()-> json(['redirect' => route('leaves.index')]);
    }

    // Duyệt đơn
    public function approve(Leave $leave) : JsonResponse {
        return $this->process($leave, [
            'status' => AppConstants::STATUS_APPROVED,
            'update_at' => now(),
            'approved_at' => today()->toDateString()
        ]);
    }

    // Hủy đơn
    public function cancel(Request $request, Leave $leave) : JsonResponse {
        $data = $request->validate(['cancel_reason' => 'required']);

        return $this->process($leave, $data + [
            'status' => AppConstants::STATUS_CANCELLED,
            'delete_at' => now()
        ]);
    }

    // Xử lý cập nhật trạng thái đơn
    private function process(Leave $leave, array $changes) : JsonResponse {
        DB::beginTransaction();
        try {
            $leave = Leave::where('id', $leave->id)->lockForUpdate()->first();
            abort_if($leave === null || $leave->user === null, 404);
            $leave->update($changes);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return response()->json([]);
    }

    // Lấy danh sách người duyệt
    private function approvers(User $user) : Collection {
        $query = User::where('status', AppConstants::STATUS_ACTIVE);
        if($user->user_type === AppConstants::TYPE_USER){
            $query->where('user_type', AppConstants::TYPE_MANAGER)->where('department', $user->department); // Lấy danh sách manager cùng phong ban nếu là User
        } elseif($user->user_type === AppConstants::TYPE_MANAGER){
            $query->where('user_type', AppConstants::TYPE_ADMIN); // Lấy danh sách admin nếu là Manager
        }
        return $query->orderBy('id')->get(['id', 'full_name']);
    }

    // Xử lý lưu file đính kèm
    private function storeAttachment(Request $request): array
    {
        // Chỉ thay file nếu có chọn file mới
        if (! $request->hasFile('attachment')) {
            return [];
        }
        $file = $request->file('attachment');
        $path = $file->store('leaves', 'local');
        throw_if($path === false, RuntimeException::class, __('messages.attachment_write_failed'));
        DB::afterRollBack(fn () => $this->deleteUnusedAttachment($path));

        return [
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_path'          => $path,
        ];
    }

    // Xóa file đính kèm không sử dụng khi lưu thất bại
    private function deleteUnusedAttachment(string $path): void
    {
        try {
            if (! Leave::where('attachment_path', $path)->exists()) {
                throw_if(! Storage::disk('local')->delete($path), 'Không xóa được tệp đính kèm.');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    // Xem trước ảnh đính kèm
    public function preview(Leave $leave) : StreamedResponse {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($leave->attachment_path), 404);

        return $disk->response($leave->attachment_path);
    }
}