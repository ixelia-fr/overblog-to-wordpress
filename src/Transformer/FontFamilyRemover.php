<?php

namespace App\Transformer;

class FontFamilyRemover implements TransformerInterface
{
    public function transform(string $content): string
    {
        $content = $this->removeFontFamily($content);
        $content = $this->removeFontSizes($content);
        $content = $this->removeLineHeight($content);
        $content = $this->removeAlignForHeadings($content);
        $content = $this->removeSomeColors($content);
        $content = $this->removeEmptyStyle($content);

        return $content;
    }

    protected function removeFontFamily(string $content): string
    {
        return preg_replace('|font-family:[^";]*|', '', $content);
    }

    protected function removeAlignForHeadings(string $content): string
    {
        return preg_replace('|(<h\d style="[^"]*?) ?text-align:[^";]+;?|', '$1', $content);
    }

    protected function removeFontSizes(string $content): string
    {
        return preg_replace('|font-size:[^";]*|', '', $content);
    }

    protected function removeLineHeight(string $content): string
    {
        return preg_replace('|line-height:[^";]*|', '', $content);
    }

    protected function removeSomeColors(string $content): string
    {
        $content = preg_replace('|color: *#000000 *;?|', '', $content);
        $content = preg_replace('|color: *null *;?|', '', $content);

        return $content;
    }

    protected function removeEmptyStyle(string $content): string
    {
        $content = preg_replace('| style="[ ;]*"|', '', $content);
        $content = preg_replace('|<span >|', '<span>', $content);

        return $content;
    }
}
