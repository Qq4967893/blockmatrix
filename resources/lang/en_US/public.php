<?php

return [
    // 公共回复内容
    'message' => [
        'get_success' => 'Data retrieved successfully',
        'get_error' => 'Failed to retrieve data',
        'data_empty' => 'No data',
        'del_success' => 'Data deleted successfully',
        'del_error' => 'Failed to delete data',
        'add_success' => 'Data added successfully',
        'add_error' => 'Failed to add data',
        'upd_success' => 'Data updated successfully',
        'upd_error' => 'Failed to update data',
        'in_complete' => 'Incomplete data',                     // 数据填写不完整
        'repeated' => 'Title cannot be duplicated',          // 标题不能重复
        'id_empty' => 'ID cannot be empty',                  // Id不能为空
        'title_empty' => 'The title cannot be empty',           // 标题不能为空
        'url_empty' => 'Upload URL cannot be empty',          // 上传url不能为空
        'state_error' => 'Invalid status',                      // 状态错误
        'non_existent' => 'The record does not exist',           // 记录不存在
        'param_non_existent' => ':param does not exist',
        'not_be_empty' => 'Content cannot be empty',             // 内容不能为空
        'favorite_limit_exceeded' => 'Favorite limit reached (max :limit)', // 收藏数量已达上限
        'wrong_threshold' => 'Invalid threshold',                   // 参数错误
        'not_found' => 'No data found',                       // 未查询到数据
        'type_error' => 'Invalid type',                        // 类型错误
        'correlation' => 'Associated data exists',              // 存在关联设置
        'ib_level_account_exists' => 'This level has assigned IB accounts and cannot be deleted',
        'already_exist' => 'This record already exists',          // 重复的记录
        'sys_err' => 'System error, please contact the administrator', // 系统错误，请联系管理员
        'threshold_exceeded' => 'Threshold exceeded',                  // 阈值超出限制
        'select_one' => 'Please select at least one record',   // 请至少选择一条记录
        'wrong_options' => 'Invalid option',                      // 错误的选项
        'over_search_range' => 'Search range exceeded',               // 超出查看范围
        'reason_must' => 'Reason is required',                  // 原因必须填写
        'amount_error' => 'Amount must be greater than 0',       // 金额必须大于0
        'amount_lt_max' => 'Amount must be less than :max',
        'wrong_format_date' => 'Invalid date format',                 // 日期格式错误
        'wrong_time_select' => 'Invalid time selection',              // 时间选择错误
        'no_data_permission' => 'No permission to access this data',   // 没有权限
        'final_status' => 'Final status cannot be modified',     // 最终状态，不能修改
        'not_repeat' => 'Don\'t repeat operations',
        'format_err' => 'Format error',                        // 格式错误
        'lang_err' => 'Invalid language type',
        'default_lang' => 'The default language is English',     // 默认语言为英语
        'repeat_lang' => 'Language entry already exists, please try again', // 默认语言为英语
        'Incomplete' => 'Incomplete data',
        'data_error' => 'Invalid data',
        'empty_content' => 'Content cannot be empty',
        'currency_exists' => 'Currency already exists',
        'export_limit_exceeded' => 'Export cannot exceed :limit rows. Please narrow the filters.',
    ],

    'user_prohibition_types' => [
        1 => 'Restrict Login',
        2 => 'Restrict Deposit',
        3 => 'Restrict Withdrawal',
        4 => 'Restrict Opening Positions',
    ],

    'status' => [
        0 => 'Delete',
        1 => 'Normal',
    ],

    'filter_all' => 'All',

    'position' => [
        'identifierOnly' => 'Ad position identifier already exists',
    ],
];
