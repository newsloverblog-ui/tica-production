<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize()
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array
   */
  public function rules()
  {
    return [
      'id' => ['nullable', 'integer', 'exists:coupons,id'],
      'name' => ['required', 'string', 'max:120'],
      'code' => ['required', 'regex:/^[A-Z0-9_-]{3,30}$/', Rule::unique('coupons', 'code')->ignore($this->integer('id'))],
      'type' => ['required', Rule::in(['fixed', 'percentage'])],
      'value' => ['required', 'numeric', 'gt:0', function ($attribute, $value, $fail) {
        if ($this->input('type') === 'percentage' && (float) $value > 100) {
          $fail('A percentage discount cannot be more than 100%.');
        }
      }],
      'start_date' => ['required', 'date'],
      'end_date' => ['required', 'date', 'after_or_equal:start_date'],
      'events' => ['nullable', 'array'],
      'events.*' => ['integer', 'exists:events,id'],
    ];
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'name' => trim((string) $this->input('name')),
      'code' => mb_strtoupper(trim((string) $this->input('code'))),
    ]);
  }
}
