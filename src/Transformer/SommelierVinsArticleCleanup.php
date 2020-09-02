<?php

namespace App\Transformer;

class SommelierVinsArticleCleanup implements TransformerInterface
{
    public function transform(string $content): string
    {
        $texts = [
            'Des guides pratiques PDF pour affuter vos connaissances',
            'Des magazines PDF pour affuter vos connaissances',
            'Cela fait 25 ans que je suis dans le vin, sommelier et formateur',
            'offrez-vous mes nouveaux magazines pédagogiques en PDF sur ces sujets passionnants',
            'Avec mes magazines pédagogiques en PDF',
        ];

        foreach ($texts as $text) {
            $textPos = strpos($content, $text);

            if ($textPos !== false) {
                $tempContent = substr($content, 0, $textPos);
                $openingDivPos = strrpos($tempContent, '<div');

                $closingDivPos = strpos($content, '</div>', $textPos);

                if ($openingDivPos !== false && $closingDivPos !== false) {
                    $newContent = substr($content, 0, $openingDivPos);
                    $newContent .= substr($content, $closingDivPos + strlen('</div>'));

                    $content = $newContent;
                }
            }
        }

        return $content;
    }
}
