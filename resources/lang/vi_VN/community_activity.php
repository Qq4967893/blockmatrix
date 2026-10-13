<?php

return [
    'status' => [
        0 => 'Chờ xuất bản',
        1 => 'Đã xuất bản',
        2 => 'Vô hiệu',
        'unknown' => 'Không xác định',
    ],
    'publish_type' => [
        0 => 'Định kỳ',
        1 => 'Thủ công',
        'unknown' => 'Không xác định',
    ],
    'message' => [
        'void_success' => 'Vô hiệu thành công',
        'voided_no_action' => 'Hoạt động đã bị vô hiệu, không thể thao tác lại.',
        'publish_success' => 'Xuất bản thành công',
        'only_pending_can_publish' => 'Chỉ hoạt động ở trạng thái chờ mới có thể xuất bản.',
        'activity_ended' => 'Hoạt động đã kết thúc, không thể xuất bản.',
    ],
];
