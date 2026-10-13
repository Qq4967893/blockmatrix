<?php

return [

    'message' => [
        'mobile_err'         => '携帯番号の形式が正しくありません',
        'email_err'          => 'メールアドレスの形式が正しくありません',
        'account_used'       => '連携されたパスポートアカウントは既に使用されています',
        'user_not_exist'     => 'ユーザーが存在しません',
        'superior_not_exist' => '上位が存在しません',
    ],

    //用户
    'user'    => [
        'multi_field_not_empty' => '本名、パスワード、役割は空にできません',
        'name_exists'           => 'ログイン名は既に存在します',
        'email_exist'           => 'メールアドレスは既に存在します',
        'phone_exist'           => '電話番号は既に存在します',
        'email_error'           => 'メールアドレスの形式が正しくありません',
        'phone_error'           => '電話番号の形式が正しくありません',
        'email_empty'           => 'メールアドレスは空にできません',
        'phone_empty'           => '電話番号は空にできません',
        'least_one'             => '少なくとも1項目入力してください',
        'original_password_err' => '現在のパスワードが正しくありません',
    ],

    //角色菜单
    'role'    => [
        'name_not_empty'       => '役割名は空にできません',
        'name_too_lang'        => '役割名が長すぎます',
        'name_exist'           => '役割名は既に存在します',
        'superior_not_exist'   => '上位が存在しません',
        'permission_err'       => '権限の形式が正しくありません',
        'permission_not_empty' => 'データ権限は空にできません',
        'non_superiors'        => '上位が存在しません',
        'unable_delete'        => 'この役割にユーザーがいるため削除できません',
        'not_empty'            => '役割は空にできません',
    ],

    //菜单
    'menu'    => [
        'not_empty' => 'メニューは空にできません',
    ],

    'password' => [
        'not_empty'  => 'パスワードは空にできません',
        'consistent' => '新しいパスワードは一致する必要があります',
        'err'        => '現在のパスワードが正しくありません',
        'short'      => 'パスワードの長さは最低6文字です',
    ],

    'no_manager'     => '管理者なし',

    'validate' => [
        'validation' => '検証エラーメッセージ',
        'password'   => 'パスワードは大文字・小文字・数字を含み、長さは8〜24文字である必要があります',
    ],
];
