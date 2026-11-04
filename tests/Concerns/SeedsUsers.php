<?php

namespace Javaabu\Exports\Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait SeedsUsers
{
    /**
     * Insert users quickly, without models or events
     */
    protected function seedUsers(int $count, int $batch_size = 1000, ?int $role_id = null): void
    {
        $now = now()->toDateTimeString();
        $start = (int) DB::table('users')->max('id');

        for ($offset = $start; $offset < $start + $count; $offset += $batch_size) {
            $rows = [];

            for ($i = $offset; $i < min($offset + $batch_size, $start + $count); $i++) {
                $rows[] = [
                    'email' => "user{$i}@example.mv",
                    'name' => "އަހުމަދު {$i}",
                    'password' => 'secret',
                    'is_admin' => $i % 2,
                    'role_id' => $role_id,
                    'status' => 'approved',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($rows);
        }
    }
}
