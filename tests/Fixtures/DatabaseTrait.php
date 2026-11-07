<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

use think\db\ConnectionInterface;

trait DatabaseTrait
{
    protected function connection(): ConnectionInterface
    {
        /** @var \think\DbManager $db */
        $db = $GLOBALS['think_openspout_db'];

        return $db->connect('sqlite');
    }

    /**
     * 重置 user 表数据。
     *
     * @param array<int, array<string, mixed>> $rows
     */
    protected function resetUserTable(array $rows = []): void
    {
        if ($rows === []) {
            $rows = $this->defaultUsers();
        }

        $connection = $this->connection();
        $connection->execute('DELETE FROM user');
        $connection->execute("DELETE FROM sqlite_sequence WHERE name = 'user'");

        foreach ($rows as $row) {
            $connection->execute(
                'INSERT INTO user (name, email, status, balance, created_at) VALUES (?, ?, ?, ?, ?)',
                [
                    $row['name'] ?? null,
                    $row['email'] ?? null,
                    $row['status'] ?? 1,
                    $row['balance'] ?? 0,
                    $row['created_at'] ?? null,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultUsers(): array
    {
        return [
            ['name' => '张三', 'email' => 'zhangsan@example.com', 'status' => 1, 'balance' => 100.5, 'created_at' => '2024-01-01 10:00:00'],
            ['name' => '李四', 'email' => 'lisi@example.com', 'status' => 0, 'balance' => 0, 'created_at' => '2024-01-02 10:00:00'],
            ['name' => '王五', 'email' => 'wangwu@example.com', 'status' => 1, 'balance' => 88.25, 'created_at' => '2024-01-03 10:00:00'],
        ];
    }
}