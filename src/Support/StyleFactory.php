<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Support;

use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

/**
 * OpenSpout 样式构建器.
 */
final class StyleFactory
{
    /**
     * 构建表头样式.
     */
    public static function header(array $config): Style
    {
        $header = (array) ($config['header'] ?? []);
        $style  = new Style();

        if ((bool) ($header['bold'] ?? true)) {
            $style->setFontBold();
        }

        $fontSize = (int) ($header['font_size'] ?? 11);

        if ($fontSize > 0) {
            $style->setFontSize($fontSize);
        }

        $fontName = trim((string) ($header['font_name'] ?? ''));

        if ($fontName !== '') {
            $style->setFontName($fontName);
        }

        $style->setFontColor(self::color((string) ($header['font_color'] ?? '000000')));
        $style->setBackgroundColor(self::color((string) ($header['background'] ?? 'F2F2F2')));
        $style->setCellAlignment(self::alignment((string) ($header['align'] ?? 'center')));
        $style->setCellVerticalAlignment(self::verticalAlignment((string) ($header['valign'] ?? 'center')));

        if ((bool) ($header['border'] ?? true)) {
            $style->setBorder(self::border((string) ($header['border_color'] ?? 'D9D9D9')));
        }

        return $style;
    }

    /**
     * 构建数据行基础样式.
     */
    public static function body(array $config): Style
    {
        $body  = (array) ($config['body'] ?? []);
        $style = new Style();

        $fontSize = (int) ($body['font_size'] ?? 0);

        if ($fontSize > 0) {
            $style->setFontSize($fontSize);
        }

        $fontName = trim((string) ($body['font_name'] ?? ''));

        if ($fontName !== '') {
            $style->setFontName($fontName);
        }

        $fontColor = trim((string) ($body['font_color'] ?? ''));

        if ($fontColor !== '') {
            $style->setFontColor(self::color($fontColor));
        }

        $style->setCellAlignment(self::alignment((string) ($body['align'] ?? 'left')));
        $style->setCellVerticalAlignment(self::verticalAlignment((string) ($body['valign'] ?? 'center')));

        if ((bool) ($body['wrap'] ?? false)) {
            $style->setShouldWrapText();
        }

        if ((bool) ($body['border'] ?? false)) {
            $style->setBorder(self::border((string) ($body['border_color'] ?? 'D9D9D9')));
        }

        return $style;
    }

    /**
     * 构建列覆盖样式，返回 null 表示该列无需覆盖数据行样式.
     */
    public static function column(Column $column): ?Style
    {
        $style   = new Style();
        $applied = false;

        if ($column->align !== null && CellAlignment::isValid($column->align)) {
            $style->setCellAlignment($column->align);
            $applied = true;
        }

        if ($column->wrap) {
            $style->setShouldWrapText();
            $applied = true;
        }

        if ($column->numberFormat !== null && $column->numberFormat !== '') {
            $style->setFormat($column->numberFormat);
            $applied = true;
        }

        return $applied ? $style : null;
    }

    /**
     * 构建四周边框.
     */
    private static function border(string $color): Border
    {
        $color = self::color($color);

        return new Border(
            new BorderPart(Border::LEFT, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, $color, Border::WIDTH_THIN, Border::STYLE_SOLID)
        );
    }

    /**
     * 规范化颜色为 6 位十六进制 RGB.
     */
    private static function color(string $color): string
    {
        $color = strtoupper(ltrim(trim($color), '#'));

        if (preg_match('/^[0-9A-F]{8}$/', $color) === 1) {
            $color = substr($color, 2);
        }

        return preg_match('/^[0-9A-F]{6}$/', $color) === 1 ? $color : Color::BLACK;
    }

    /**
     * 规范化水平对齐方式.
     */
    private static function alignment(string $align): string
    {
        $align = strtolower(trim($align));

        return CellAlignment::isValid($align) ? $align : CellAlignment::LEFT;
    }

    /**
     * 规范化垂直对齐方式.
     */
    private static function verticalAlignment(string $valign): string
    {
        $valign = strtolower(trim($valign));

        return CellVerticalAlignment::isValid($valign) ? $valign : CellVerticalAlignment::CENTER;
    }
}