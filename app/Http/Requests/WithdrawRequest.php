<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth('organizer')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'withdraw_method' => 'required|integer|exists:withdraw_payment_methods,id',
            'withdraw_amount' => 'required|numeric|min:0.01',
            'additional_reference' => 'nullable|string|max:500',
        ];
    }
}
