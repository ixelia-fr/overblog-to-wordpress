<?php

namespace App\Tests\Transformer;

use App\Transformer\FontFamilyRemover;
use PHPUnit\Framework\TestCase;

class FontFamilyRemoverTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testTransformer($content, $expected): void
    {
        $transformer = new FontFamilyRemover();

        $this->assertEquals(
            $expected,
            $transformer->transform($content)
        );
    }

    public function provider()
    {
        return [
            [
                '<h2 style="text-align: center">Title</h2>',
                '<h2>Title</h2>'
            ],
            [
                '<h2 style="padding:20px; text-align: center; margin: 20px;">Title</h2>',
                '<h2 style="padding:20px; margin: 20px;">Title</h2>'
            ],
        ];
    }
}
