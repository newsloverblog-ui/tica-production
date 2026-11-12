<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Services\BookingCompletionUrlService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingCompletionUrlServiceTest extends TestCase
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

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('booking_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_completion_url_is_temporary_signed_and_tamper_evident(): void
    {
        $url = app(BookingCompletionUrlService::class)->make(42, 91, 'offline');

        $this->assertTrue(Request::create($url)->hasValidSignature());
        $this->assertStringContainsString('via=offline', $url);
        $this->assertFalse(Request::create(str_replace('id=42', 'id=43', $url))->hasValidSignature());
        $this->assertFalse(Request::create(str_replace('booking_id=91', 'booking_id=92', $url))->hasValidSignature());
    }

    public function test_completion_route_requires_a_valid_signature(): void
    {
        $route = Route::getRoutes()->getByName('event_booking.complete');

        $this->assertNotNull($route);
        $this->assertContains('signed', $route->gatherMiddleware());
    }

    public function test_completion_booking_is_bound_to_the_signed_event(): void
    {
        $booking = Booking::query()->create([
            'event_id' => 42,
            'booking_id' => 'public-reference',
        ]);

        $resolved = app(BookingCompletionUrlService::class)->booking(42, $booking->id);
        $this->assertTrue($booking->is($resolved));

        $this->expectException(ModelNotFoundException::class);
        app(BookingCompletionUrlService::class)->booking(43, $booking->id);
    }

    public function test_controllers_do_not_generate_unsigned_completion_links(): void
    {
        $violations = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            $contents = $file->getContents();
            if (str_contains($contents, "route('event_booking.complete")) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $violations, 'Unsigned booking completion redirects remain: '.implode(', ', $violations));
    }
}
