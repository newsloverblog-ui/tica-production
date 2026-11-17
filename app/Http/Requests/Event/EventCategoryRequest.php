<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventCategoryRequest extends FormRequest
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
   * @return array<string, mixed>
   */
  public function rules()
  {
    return [
      'language_id' => ['required', 'integer', 'exists:languages,id'],
      'icon' => ['required', Rule::in(array_keys(config('category_icons')))],
      'name' => ['required', 'string', 'max:120'],
      'status' => ['required', 'boolean'],
      'serial_number' => ['required', 'integer', 'min:1', 'max:10000'],
      'is_featured' => ['required', Rule::in(['yes', 'no'])],
    ];
  }

  public function messages()
  {
    return [
      'language_id.required' => 'The language field is required.',
      'icon.required' => 'Pick an icon for this category.',
      'icon.in' => 'Pick an icon from the list.'
    ];
  }
}
