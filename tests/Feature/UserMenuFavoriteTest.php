<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Repositories\Blockmatrix\UserMenuFavoriteRepository;
use App\Services\Blockmatrix\UserMenuFavoriteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserMenuFavoriteTest extends TestCase
{
    private const ADMIN_USER_ID = 11;
    private const ROLE_ID       = 9;

    private array $originalBmConnection;

    private UserMenuFavoriteService $service;

    private array $loginUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBmConnection = config('database.connections.' . DB_BM);
        config([
            'database.connections.' . DB_BM => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ],
        ]);
        DB::purge(DB_BM);

        $this->createTables();
        $this->seedData();

        $this->service   = app(UserMenuFavoriteService::class);
        $this->loginUser = [
            'id'         => self::ADMIN_USER_ID,
            'login_name' => 'tester',
            'role_id'    => self::ROLE_ID,
        ];
    }

    protected function tearDown(): void
    {
        config(['database.connections.' . DB_BM => $this->originalBmConnection]);
        DB::purge(DB_BM);

        parent::tearDown();
    }

    public function test_add_and_list_returns_favorites_in_newest_first_order(): void
    {
        $this->service->add(self::ADMIN_USER_ID, 'key_a', $this->loginUser);
        $this->service->add(self::ADMIN_USER_ID, 'key_b', $this->loginUser);

        $favorites = $this->service->list(self::ADMIN_USER_ID, $this->loginUser);

        $this->assertSame(['key_b', 'key_a'], array_column($favorites, 'menu_key'));
        $this->assertSame('菜单B', $favorites[0]['name']);
    }

    public function test_add_is_idempotent_for_duplicated_favorite(): void
    {
        $this->service->add(self::ADMIN_USER_ID, 'key_a', $this->loginUser);
        $this->service->add(self::ADMIN_USER_ID, 'key_a', $this->loginUser);

        $count = app(UserMenuFavoriteRepository::class)->countByAdminUserId(self::ADMIN_USER_ID);
        $this->assertSame(1, $count);
    }

    public function test_add_rejects_menu_without_permission(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->add(self::ADMIN_USER_ID, 'key_c', $this->loginUser);
    }

    public function test_add_rejects_deleted_menu(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->add(self::ADMIN_USER_ID, 'key_d', $this->loginUser);
    }

    public function test_add_enforces_favorite_limit(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $rows[] = [
                'admin_user_id' => self::ADMIN_USER_ID,
                'menu_key'      => 'key_limit_' . $i,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }
        DB::connection(DB_BM)->table('user_menu_favorite')->insert($rows);

        $this->expectException(BusinessException::class);

        $this->service->add(self::ADMIN_USER_ID, 'key_a', $this->loginUser);
    }

    public function test_list_filters_deleted_and_unauthorized_menus(): void
    {
        DB::connection(DB_BM)->table('user_menu_favorite')->insert([
            ['admin_user_id' => self::ADMIN_USER_ID, 'menu_key' => 'key_a', 'created_at' => now(), 'updated_at' => now()],
            ['admin_user_id' => self::ADMIN_USER_ID, 'menu_key' => 'key_c', 'created_at' => now(), 'updated_at' => now()],
            ['admin_user_id' => self::ADMIN_USER_ID, 'menu_key' => 'key_d', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $favorites = $this->service->list(self::ADMIN_USER_ID, $this->loginUser);

        $this->assertSame(['key_a'], array_column($favorites, 'menu_key'));
    }

    public function test_list_returns_empty_for_user_without_favorites(): void
    {
        $this->assertSame([], $this->service->list(self::ADMIN_USER_ID, $this->loginUser));
    }

    public function test_remove_deletes_favorite_and_is_idempotent(): void
    {
        $this->service->add(self::ADMIN_USER_ID, 'key_a', $this->loginUser);

        $this->service->remove(self::ADMIN_USER_ID, 'key_a');
        $this->service->remove(self::ADMIN_USER_ID, 'key_a');

        $this->assertSame([], $this->service->list(self::ADMIN_USER_ID, $this->loginUser));
    }

    private function createTables(): void
    {
        Schema::connection(DB_BM)->create('menu', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->integer('pos')->default(0);
            $table->tinyInteger('category')->default(1);
            $table->string('menu_key', 64);
            $table->unique(['menu_key', 'status']);
        });

        Schema::connection(DB_BM)->create('role_menu_rel', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('menu_id');
        });

        Schema::connection(DB_BM)->create('user_menu_favorite', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('menu_key', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['admin_user_id', 'menu_key']);
        });
    }

    private function seedData(): void
    {
        DB::connection(DB_BM)->table('menu')->insert([
            ['id' => 1, 'name' => '菜单A', 'parent_id' => 0, 'status' => 1, 'pos' => 1, 'category' => 1, 'menu_key' => 'key_a'],
            ['id' => 2, 'name' => '菜单B', 'parent_id' => 0, 'status' => 1, 'pos' => 2, 'category' => 1, 'menu_key' => 'key_b'],
            // 无权限菜单：不在角色授权范围内
            ['id' => 3, 'name' => '菜单C', 'parent_id' => 0, 'status' => 1, 'pos' => 3, 'category' => 1, 'menu_key' => 'key_c'],
            // 已删除菜单：角色曾授权但状态已删除
            ['id' => 4, 'name' => '菜单D', 'parent_id' => 0, 'status' => 0, 'pos' => 4, 'category' => 1, 'menu_key' => 'key_d'],
        ]);

        DB::connection(DB_BM)->table('role_menu_rel')->insert([
            ['role_id' => self::ROLE_ID, 'menu_id' => 1],
            ['role_id' => self::ROLE_ID, 'menu_id' => 2],
            ['role_id' => self::ROLE_ID, 'menu_id' => 4],
        ]);
    }
}
