<?php

namespace Javaabu\Exports\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
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
            'created_at',
            'updated_at',
            'deleted_at',
        ], $export->allowedAttributes());
    }

    /** @test */
    public function it_only_includes_allowed_attributes(): void
    {
        $user = User::factory()->create();

        $export = new UsersExport();

        $this->assertEquals([
            $user->id,
            $user->email,
            $user->name,
            $user->created_at,
            $user->updated_at,
            $user->deleted_at,
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
            'Created At',
            'Updated At',
            'Deleted At',
        ], $export->headings());
    }
}
