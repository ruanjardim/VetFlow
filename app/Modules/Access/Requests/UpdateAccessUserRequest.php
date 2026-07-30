<?php

namespace App\Modules\Access\Requests;

use Illuminate\Validation\Rule;

class UpdateAccessUserRequest extends StoreAccessUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'] = [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore((int) $this->route('user')),
        ];
        $rules['password'][0] = 'nullable';

        return $rules;
    }
}
