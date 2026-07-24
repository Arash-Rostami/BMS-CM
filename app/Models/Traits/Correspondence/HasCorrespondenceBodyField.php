<?php

namespace App\Models\Traits\Correspondence;

trait HasCorrespondenceBodyField
{
    protected static function extraAttributes(): array
    {
        $isRtl = static::isRtl();
        $align = $isRtl ? 'text-right' : 'text-left';

        return [
            'style' => "min-height:500px; text-align:{$align}; font-size:1rem; line-height:1.6;",
            'dir' => $isRtl ? 'rtl' : 'ltr',
        ];
    }

    protected static function floatingToolbars(): array
    {
        return [
            'paragraph' => ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'textColor', 'highlight'],
            'heading' => ['h1', 'h2', 'h3'],
            'table' => [
                'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                'tableMergeCells', 'tableSplitCell', 'tableToggleHeaderRow', 'tableDelete',
            ],
        ];
    }

    protected static function mergeTags(): array
    {
        return ['name', 'company', 'today'];
    }

    protected static function placeHolder(): string
    {
        return static::isRtl() ? '⌨' : '𓂃🖊';
    }

    protected static function toolbarButtons(): array
    {
        return [
            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript'],
            ['highlight', 'link', 'blockquote'],
            ['h2', 'h3'],
            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
            ['bulletList', 'orderedList', 'table'],
            ['undo', 'redo'],
        ];
    }

    private static function isRtl(): bool
    {
        return in_array(app()->getLocale(), ['fa', 'ar', 'he']);
    }
}
