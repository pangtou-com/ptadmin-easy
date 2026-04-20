<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Feature\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PTAdmin\Easy\Core\Query\BuilderQueryApplier;

final class BuilderQueryApplierTest extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    public function test_it_reuses_filters_sorts_and_keyword_rules_for_query_builder_and_eloquent_builder(): void
    {
        $table = 'builder_query_articles';
        $applier = new BuilderQueryApplier();

        Schema::create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 100);
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedInteger('tenant_id')->default(0);
        });

        DB::table($table)->insert([
            ['title' => 'alpha news', 'status' => 1, 'tenant_id' => 1],
            ['title' => 'beta page', 'status' => 0, 'tenant_id' => 1],
            ['title' => 'gamma news', 'status' => 1, 'tenant_id' => 2],
        ]);

        $dsl = [
            'filters' => [
                ['field' => 'status', 'operator' => '=', 'value' => 1],
            ],
            'sorts' => [
                ['field' => 'id', 'direction' => 'desc'],
            ],
            'keyword' => 'news',
            'keyword_fields' => ['title'],
        ];

        $queryBuilderResult = $applier->fetch(
            DB::table($table),
            $dsl,
            [
                'allowed_filters' => ['status', 'tenant_id'],
                'allowed_sorts' => ['id'],
            ]
        );

        $model = new class() extends Model {
            protected $table = 'builder_query_articles';
            public $timestamps = false;
            protected $guarded = [];
        };

        $eloquentResult = $applier->fetch(
            $model->newQuery(),
            $dsl,
            [
                'allowed_filters' => ['status', 'tenant_id'],
                'allowed_sorts' => ['id'],
            ]
        );

        $paginated = $applier->fetch(
            DB::table($table),
            [
                'filters' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 1],
                ],
                'sorts' => [
                    ['field' => 'id', 'direction' => 'asc'],
                ],
                'limit' => 1,
                'page' => 2,
                'paginate' => true,
            ],
            [
                'allowed_filters' => ['status'],
                'allowed_sorts' => ['id'],
            ]
        );

        $ignoredUnsafeField = $applier->fetch(
            DB::table($table),
            [
                'filters' => [
                    ['field' => 'status desc', 'operator' => '=', 'value' => 1],
                ],
                'sorts' => [
                    ['field' => 'id desc', 'direction' => 'desc'],
                ],
            ]
        );

        self::assertSame(['gamma news', 'alpha news'], $queryBuilderResult->pluck('title')->all());
        self::assertSame(['gamma news', 'alpha news'], $eloquentResult->pluck('title')->all());
        self::assertSame(2, $paginated->currentPage());
        self::assertSame(1, $paginated->perPage());
        self::assertSame(2, $paginated->total());
        self::assertSame(['gamma news'], collect($paginated->items())->pluck('title')->all());
        self::assertCount(3, $ignoredUnsafeField);
    }
}
