<?php

namespace App\Constants;

class AppConstants
{
    public const TYPE_USER    = 1;
    public const TYPE_MANAGER = 2;
    public const TYPE_ADMIN   = 3;

    public const STATUS_ACTIVE = 1;
    public const STATUS_LOCKED = 0;
    
    public const USER_CODE_LENGTH = 4;

    public const USER_STATUSES = [
        0 => 'Đã khóa',
        1 => 'Đang hoạt động',
    ];

    public const USER_TYPES = [
        1 => 'User',
        2 => 'Manager',
        3 => 'Admin',
    ];

    public const DEPARTMENTS = [
        1 => 'Ban quản lý',
        2 => 'Nhân sự',
        3 => 'Kế toán',
        4 => 'Kỹ thuật',
        5 => 'Sản xuất',
    ];

    public const STATUS_NEW       = 0;
    public const STATUS_APPROVED  = 1;
    public const STATUS_CANCELLED = 2;

    public const TYPE_NGHIPHEP      = 1;
    public const TYPE_VATTU         = 2;
    public const TYPE_GIOLAM        = 3;
    public const TYPE_CHIPHICONGTAC = 4;

    public const LEAVE_TYPES = [
        1 => 'Đơn nghỉ phép',
        2 => 'Đơn cấp vật tư máy móc',
        3 => 'Đơn thay đổi giờ làm',
        4 => 'Đơn xin thanh toán công tác phí',
    ];

    public const LEAVE_STATUSES = [
        0 => 'Đơn mới',
        1 => 'Đã duyệt',
        2 => 'Đã hủy',
    ];
}
