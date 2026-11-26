<?php

namespace App\Policies;

use App\Constants\AppConstants;
use App\Models\User;

class UserPolicy
{
    // Quyền thêm mới user
    public function create(User $user): bool
    {
        return $user->status === AppConstants::STATUS_ACTIVE
            && in_array($user->user_type, [AppConstants::TYPE_MANAGER, AppConstants::TYPE_ADMIN], true); 
    }

    // Quyền quản lý user
    public function update(User $user, User $targetUser): bool
    {
        if ($user->status !== AppConstants::STATUS_ACTIVE) {
            return false;
        }
        // Admin có thể quản lý tất cả người dùng
        if ($user->user_type === AppConstants::TYPE_ADMIN) {
            return true;
        }

        // Manager chỉ có thể quản lý người dùng thuộc cùng phòng ban
        return $user->user_type === AppConstants::TYPE_MANAGER
            && $targetUser->user_type === AppConstants::TYPE_USER
            && $targetUser->department === $user->department;
    }

    // Quyền sử dụng chức năng khóa nhiều tài khoản
    public function bulkLock(User $viewer): bool
    {
        return $viewer->status === AppConstants::STATUS_ACTIVE
            && in_array($viewer->user_type, [AppConstants::TYPE_ADMIN, AppConstants::TYPE_MANAGER], true);
    }
}
