<?php

namespace App\Tests\Transformer;

use App\Transformer\SommelierVinsArticleCleanup;
use PHPUnit\Framework\TestCase;

class SommelierVinsArticleCleanupTest extends TestCase
{
    public function testTransformer(): void
    {
        $content = '
<div>
    hey
</div>
A
<div>
    ABC
    Des magazines PDF pour affuter vos connaissances
    DEF
</div>
B
<div>
    hou
</div>';

        $expected = '
<div>
    hey
</div>
A

B
<div>
    hou
</div>';

        $transformer = new SommelierVinsArticleCleanup();

        $this->assertEquals(
            $expected,
            $transformer->transform($content)
        );
    }
}
