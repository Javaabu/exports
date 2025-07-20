<?php

namespace Javaabu\Exports\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\Organization;
use Javaabu\Exports\Tests\TestSupport\Models\Role;
use Javaabu\Exports\Tests\TestSupport\Models\User;

class ModelExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_query_the_model(): void
    {
        $user = User::factory()->create();

        $export = new UsersExport();


        $result = $export->query()->get();

        $this->assertTrue($result->contains('id', $user->id));
    }

    /** @test */
    public function it_can_determine_the_allowed_attributes(): void
    {
        $export = new UsersExport();

        $this->assertEquals([
            'id',
            'email',
            'name',
            'is_admin',
            'role_id',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
            'userRole',
            'organizations',
        ], $export->allowedAttributes());
    }

    /** @test */
    public function it_only_includes_allowed_attributes(): void
    {
        $role = Role::factory()->create([
            'name' => 'Test Role',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_admin' => true,
        ]);

        $organization_1 = Organization::factory()->create([
            'name' => 'Organization 1',
        ]);

        $organization_2 = Organization::factory()->create([
            'name' => 'Organization 2',
        ]);

        $user->organizations()->sync([$organization_1->id, $organization_2->id]);

        $export = new UsersExport();

        $this->assertEquals([
            $user->id,
            $user->email,
            $user->name,
            'True',
            $user->role_id,
            $user->status->name,
            $user->created_at,
            $user->updated_at,
            $user->deleted_at,
            'Test Role',
            'Organization 1,Organization 2',
        ], $export->map($user));
    }

    /** @test */
    public function it_can_generate_headings(): void
    {
        $export = new UsersExport();

        $this->assertEquals([
            'Id',
            'Email',
            'Name',
            'Is Admin',
            'Role Id',
            'Status',
            'Created At',
            'Updated At',
            'Deleted At',
            'User Role',
            'Organizations',
        ], $export->headings());
    }
}
