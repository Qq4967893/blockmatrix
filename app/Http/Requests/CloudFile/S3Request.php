<?php

namespace App\Http\Requests\CloudFile;

use App\Http\Requests\ApiRequest;

class S3Request extends ApiRequest
{
    public function rules()
    {
        return [
            'key'          => ['required', 'string', 'regex:' . FILE_PATTERN],
            'content_type' => 'required|string|in:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime',
        ];
    }

    public function messages()
    {
        return [
            // key
            'key.required' => 'The file key field is required.',
            'key.string'   => 'The file key must be a string.',
            'key.regex'    => 'The file key format is invalid.',

            // content_type
            'content_type.required' => 'The content type field is required.',
            'content_type.string'   => 'The content type must be a string.',
            'content_type.in'       => 'The selected content type is invalid. Allowed types: image/jpeg, image/png, image/webp, image/gif, video/mp4, video/quicktime.',
        ];
    }
}
