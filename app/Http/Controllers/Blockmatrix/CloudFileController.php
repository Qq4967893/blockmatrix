<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use Scaffold\Framework\Utils\S3;
use App\Http\Requests\CloudFile\S3Request;

class CloudFileController extends BaseController
{
    public function sign(S3Request $request)
    {
        $data = app(S3::class)->config($request->key, $request->content_type);

        return $this->succeed($data);
    }
}
