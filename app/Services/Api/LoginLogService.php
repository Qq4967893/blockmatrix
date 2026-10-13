<?php

namespace App\Services\Api;

use App\Repositories\Api\CountryRepository;
use App\Repositories\Api\LoginLogRepository;
use DateTimeInterface;
use ReflectionClass;
use Scaffold\Framework\Models\Api\LoginLog;
use Throwable;

class LoginLogService
{
    public function __construct(
        private LoginLogRepository $loginLogRepo,
        private CountryRepository $countryRepo,
    ) {
    }

    /**
     * 用户登录日志列表
     *
     * @param array{
     *   keywords?: string|null,
     *   keyword_type?: string|null,
     *   is_latest?: int|string|null,
     *   country?: string|null,
     *   login_type?: string|null,
     *   login_time_start?: string|null,
     *   login_time_end?: string|null
     * } $filters 筛选条件
     * @param int $limit  每页条数
     * @param int $offset 分页偏移量
     *
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        [$rows, $total] = $this->loginLogRepo->getList($filters, $limit, $offset);
        $countryNames = $this->countryNames($rows);

        $data = array_map(function (mixed $row) use ($countryNames): array {
            $country = strtoupper((string) $this->valueFrom($row, 'country', ''));
            $isLatest = (int) $this->valueFrom($row, 'is_latest', 0);
            $loginType = (string) $this->valueFrom($row, 'login_type', '');

            return [
                'id' => (int) $this->valueFrom($row, 'id', 0),
                'request_id' => (string) $this->valueFrom($row, 'request_id', ''),
                'user_code' => (int) $this->valueFrom($row, 'user_code', 0),
                'email' => (string) $this->valueFrom($row, 'email', ''),
                'is_latest' => $isLatest,
                'is_latest_text' => $this->booleanText($isLatest),
                'ip' => (string) $this->valueFrom($row, 'ip', ''),
                'country' => $country,
                'country_text' => $countryNames[$country] ?? '',
                'user_agent' => (string) $this->valueFrom($row, 'user_agent', ''),
                'device_uuid' => (string) $this->valueFrom($row, 'device_uuid', ''),
                'timezone' => (string) $this->valueFrom($row, 'timezone', ''),
                'canvas_hash' => (string) $this->valueFrom($row, 'canvas_hash', ''),
                'audio_hash' => (string) $this->valueFrom($row, 'audio_hash', ''),
                'screen_resolution' => (string) $this->valueFrom($row, 'screen_resolution', ''),
                'device_hash' => (string) $this->valueFrom($row, 'device_hash', ''),
                'login_type' => $loginType,
                'login_type_text' => $this->loginTypeText($loginType),
                'login_time' => $this->formatDateTime($this->valueFrom($row, 'created_at')),
            ];
        }, $rows);

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    /**
     * 用户登录日志筛选项
     *
     * @return array{login_types: array<int, array{key: string, value: string}>}
     */
    public function options(): array
    {
        return [
            'login_types' => array_map(fn (string $type): array => [
                'key' => $type,
                'value' => $this->loginTypeText($type),
            ], $this->loginTypes()),
        ];
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, string>
     */
    private function countryNames(array $rows): array
    {
        $countryCodes = array_values(array_unique(array_filter(array_map(function (mixed $row): string {
            return strtoupper((string) $this->valueFrom($row, 'country', ''));
        }, $rows))));

        if ($countryCodes === []) {
            return [];
        }

        $countries = $this->countryRepo->getByAlphas($countryCodes, ['alpha', 'name']);
        $names = [];
        foreach ($countries as $country) {
            $alpha = strtoupper((string) ($country['alpha'] ?? ''));
            if ($alpha === '') {
                continue;
            }

            $names[$alpha] = $this->resolveCountryName($country['name'] ?? null) ?? '';
        }

        return $names;
    }

    private function resolveCountryName(mixed $name): ?string
    {
        $currentLang = config('app.locale', 'en_US');

        if (is_array($name)) {
            return $name[$currentLang] ?? reset($name) ?: null;
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded[$currentLang] ?? reset($decoded) ?: null;
            }

            return $name;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function loginTypes(): array
    {
        $reflection = new ReflectionClass(LoginLog::class);

        return array_values(array_filter(
            $reflection->getConstants(),
            static fn (mixed $value, string $key): bool => str_starts_with($key, 'TYPE_') && is_string($value),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    private function loginTypeText(string $type): string
    {
        if ($type === '') {
            return '-';
        }

        $key = "user.login_type.$type";
        $label = trans($key);

        return $label === $key ? ucfirst($type) : (string) $label;
    }

    private function booleanText(int $value): string
    {
        return (string) trans('user.boolean.' . ($value === 1 ? 'yes' : 'no'));
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function valueFrom(mixed $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        if (is_object($row)) {
            return $row->{$key} ?? $default;
        }

        return $default;
    }
}
