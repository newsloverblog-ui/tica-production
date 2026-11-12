<?php

namespace App\Http\Requests\Advertisement;

use App\Rules\ImageMimeTypeRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
      'ad_type' => 'required|in:banner,adsense',
      'resolution_type' => 'required|integer|in:1,2,3',
      'image' => [
        'required_if:ad_type,banner',
        $this->hasFile('image') ? new ImageMimeTypeRule() : ''
      ],
      'url' => [
        'required_if:ad_type,banner',
        $this->filled('url') ? 'url' : ''
      ],
      'slot' => 'required_if:ad_type,adsense|nullable|regex:/^[0-9]{5,32}$/'
    ];
  }
}
