<?php

namespace App\Livewire\LandingPage;

use Livewire\Component;

class Search extends Component
{
    public const ICONS = [
        'shopping-cart', 'document-text', 'document-check', 'building-office',
        'shopping-bag', 'banknotes', 'truck', 'clipboard-document-check',
        'building-office-2', 'building-library', 'currency-dollar', 'cube',
        'tag', 'check-badge', 'user-circle',
    ];

    public const TEXT_COLORS = [
        'blue' => 'text-blue-600',
        'green' => 'text-green-600',
        'yellow' => 'text-yellow-700',
        'red' => 'text-red-600',
        'slate' => 'text-slate-500',
    ];

    public const ENTRY_COLORS = [
        'blue' => 'text-blue-600 dark:text-blue-400',
        'green' => 'text-green-600 dark:text-green-400',
        'yellow' => 'text-yellow-700 dark:text-yellow-400',
        'red' => 'text-red-600 dark:text-red-400',
        'slate' => 'text-slate-500 dark:text-slate-400',
    ];

    public const PROGRESS_COLORS = [
        'blue' => 'text-blue-500',
        'green' => 'text-green-500',
        'yellow' => 'text-yellow-500',
        'red' => 'text-red-500',
        'slate' => 'text-slate-500',
    ];

    public const BADGE_COLORS = [
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400',
    ];

    public const STAGE_STATE_CLASSES = [
        'completed' => 'bg-green-50 border-green-200 text-green-700 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400',
        'partial' => 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/20 dark:text-yellow-400',
        'missing' => 'bg-red-50 border-red-200 text-red-700 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400',
        'upcoming' => 'bg-white border-slate-200 text-slate-500 dark:bg-white/5 dark:border-white/10 dark:text-slate-400',
    ];

    public const STAGE_LINE_CLASSES = [
        'completed' => 'bg-green-400 dark:bg-green-500/60',
        'partial' => 'bg-yellow-400 dark:bg-yellow-500/60',
        'missing' => 'bg-red-400 dark:bg-red-500/60',
        'upcoming' => 'bg-slate-200 dark:bg-slate-700',
    ];

    public bool $isRtl = false;

    public function mount(bool $isRtl): void
    {
        $this->isRtl = $isRtl;
    }

    public function render()
    {
        return view('livewire.landing-page.search', [
            'icons' => self::ICONS,
            'textColors' => self::TEXT_COLORS,
            'entryColors' => self::ENTRY_COLORS,
            'progressColors' => self::PROGRESS_COLORS,
            'badgeColors' => self::BADGE_COLORS,
            'stageStateClasses' => self::STAGE_STATE_CLASSES,
            'stageLineClasses' => self::STAGE_LINE_CLASSES,
        ]);
    }
}
