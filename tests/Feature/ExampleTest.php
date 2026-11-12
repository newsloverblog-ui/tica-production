<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class ExampleTest extends TestCase
{
  /**
   * A basic test example.
   *
   * @return void
   */
  public function test_home_page_smoke_when_the_application_database_is_available()
  {
    try {
      DB::connection()->getPdo();
    } catch (Throwable $exception) {
      $this->markTestSkipped('The full home-page smoke test requires the production-like MySQL fixture database.');
    }

    $response = $this->get('/');

    $response->assertStatus(200);
  }
}
