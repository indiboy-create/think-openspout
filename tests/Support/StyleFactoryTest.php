<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Support;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Youvision\ThinkOpenSpout\Support\Column;
use Youvision\ThinkOpenSpout\Support\StyleFactory;

class StyleFactoryTest extends TestCase
{
    #[Test]
    public function header_builds_default_style(): void
    {
        $style = StyleFactory::header([]);

        $this->assertTrue($style->isFontBold());
        $this->assertSame(11, $style->getFontSize());
        $this->assertSame('000000', $style->getFontColor());
        $this->assertSame('F2F2F2', $style->getBackgroundColor());
        $this->assertSame(CellAlignment::CENTER, $style->getCellAlignment());
        $this->assertSame(CellVerticalAlignment::CENTER, $style->getCellVerticalAlignment());
        $this->assertNotNull($style->getBorder());
    }

    #[Test]
    public function header_applies_custom_options_and_can_disable_bold_and_border(): void
    {
        $style = StyleFactory::header(['header' => [
            'bold'         => false,
            'font_size'    => 14,
            'font_name'    => 'Arial',
            'font_color'   => '#FF0000',
            'background'   => '00FF00',
            'align'        => 'right',
            'valign'       => 'top',
            'border'       => false,
            'border_color' => '000000',
        ]]);

        $this->assertFalse($style->isFontBold());
        $this->assertSame(14, $style->getFontSize());
        $this->assertSame('Arial', $style->getFontName());
        $this->assertSame('FF0000', $style->getFontColor());
        $this->assertSame('00FF00', $style->getBackgroundColor());
        $this->assertSame(CellAlignment::RIGHT, $style->getCellAlignment());
        $this->assertSame(CellVerticalAlignment::TOP, $style->getCellVerticalAlignment());
        $this->assertNull($style->getBorder());
    }

    #[Test]
    public function header_strips_alpha_channel_from_eight_digit_color(): void
    {
        $style = StyleFactory::header(['header' => ['background' => 'FF00FF00']]);

        $this->assertSame('00FF00', $style->getBackgroundColor());
    }

    #[Test]
    public function header_falls_back_to_black_for_invalid_color(): void
    {
        $style = StyleFactory::header(['header' => ['font_color' => 'not-a-color']]);

        $this->assertSame(Color::BLACK, $style->getFontColor());
    }

    #[Test]
    public function header_falls_back_to_left_for_invalid_alignment(): void
    {
        $style = StyleFactory::header(['header' => ['align' => 'middle']]);

        $this->assertSame(CellAlignment::LEFT, $style->getCellAlignment());
    }

    #[Test]
    public function header_falls_back_to_center_for_invalid_vertical_alignment(): void
    {
        $style = StyleFactory::header(['header' => ['valign' => 'middle']]);

        $this->assertSame(CellVerticalAlignment::CENTER, $style->getCellVerticalAlignment());
    }

    #[Test]
    public function header_ignores_zero_font_size_and_empty_font_name(): void
    {
        $style = StyleFactory::header(['header' => ['font_size' => 0, 'font_name' => '  ']]);

        $this->assertSame(11, $style->getFontSize());
    }

    #[Test]
    public function body_builds_default_style(): void
    {
        $style = StyleFactory::body([]);

        $this->assertFalse($style->isFontBold());
        $this->assertSame(CellAlignment::LEFT, $style->getCellAlignment());
        $this->assertSame(CellVerticalAlignment::CENTER, $style->getCellVerticalAlignment());
        $this->assertFalse($style->shouldWrapText());
        $this->assertNull($style->getBorder());
    }

    #[Test]
    public function body_applies_custom_options(): void
    {
        $style = StyleFactory::body(['body' => [
            'font_size'  => 12,
            'font_name'  => 'SimSun',
            'font_color' => 'FF0000',
            'align'      => 'right',
            'valign'     => 'bottom',
            'wrap'       => true,
            'border'     => true,
        ]]);

        $this->assertSame(12, $style->getFontSize());
        $this->assertSame('SimSun', $style->getFontName());
        $this->assertSame('FF0000', $style->getFontColor());
        $this->assertSame(CellAlignment::RIGHT, $style->getCellAlignment());
        $this->assertSame(CellVerticalAlignment::BOTTOM, $style->getCellVerticalAlignment());
        $this->assertTrue($style->shouldWrapText());
        $this->assertNotNull($style->getBorder());
    }

    #[Test]
    public function body_ignores_empty_font_color_and_zero_font_size(): void
    {
        $default = StyleFactory::body([]);
        $style   = StyleFactory::body(['body' => ['font_color' => '  ', 'font_size' => 0]]);

        $this->assertSame($default->getFontColor(), $style->getFontColor());
        $this->assertSame($default->getFontSize(), $style->getFontSize());
    }

    #[Test]
    public function column_returns_null_when_nothing_to_override(): void
    {
        $this->assertNull(StyleFactory::column(Column::make('id')));
    }

    #[Test]
    public function column_applies_alignment(): void
    {
        $style = StyleFactory::column(Column::make('id', ['align' => 'center']));

        $this->assertNotNull($style);
        $this->assertSame(CellAlignment::CENTER, $style->getCellAlignment());
    }

    #[Test]
    public function column_ignores_invalid_alignment_only(): void
    {
        $this->assertNull(StyleFactory::column(Column::make('id', ['align' => 'middle'])));
    }

    #[Test]
    public function column_applies_wrap(): void
    {
        $style = StyleFactory::column(Column::make('id', ['wrap' => true]));

        $this->assertNotNull($style);
        $this->assertTrue($style->shouldWrapText());
    }

    #[Test]
    public function column_applies_number_format(): void
    {
        $style = StyleFactory::column(Column::make('price', ['number_format' => '#,##0.00']));

        $this->assertNotNull($style);
        $this->assertSame('#,##0.00', $style->getFormat());
    }

    #[Test]
    public function column_ignores_empty_number_format(): void
    {
        $this->assertNull(StyleFactory::column(Column::make('price', ['number_format' => ''])));
    }

    #[Test]
    public function column_combines_multiple_overrides(): void
    {
        $style = StyleFactory::column(Column::make('price', [
            'align'         => 'right',
            'wrap'          => true,
            'number_format' => '0.00',
        ]));

        $this->assertNotNull($style);
        $this->assertSame(CellAlignment::RIGHT, $style->getCellAlignment());
        $this->assertTrue($style->shouldWrapText());
        $this->assertSame('0.00', $style->getFormat());
    }
}