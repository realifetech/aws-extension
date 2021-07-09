<?php

namespace AwsExtension\S3;

use Aws\S3\S3Client;
use AwsExtension\S3\Exception\ImageDownloadException;
use AwsExtension\S3\Exception\S3ImageUploadException;
use finfo;
use JetBrains\PhpStorm\ArrayShape;

class ImageService extends S3Client
{
    public function __construct(
        array $args,
        private string $s3Bucket
    ) {
        parent::__construct($args);
    }

    /**
     * @throws ImageDownloadException
     * @throws S3ImageUploadException
     */
    public function uploadImage(string $imageUrl, string $imageDomain, string $key, string $acl = 'private'): ?string
    {
        if (!$imageBody = file_get_contents($imageUrl)) {
            throw new ImageDownloadException('Cannot download the image from url');
        }

        $mimeType = $this->getMimeType($imageBody);
        $ext = $this->getExtension($mimeType);
        $fileName = md5($imageUrl) . '.' . $ext;
        $key = "{$key}/{$fileName}";
        $options = $this->getOptions($mimeType);
        $result = $this->upload($this->s3Bucket, $key, $imageBody, $acl, $options)->toArray();

        if ((int)$result["@metadata"]["statusCode"] !== 200) {
            throw new S3ImageUploadException('Cannot upload the image to S3');
        }

        $imageData = [
            "bucket" => $this->s3Bucket,
            "key" => $key,
        ];

        return rtrim($imageDomain, '/') . '/' . base64_encode(json_encode($imageData));
    }

    private function getMimeType(string $imageBody): string
    {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);

        return $fileInfo->buffer($imageBody);
    }

    private function getExtension(string $mimeType): ?string
    {
        $mimeTypeInfo = explode("/", $mimeType);

        return $mimeTypeInfo[1] ?? '';
    }

    #[ArrayShape(['params' => "string[]"])] private function getOptions(string $mimeType): array
    {
        return [
            'params' => [
                'ContentType' => $mimeType
            ]
        ];
    }
}
