<?php

namespace Tests\Unit;

use App\Models\Allocation;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RoleAndAllocationTest extends TestCase
{
    public function test_roles_separate_operational_and_read_only_access(): void
    {
        foreach (User::ROLES as $i => $role) {
            $user = new User(['role' => $role]);
            $this->assertSame($i < 2, $user->isOperator());
            $this->assertSame($i === 0, $user->isAdmin());
        }
    }

    public function test_overdue_is_derived_without_mutating_history(): void
    {
        Carbon::setTestNow('2026-09-20');
        try {
            $a = (new Allocation)->setRawAttributes(['status' => 'Active', 'expected_return_date' => '2026-09-19']);
            $this->assertSame('Overdue', $a->display_status);
            $this->assertSame('Active', $a->status);
            $a->status = 'Returned';
            $this->assertSame('Returned', $a->display_status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
