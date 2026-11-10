<?php

namespace Javaabu\Exports\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Exports\Columns\Column;
use Javaabu\Exports\ModelExport;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Enums\Atolls;
use Javaabu\Exports\Tests\TestSupport\Enums\UserStatuses;
use Javaabu\Exports\Tests\TestSupport\Exports\IslandsExport;
use Javaabu\Exports\Tests\TestSupport\Exports\PlainUsersExport;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\Island;
use Javaabu\Exports\Tests\TestSupport\Models\Organization;
use Javaabu\Exports\Tests\TestSupport\Models\Role;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ModelExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_query_the_model(): void
    {
        $user = User::factory()->create();

        $result = UsersExport::make()->query()->get();

        $this->assertTrue($result->contains('id', $user->id));
    }

    #[Test]
    public function it_includes_table_columns_without_hidden_attributes_and_secrets(): void
    {
        $this->assertEquals([
            'id',
            'email',
            'name',
            'is_admin',
            'role',
            'status',
            'birthday',
            'settings',
            'created_at',
            'updated_at',
            'deleted_at',
        ], PlainUsersExport::make()->allowedAttributes());
    }

    #[Test]
    public function it_replaces_foreign_keys_with_included_relations(): void
    {
        $this->assertEquals([
            'id',
            'email',
            'name',
            'is_admin',
            'status',
            'birthday',
            'settings',
            'created_at',
            'updated_at',
            'deleted_at',
            'userRole',
            'organizations',
        ], UsersExport::make()->allowedAttributes());
    }

    #[Test]
    public function it_can_keep_foreign_keys_and_leave_out_timestamps(): void
    {
        config()->set('exports.resolve_foreign_keys', false);
        config()->set('exports.timestamps', false);

        $this->assertEquals([
            'id',
            'email',
            'name',
            'is_admin',
            'role_id',
            'status',
            'birthday',
            'settings',
        ], PlainUsersExport::make()->allowedAttributes());
    }

    #[Test]
    public function it_excludes_the_configured_columns(): void
    {
        config()->set('exports.exclude', ['email', 'birth*']);

        $columns = PlainUsersExport::make()->allowedAttributes();

        $this->assertNotContains('email', $columns);
        $this->assertNotContains('birthday', $columns);
        $this->assertContains('api_token', $columns);
    }

    #[Test]
    public function it_formats_values_for_people(): void
    {
        $role = Role::factory()->create(['name' => 'ވެރިޔާ']);

        $user = User::factory()->create([
            'email' => 'ahmed@example.mv',
            'name' => 'އަހުމަދު',
            'is_admin' => true,
            'role_id' => $role->id,
            'status' => UserStatuses::APPROVED,
            'birthday' => '1990-05-01',
            'settings' => ['theme' => 'dark', 'island' => 'މާލެ'],
        ]);

        $user->forceFill(['created_at' => '2026-11-04 04:30:00', 'updated_at' => '2026-11-04 04:30:00'])->save();

        $user->organizations()->sync([
            Organization::factory()->create(['name' => 'ޖާވާބު'])->id,
            Organization::factory()->create(['name' => 'ސިފައިން'])->id,
        ]);

        $export = UsersExport::make();
        $row = $export->map($export->query()->first());

        $this->assertEquals([
            $user->id,
            'ahmed@example.mv',
            'އަހުމަދު',
            'Yes',
            'Approved',
            '1990-05-01',
            '{"theme":"dark","island":"މާލެ"}',
            '2026-11-04 04:30:00',
            '2026-11-04 04:30:00',
            null,
            'ވެރިޔާ',
            'ޖާވާބު, ސިފައިން',
        ], $row);
    }

    #[Test]
    public function it_converts_dates_to_the_configured_timezone(): void
    {
        config()->set('exports.timezone', 'Indian/Maldives');

        $user = User::factory()->create();
        $user->forceFill(['created_at' => '2026-11-04 04:30:00'])->save();

        $export = new class extends PlainUsersExport
        {
            public function columns(): array
            {
                return ['created_at'];
            }
        };

        $this->assertEquals(['2026-11-04 09:30:00'], $export->map($user->fresh()));
    }

    #[Test]
    public function it_uses_enum_labels(): void
    {
        $island = Island::create(['name' => 'ހިތަދޫ', 'atoll' => Atolls::SEENU, 'inhabited' => false]);

        $export = IslandsExport::make();

        $this->assertEquals('ސ', $export->map($island)[2]);
        $this->assertEquals('No', $export->map($island)[3]);
    }

    #[Test]
    public function it_generates_readable_headings(): void
    {
        $this->assertEquals([
            'ID',
            'Email',
            'Name',
            'Is Admin',
            'Role',
            'Status',
            'Birthday',
            'Settings',
            'Created At',
            'Updated At',
            'Deleted At',
        ], PlainUsersExport::make()->headings());
    }

    #[Test]
    public function it_takes_headings_from_validation_attribute_names(): void
    {
        app('translator')->addLines(['validation.attributes.email' => 'އީމެއިލް'], 'en');

        $this->assertEquals('އީމެއިލް', PlainUsersExport::make()->headings()[1]);
    }

    #[Test]
    public function it_takes_headings_from_the_model(): void
    {
        $this->assertEquals(
            ['ID', 'ރަށުގެ ނަން', 'Atoll', 'Inhabited', 'Population', 'Created At', 'Updated At'],
            IslandsExport::make()->headings(),
        );
    }

    #[Test]
    public function it_translates_headings_and_values(): void
    {
        app()->setLocale('dv');

        $island = Island::create(['name' => 'މާލެ', 'atoll' => Atolls::KAAFU])->fresh();

        $export = IslandsExport::make();

        $this->assertEquals('ނަން', PlainUsersExport::make()->headings()[2]);
        $this->assertEquals('އާނ', $export->map($island)[3]);
    }

    #[Test]
    public function it_supports_column_definitions(): void
    {
        $role = Role::factory()->create(['name' => 'ވެރިޔާ']);

        $user = User::factory()->create(['name' => 'އައިޝާ', 'role_id' => $role->id, 'birthday' => '1995-01-31']);
        $user->organizations()->sync([
            Organization::factory()->create(['name' => 'ޖާވާބު'])->id,
            Organization::factory()->create(['name' => 'ސިފައިން'])->id,
        ]);

        $export = new class extends PlainUsersExport
        {
            public function columns(): array
            {
                return [
                    'name',
                    'userRole.name',
                    Column::make('organizations.name')->label('ޖަމްޢިއްޔާތައް'),
                    Column::make('birthday')->format(fn ($value) => $value->format('d/m/Y')),
                    Column::make('greeting')->label('Greeting')->value(fn (User $user) => $user->greeting()),
                ];
            }
        };

        $this->assertEquals(['userRole', 'organizations'], $export->eagerLoads());
        $this->assertEquals(['Name', 'User Role', 'ޖަމްޢިއްޔާތައް', 'Birthday', 'Greeting'], $export->headings());
        $this->assertEquals(
            ['އައިޝާ', 'ވެރިޔާ', 'ޖާވާބު, ސިފައިން', '31/01/1995', 'ހެލޯ އައިޝާ'],
            $export->map($export->query()->first()),
        );
    }

    #[Test]
    public function it_names_non_display_attributes_of_relations_in_headings(): void
    {
        $export = new class extends PlainUsersExport
        {
            public function columns(): array
            {
                return ['organizations.id', 'role_id'];
            }
        };

        $this->assertEquals(['Organizations ID', 'Role ID'], $export->headings());
    }

    #[Test]
    public function it_adds_eager_loads_to_a_given_query(): void
    {
        $export = UsersExport::make(User::query()->where('is_admin', true));

        $this->assertEquals(['userRole', 'organizations'], array_keys($export->query()->getEagerLoads()));
    }

    #[Test]
    public function it_shows_related_models_without_a_name_by_their_key(): void
    {
        $user = User::factory()->create();

        $export = new class extends PlainUsersExport
        {
            public function columns(): array
            {
                return ['name'];
            }
        };

        $this->assertEquals($user->id, $export->displayName($user->setAttribute('name', null)));
    }

    #[Test]
    public function it_keeps_v1_overrides_working(): void
    {
        $role = Role::factory()->create(['name' => 'ވެރިޔާ']);
        $user = User::factory()->create(['name' => 'އަހުމަދު', 'role_id' => $role->id]);

        $export = new class extends UsersExport
        {
            public function allowedAttributes(): array
            {
                return ['name', 'userRole'];
            }

            public function formatValue($model, string $attribute, mixed $value): mixed
            {
                if ($attribute === 'userRole' && $value instanceof Model) {
                    return "#{$value->id}";
                }

                return parent::formatValue($model, $attribute, $value);
            }

            public function headings(): array
            {
                return ['ނަން', 'ރޯލް'];
            }
        };

        $this->assertEquals(['ނަން', 'ރޯލް'], $export->headings());
        $this->assertEquals(['އަހުމަދު', "#{$role->id}"], $export->map($user));
    }

    #[Test]
    public function it_does_not_call_methods_that_are_not_relations(): void
    {
        $export = new class extends PlainUsersExport
        {
            public function columns(): array
            {
                return ['name', 'greeting', 'delete'];
            }
        };

        $this->assertEquals([], $export->eagerLoads());
    }

    #[Test]
    public function it_names_the_file_after_the_model(): void
    {
        $this->assertEquals('users', UsersExport::make()->filename());
        $this->assertEquals('islands', IslandsExport::make()->filename());
    }

    #[Test]
    public function it_can_be_made_from_any_model_export(): void
    {
        $this->assertInstanceOf(ModelExport::class, UsersExport::make(User::query()));
    }
}
