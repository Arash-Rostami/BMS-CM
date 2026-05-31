<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'PersonNationalCode' => ['required', 'string'],
            'UserRoleID' => ['required', 'numeric'],
            'DocumentDate' => ['required', 'date'],
            'UserSellType' => ['required', 'integer'],
            'StatusAppointment' => ['nullable', 'integer'],
            'DocNumber' => ['required', 'string'],
            'FromPostalCode' => ['nullable', 'string'],
            'ToPostalCode' => ['nullable', 'string'],
            'DocumentDescription' => ['nullable', 'string'],

            'BuyerInfo' => ['nullable', 'array'],
            'BuyerInfo.NationalId' => ['required_with:BuyerInfo', 'string'],
            'BuyerInfo.Name' => ['nullable', 'string'],
            'BuyerInfo.Mobile' => ['nullable', 'string'],

            'StuffsIn' => ['required', 'array'],
            'StuffsIn.*.Code' => ['required', 'string'],
            'StuffsIn.*.Count' => ['required', 'numeric'],
            'StuffsIn.*.Price' => ['required', 'numeric'],
            'StuffsIn.*.Discount' => ['nullable', 'numeric'],
            'StuffsIn.*.VAT' => ['nullable', 'numeric'],

            'WayBill' => ['nullable', 'array'],
            'WayBill.Status' => ['required_with:WayBill', 'integer'],
            'WayBill.Number' => ['nullable', 'string'],
            'WayBill.Serial' => ['nullable', 'string'],
        ];
    }
}
