<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;
use App\Constants\AppConstants;

class LeavePolicy
{
    // Quyền duyệt/hủy đơn
    public function process(User $user, Leave $leave): bool
    {
        if($user->status !== AppConstants::STATUS_ACTIVE || $user->user_type === AppConstants::TYPE_USER || $leave->leave_status !== AppConstants::STATUS_NEW){ // User không được duyệt/hủy đơn
            return false;
        }
        if($user->user_type === AppConstants::TYPE_ADMIN){ // Admin được duyệt/hủy mọi đơn
            return true;
        }
        return $user->user_type === AppConstants::TYPE_MANAGER && $leave->user?->department === $user->department // Manager phải thuộc cùng phòng ban mới được duyệt/hủy
        && $leave->approver_id === $user->id && $leave->user?->user_type === AppConstants::TYPE_USER;
    }

    public function update(User $user, Leave $leave): bool
    {
        return $user->status === AppConstants::STATUS_ACTIVE
        && $leave->user_id === $user->id && $leave->status === AppConstants::STATUS_NEW; // Chỉ người tạo đơn mới được sửa đơn (chỉ được sửa đơn mới)
    }
}
