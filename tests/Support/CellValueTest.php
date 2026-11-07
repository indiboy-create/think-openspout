<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Support;

use OpenSpout\Common\Entity\Cell\StringCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Youvision\ThinkOpenSpout\Support\CellValue;

class CellValueTest extends TestCase
{
    #[Test]
    public function exposes_cell_and_display_text(): void
    {
        $cell  = new StringCell('内容', null);
        $value = new CellValue($cell, '内容');

        $this->assertSame($cell, $value->cell);
        $this->assertSame('内容', $value->text);
        $this->assertSame('内容', $value->cell->getValue());
    }

    #[Test]
    public function keeps_text_independent_from_cell_value(): void
    {
        $value = new CellValue(new StringCell('1.00', null), '1.00');

        $this->assertSame('1.00', $value->text);
        $this->assertSame('1.00', $value->cell->getValue());
    }
}