<?php

namespace App\Controllers\Api\V1;

use App\Services\PhotoUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class UploadController extends BaseApiController
{
    public function photo(): ResponseInterface
    {
        $user = $this->currentTokenUser();

        $file = $this->request->getFile('photo');
        if ($file === null) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['photo' => ['The photo field is required.']]
            );
        }

        try {
            $upload = (new PhotoUploadService())->storeTemporaryUpload($file, (int) ($user?->id ?? 0));

            return $this->respondSuccess(
                'Photo uploaded',
                [
                    'upload_id'       => $upload['upload_id'],
                    'file_name'       => $upload['file_name'],
                    'file_path'       => $upload['file_path'],
                    'file_size_bytes' => $upload['file_size_bytes'],
                ]
            );
        } catch (RuntimeException $e) {
            return $this->respondError(
                $e->getMessage(),
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['photo' => [$e->getMessage()]]
            );
        }
    }
}
