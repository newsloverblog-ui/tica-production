<?php

namespace Tests\Unit;

use App\Services\TicaUi\OrganizerAttendeeDirectory;
use Tests\TestCase;

/** Orders and attendees: the date range narrows the list and the CSV the same way. */
class AttendeeDateFilterTest extends TestCase
{
  public function test_date_range_is_inclusive_by_day(): void
  {
    $query = $this->directoryQuery(['from' => '2026-11-01', 'to' => '2026-11-13']);
    $bindings = $query->getBindings();

    // Quote style differs between MySQL and SQLite, so compare without it.
    $sql = str_replace(['"', '`'], '', $query->toSql());
    $this->assertStringContainsString('bookings.created_at >= ?', $sql);
    $this->assertStringContainsString('bookings.created_at <= ?', $sql);
    $this->assertContains('2026-11-01 00:00:00', $bindings);
    $this->assertContains('2026-11-13 23:59:59', $bindings);
  }

  public function test_no_dates_means_no_date_bounds(): void
  {
    $this->assertStringNotContainsString('bookings.created_at >=', str_replace(['"', '`'], '', $this->directoryQuery([])->toSql()));
  }

  public function test_page_has_date_filters_and_csv_uses_the_same_query(): void
  {
    $page = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
    $js = file_get_contents(public_path('assets/tica-ui/js/tica.js'));

    $this->assertStringContainsString('id="od-from"', $page);
    $this->assertStringContainsString('id="od-to"', $page);
    $this->assertStringContainsString("attendees.csv?'+attQuery()", $js);
    $this->assertStringContainsString("attendees?'+attQuery()", $js);
  }

  private function directoryQuery(array $filters)
  {
    $method = new \ReflectionMethod(OrganizerAttendeeDirectory::class, 'query');
    return $method->invoke(app(OrganizerAttendeeDirectory::class), 7, $filters, null);
  }
}
