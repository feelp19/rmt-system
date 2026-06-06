<?php

namespace App\Services;

use DomainException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload hardening em 4 camadas (rmt-security):
 *  1. FormRequest valida `image|mimes|max` na borda (no controller).
 *  2. MIME real por magic bytes (finfo) — ignora o Content-Type do cliente.
 *  3. Reprocessa via GD → WebP: a saída é um arquivo novo byte a byte (anula polyglot).
 *  4. Disco privado + nome random + extensão controlada (.webp); servido por endpoint.
 */
class ImageUploadService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    private const DISK = 'local'; // privado (storage/app/private)

    public function storeImage(UploadedFile $file, string $dir, int $maxDim = 1280): string
    {
        $realPath = $file->getRealPath();

        // Camada 2: MIME real por magic bytes.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw new DomainException('Arquivo inválido: envie uma imagem JPEG, PNG ou WebP.');
        }

        // Camada 3: reprocessa (decodifica + redimensiona + recodifica em WebP).
        $source = @imagecreatefromstring((string) file_get_contents($realPath));
        if ($source === false) {
            throw new DomainException('Não foi possível ler a imagem.');
        }

        $image = $this->scaleDown($source, $maxDim);
        ob_start();
        $encoded = imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();

        if ($image !== $source) {
            imagedestroy($image);
        }
        imagedestroy($source);

        if ($encoded === false || $bytes === '') {
            throw new DomainException('Não foi possível converter a imagem.');
        }

        // Camada 4: nome random + extensão controlada + disco privado.
        $path = trim($dir, '/').'/'.Str::random(40).'.webp';
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function scaleDown(GdImage $img, int $maxDim): GdImage
    {
        $width = imagesx($img);
        $height = imagesy($img);

        if ($width <= $maxDim && $height <= $maxDim) {
            return $img;
        }

        $ratio = min($maxDim / $width, $maxDim / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $dst;
    }
}
