<?php

namespace App\Services;

use App\Models\AssetPhotoUploadModel;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class PhotoUploadService
{
    public const MAX_FILE_SIZE = 1048576;

    public function storeTemporaryUpload(UploadedFile $file, int $uploadedBy): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException($file->getErrorString());
        }

        $mimeType = (string) $file->getMimeType();
        if (! str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('Only image files are allowed.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Photo must not exceed 1 MB.');
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientExtension() ?: 'jpg');
        $uploadId  = 'upl_' . bin2hex(random_bytes(8));
        $relative  = 'tmp/' . gmdate('Y/m') . '/' . $uploadId . '.' . $extension;
        $absolute  = $this->absolutePath($relative);

        $this->ensureDirectory(dirname($absolute));

        if (! $file->move(dirname($absolute), basename($absolute))) {
            throw new RuntimeException('Failed to store uploaded photo.');
        }

        $storedFile = new File($absolute);
        $imageInfo  = @getimagesize($absolute) ?: [null, null];

        $data = [
            'upload_id'       => $uploadId,
            'asset_id'        => null,
            'file_name'       => $storedFile->getBasename(),
            'disk'            => 'local',
            'file_path'       => $relative,
            'mime_type'       => $mimeType,
            'extension'       => $extension,
            'file_size_bytes' => $storedFile->getSize(),
            'width'           => $imageInfo[0],
            'height'          => $imageInfo[1],
            'sha256_checksum' => hash_file('sha256', $absolute),
            'uploaded_by'     => $uploadedBy,
            'created_at'      => gmdate('Y-m-d H:i:s'),
            'expires_at'      => gmdate('Y-m-d H:i:s', time() + 86400),
            'consumed_at'     => null,
        ];

        $model = model(AssetPhotoUploadModel::class);
        if (! $model->insert($data)) {
            @unlink($absolute);
            throw new RuntimeException('Failed to save uploaded photo metadata.');
        }

        return $data;
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    public function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to prepare upload directory.');
        }
    }
}
