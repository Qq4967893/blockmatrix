<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base request for API endpoints to unify JSON validation responses.
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * Always expect JSON to avoid redirect responses.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Convert validation failures to a consistent JSON structure.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->all();

        throw new HttpResponseException(response()->json([
            'is_succ'    => false,
            'code'       => 422,
            'message'    => $errors[0] ?? trans('public.message.data_error'),
            'request_id' => data_get($GLOBALS, 'request-id', ''),
        ], 422));
    }
}
