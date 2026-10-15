<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCandidatePhoto
{
    public function store(UploadedFile $file): string
    {
        $image = imagecreatefromstring($file->getContent());
        if ($image === false) {
            throw ValidationException::withMessages(['candidates' => 'Não foi possível processar a foto.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 800 / max($width, $height));
        $resized = imagescale($image, max(1, (int) ($width * $scale)), max(1, (int) ($height * $scale)));
        if ($resized === false) {
            throw ValidationException::withMessages(['candidates' => 'Não foi possível processar a foto.']);
        }
        ob_start();
        imagewebp($resized, null, 85);
        $encoded = ob_get_clean();
        if ($encoded === false) {
            throw ValidationException::withMessages(['candidates' => 'Não foi possível processar a foto.']);
        }
        $path = 'candidates/'.Str::uuid().'.webp';
        if (! Storage::disk('public')->put($path, $encoded)) {
            throw ValidationException::withMessages(['candidates' => 'Falha ao salvar a foto. Tente novamente.']);
        }

        return $path;
    }
}
