<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Support;

use OpenSpout\Common\Entity\Cell;

/**
 * 单元格值与用于列宽计算的显示文本.
 */
final class CellValue
{
    public function __construct(
        public readonly Cell $cell,
        public readonly string $text
    ) {
    }
}