<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateOgImage extends Command
{
    protected $signature = 'seo:og-image
        {--output=public/og-image.png : Chemin de sortie}
        {--title=Logiciel de facturation pour PME : Titre principal}
        {--subtitle=Devis · Factures · Relances WhatsApp · Cabinet comptable : Sous-titre}
        {--domain=fayeku.sn : Domaine}';

    protected $description = 'Génère public/og-image.png (1200x630) pour les partages sociaux';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Extension GD non disponible.');

            return self::FAILURE;
        }

        $width = 1200;
        $height = 630;
        $output = base_path($this->option('output'));
        $title = (string) $this->option('title');
        $subtitle = (string) $this->option('subtitle');
        $domain = (string) $this->option('domain');

        $image = imagecreatetruecolor($width, $height);

        $bgTop = $this->hexToRgb('#0F2F2C');
        $bgBottom = $this->hexToRgb('#0A1F1D');
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) ($bgTop[0] + ($bgBottom[0] - $bgTop[0]) * $ratio),
                (int) ($bgTop[1] + ($bgBottom[1] - $bgTop[1]) * $ratio),
                (int) ($bgTop[2] + ($bgBottom[2] - $bgTop[2]) * $ratio),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        $accent = imagecolorallocatealpha($image, 7, 200, 162, 90);
        imagefilledellipse($image, $width - 80, 80, 400, 400, $accent);

        $white = imagecolorallocate($image, 255, 255, 255);
        $mint = imagecolorallocate($image, 124, 247, 212);
        $muted = imagecolorallocate($image, 180, 210, 205);

        $logoPath = public_path('logo.png');
        if (is_file($logoPath)) {
            $logo = imagecreatefrompng($logoPath);
            if ($logo) {
                imagealphablending($logo, true);
                imagesavealpha($logo, true);
                $logoTargetH = 90;
                $logoW = imagesx($logo);
                $logoH = imagesy($logo);
                $logoTargetW = (int) ($logoW * ($logoTargetH / $logoH));
                $resized = imagecreatetruecolor($logoTargetW, $logoTargetH);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
                imagecopyresampled($resized, $logo, 0, 0, 0, 0, $logoTargetW, $logoTargetH, $logoW, $logoH);
                imagecopy($image, $resized, 80, 70, 0, 0, $logoTargetW, $logoTargetH);
                imagedestroy($resized);
                imagedestroy($logo);
            }
        }

        $font = resource_path('fonts/og.ttf');
        if (! is_file($font)) {
            $candidates = [
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/System/Library/Fonts/Helvetica.ttc',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
            ];
            $font = null;
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $font = $candidate;
                    break;
                }
            }
        }

        if ($font) {
            $this->wrapText($image, $title, $font, 64, $white, 80, 280, $width - 160, 78);
            imagettftext($image, 28, 0, 80, 460, $muted, $font, $subtitle);
            imagettftext($image, 22, 0, 80, $height - 60, $mint, $font, 'fayeku.sn  ·  Sénégal');
        } else {
            imagestring($image, 5, 80, 280, $title, $white);
            imagestring($image, 4, 80, 320, $subtitle, $muted);
        }

        @mkdir(dirname($output), 0755, true);
        imagepng($image, $output, 6);
        imagedestroy($image);

        $this->info("OG image générée : {$output} (".filesize($output).' octets)');

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function wrapText(\GdImage $image, string $text, string $font, int $size, int $color, int $x, int $y, int $maxWidth, int $lineHeight): void
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate);
            $w = $box[2] - $box[0];
            if ($w > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        foreach ($lines as $i => $line) {
            imagettftext($image, $size, 0, $x, $y + $i * $lineHeight, $color, $font, $line);
        }
    }
}
