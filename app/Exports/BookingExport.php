<?php

namespace App\Exports;

use App\Models\BasicSettings\Basic;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

use Maatwebsite\Excel\Concerns\FromCollection;

class BookingExport implements FromCollection, WithHeadings, WithMapping
{
  public $bookings;

  public function __construct($bookings)
  {
    $this->bookings = $bookings;
  }
  /**
   * @return \Illuminate\Support\Collection
   */
  public function collection()
  {
    return $this->bookings;
  }

  public function map($bookings): array
  {
    $bs = Basic::firstOrFail();
    return [
      $bookings->booking_id,
      $bookings->title,
      $bookings->customerfname.' '.$bookings->customerlname,

      ($bs->base_currency_symbol_position == 'left' ? $bs->base_currency_symbol : '') . $bookings->discount . ($bs->base_currency_symbol_position == 'right' ? $bs->base_currency_symbol : ''),

      ($bs->base_currency_symbol_position == 'left' ? $bs->base_currency_symbol : '') . (empty($bookings->early_bird_discount) ? 0 : $bookings->early_bird_discount) . ($bs->base_currency_symbol_position == 'right' ? $bs->base_currency_symbol : ''),

      $bookings->quantity,

      ($bs->base_currency_symbol_position == 'left' ? $bs->base_currency_symbol : '') . (empty($bookings->price) ? 0 : $bookings->price) . ($bs->base_currency_symbol_position == 'right' ? $bs->base_currency_symbol : ''),

      $bookings->fname.' '.$bookings->lname,
      $bookings->email,
      $bookings->phone,
      $bookings->city,
      $bookings->state,
      $bookings->country,
      $bookings->zip_code,
      $bookings->paymentMethod,
      $bookings->paymentStatus,
      $bookings->created_at
    ];
  }

  public function headings(): array
  {
    return [
      'Booking Id', 'Event', 'Customer Name', 'Discount', 'Early Bird Discount', 'Quantity', 'Total', 'Name', 'Email', 'Phone', 'City', 'State', 'Country', 'Zip Code', 'Gateway', 'Payment Status', 'Date'
    ];
  }
}
