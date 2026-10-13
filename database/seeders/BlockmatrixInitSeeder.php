<?php

namespace Database\Seeders;

use App\Models\Blockmatrix\AdminUser;
use App\Models\Blockmatrix\Menu;
use App\Models\Blockmatrix\MenuEndpoint;
use App\Models\Blockmatrix\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BlockmatrixInitSeeder extends Seeder
{
    /**
     * 初始化后台基础数据：root角色、admin账号、sysAdmin账号、系统分类菜单
     */
    public function run(): void
    {
        $this->seedRootRole();
        $this->seedAdminUser();
        $this->seedSysAdminUser();
        $this->seedSysMenus();
    }

    /**
     * 创建 root 角色
     */
    private function seedRootRole(): void
    {
        $exists = Role::query()->where('name', 'root')->exists();
        if ($exists) {
            return;
        }
        Role::query()->insert([
            'name'       => 'root',
            'parent_id'  => 0,
            'status'     => Role::STATUS_NORMAL,
            'area_id'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 创建超级管理员账号（绑定 root 角色）
     */
    private function seedAdminUser(): void
    {
        $exists = AdminUser::query()->where('login_name', 'admin')->exists();
        if ($exists) {
            return;
        }
        $rootRole = Role::query()->where('name', 'root')->first();

        AdminUser::query()->insert([
            'login_name'  => 'admin',
            'password'    => Hash::make('Bm@Admin2026!x9'),
            'email'       => 'admin@blockmatrix.local',
            'role_id'     => $rootRole ? $rootRole->id : null,
            'level_id'    => null,
            'leader_id'   => null,
            'status'      => AdminUser::STATUS_NORMAL,
            'description' => '超级管理员',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * 创建 sysAdmin 账号（仅菜单配置，无角色）
     */
    private function seedSysAdminUser(): void
    {
        $exists = AdminUser::query()->where('login_name', AdminUser::SYS_ADMIN)->exists();
        if ($exists) {
            return;
        }
        AdminUser::query()->insert([
            'login_name'  => AdminUser::SYS_ADMIN,
            'password'    => Hash::make('Bm@SysMenu2026!k3'),
            'role_id'     => null,
            'level_id'    => null,
            'leader_id'   => null,
            'status'      => AdminUser::STATUS_NORMAL,
            'description' => '系统管理员（仅菜单配置）',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * 创建系统分类菜单（category=0）
     */
    private function seedSysMenus(): void
    {
        $exists = Menu::query()->where('menu_key', 'settings.menu.settings')->exists();
        if ($exists) {
            return;
        }

        // 清理旧种子数据（先删 menu_endpoint，再删 menu）
        $oldMenuIds = Menu::query()->whereIn('menu_key', [
            'settings', 'settings.menu', 'settings.menu.settings', 'settings.password.change',
        ])->pluck('id')->toArray();

        if (!empty($oldMenuIds)) {
            MenuEndpoint::query()->whereIn('menu_id', $oldMenuIds)->delete();
            Menu::query()->whereIn('id', $oldMenuIds)->delete();
        }

        // 一级菜单：设置
        $settingsId = Menu::query()->insertGetId([
            'name'       => '设置',
            'parent_id'  => null,
            'status'     => 0,
            'pos'        => 1,
            'category'   => Menu::CATEGORY_SYS,
            'menu_key'   => 'settings',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 二级菜单：菜单管理
        $menuId = Menu::query()->insertGetId([
            'name'       => '菜单管理',
            'parent_id'  => $settingsId,
            'status'     => 0,
            'pos'        => 1,
            'category'   => Menu::CATEGORY_SYS,
            'menu_key'   => 'settings.menu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 二级菜单：修改密码
        Menu::query()->insertGetId([
            'name'       => '修改密码',
            'parent_id'  => $settingsId,
            'status'     => 0,
            'pos'        => 2,
            'category'   => Menu::CATEGORY_SYS,
            'menu_key'   => 'settings.password.change',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 三级菜单：菜单管理设置
        $childId = Menu::query()->insertGetId([
            'name'       => '菜单管理设置',
            'parent_id'  => $menuId,
            'status'     => 0,
            'pos'        => 1,
            'category'   => Menu::CATEGORY_SYS,
            'menu_key'   => 'settings.menu.settings',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 接口地址（菜单 CRUD）
        $urls = [
            'api/menus',
            'api/menus/POST',
            'api/menus/PUT',
            'api/menus/status/PUT',
        ];
        foreach ($urls as $url) {
            MenuEndpoint::query()->insert([
                'menu_id'  => $childId,
                'api_path' => $url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
