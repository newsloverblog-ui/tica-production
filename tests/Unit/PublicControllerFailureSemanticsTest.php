<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\BlogController;
use App\Http\Controllers\FrontEnd\OrganizerController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicControllerFailureSemanticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    public function test_blog_dependency_failures_are_not_disguised_as_not_found(): void
    {
        $this->expectException(QueryException::class);

        (new BlogController())->details('unavailable-blog');
    }

    public function test_organizer_dependency_failures_are_not_disguised_as_not_found(): void
    {
        $this->expectException(QueryException::class);

        (new OrganizerController())->details(
            Request::create('/organizer/details/999/unavailable', 'GET'),
            999,
            'unavailable'
        );
    }

    public function test_shop_flutterwave_callback_does_not_render_a_not_found_page_for_exceptions(): void
    {
        $source = file_get_contents(app_path(
            'Http/Controllers/FrontEnd/Shop/PaymentGateway/FlutterwaveController.php'
        ));

        $this->assertIsString($source);
        $this->assertStringNotContainsString("return view('errors.404')", $source);
        $this->assertStringContainsString('throw $e;', $source);
    }

    public function test_blog_category_lookup_is_language_scoped_and_fails_closed(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('language_id');
            $table->string('slug');
        });
        DB::table('blog_categories')->insert([
            ['id' => 11, 'language_id' => 1, 'slug' => 'news'],
            ['id' => 22, 'language_id' => 2, 'slug' => 'news'],
        ]);

        $controller = new class extends BlogController
        {
            public function categoryId(int $languageId, string $slug): int
            {
                return $this->resolveCategoryId($languageId, $slug);
            }
        };

        $this->assertSame(11, $controller->categoryId(1, 'news'));
        $this->assertSame(22, $controller->categoryId(2, 'news'));

        $this->expectException(ModelNotFoundException::class);
        $controller->categoryId(1, 'missing');
    }
}
