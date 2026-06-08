<?php

namespace App\Support\GeoFlow;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 将上传图片保存到 storage/app/public/uploads/images/... 并返回统一元数据。
 */
final class PublicImageUploader
{
    /**
     * @return array{filename:string,file_name:string,original_name:string,file_path:string,file_size:int,mime_type:string,width:int,height:int}
     */
    public static function store(UploadedFile $file): array
    {
        $uploadDirectory = 'images/'.date('Y/m');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $directory = 'uploads/'.$uploadDirectory;
        if (! Storage::disk('public')->exists($directory) && ! Storage::disk('public')->makeDirectory($directory)) {
            throw new \RuntimeException('创建图片上传目录失败：storage/app/public/'.$directory);
        }

        $storedRelativePath = Storage::disk('public')->putFileAs($directory, $file, $filename);
        if (! is_string($storedRelativePath) || $storedRelativePath === '') {
            throw new \RuntimeException('保存图片失败');
        }

        if (! Storage::disk('public')->exists($storedRelativePath)) {
            throw new \RuntimeException('图片文件写入后未找到：storage/app/public/'.$storedRelativePath);
        }

        $targetPath = Storage::disk('public')->path($storedRelativePath);
        if (! is_file($targetPath)) {
            throw new \RuntimeException('图片文件路径不可访问：'.$targetPath);
        }

        $fileSize = filesize($targetPath);
        if ($fileSize === false) {
            throw new \RuntimeException('无法读取图片文件大小：'.$targetPath);
        }

        $imageInfo = @getimagesize($targetPath) ?: [0, 0, null, null, 'mime' => (string) $file->getMimeType()];

        return [
            'filename' => $filename,
            'file_name' => $filename,
            'original_name' => (string) $file->getClientOriginalName(),
            'file_path' => 'storage/'.$storedRelativePath,
            'file_size' => (int) $fileSize,
            'mime_type' => (string) ($imageInfo['mime'] ?? $file->getMimeType() ?? ''),
            'width' => (int) ($imageInfo[0] ?? 0),
            'height' => (int) ($imageInfo[1] ?? 0),
        ];
    }
}
