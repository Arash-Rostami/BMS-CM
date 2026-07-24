<?php

$base = __DIR__.'/../../';

$contract = <<<'CONTRACT'
=== INTERFACE CONTRACT LOCK (both workers MUST adhere exactly) ===
Alpine factory: `workflow` — default export from resources/js/workflow-alpine.js, signature `workflow(groups)` returning a plain object (no class/new/Alpine.store).
Root blade attribute: x-data="workflow(@js($insightGroups))".
LOCKED state props: groups, tips (flattened array of {tip,accent,title,key,route,group}), rotateIdx, playing, audio, selected, textOpen, videoOpen, paused, rotateInterval.
LOCKED getter: get currentTip() => this.tips[this.rotateIdx] || {}.
LOCKED methods: init(), destroy(), flattenTips(), startRotate(), nextTip(), setTip(i), togglePause(), openText(group), openVideo(group), playPause(group), stopAudio(), hasContent(group), accentBg(accent), accentText(accent).
Event bus: dispatch window.dispatchEvent(new CustomEvent('lp-audio-play',{detail:{source:'workflow'}})) immediately before .play(); init() listener pauses self when e.detail.source!=='workflow' && this.playing. (triWidget side unchanged — it listens source!=='widget'.)
Audio: lazy new Audio(group.audio) on first playPause; onended -> playing=false; fresh Audio per play (short narration, no reuse).
Rotate: setInterval 7000ms advancing rotateIdx, skip when !tips.length || textOpen || videoOpen || playing || paused. prefers-reduced-motion: if window.matchMedia('(prefers-reduced-motion: reduce)').matches, do NOT start the interval.
openText(group): this.selected=group; this.textOpen=true. openVideo(group): this.stopAudio(); this.selected=group; this.videoOpen=true. playPause(group): if playing && this.selected?.key===group.key stopAudio+return; else stopAudio; dispatch event; this.audio=new Audio(group.audio); this.selected=group; this.audio.play().then(()=>this.playing=true).catch(()=>{}); this.audio.onended=()=>this.playing=false.
hasContent(group): !!(group.terms?.length||group.process?.length||group.dos?.length||group.donts?.length).
accentMap (mirror blade EXACTLY): blue => {border:'border-blue-200 dark:border-blue-500/30', text:'text-blue-700 dark:text-blue-400', bg:'bg-blue-50 dark:bg-blue-500/10'}; green/yellow/red same pattern (green->text-green-700 dark:text-green-400 / bg-green-50 dark:bg-green-500/10; yellow->text-yellow-800 dark:text-yellow-400 / bg-yellow-50 dark:bg-yellow-500/10; red->text-red-700 dark:text-red-400 / bg-red-50 dark:bg-red-500/10). accentBg(accent) returns map[accent]?.bg||''; accentText(accent) returns map[accent]?.text||''.
Group data shape (from PHP @js): {key,title,scopeLabel,accent,tips[],terms[],process[],dos[],donts[],poster,audio,video,route}.
=== DESIGN CONSTRAINTS (MiniMax sheet, real classes only) ===
FORBIDDEN: .glass .card-3d .shimmer-effect .floating .glow-orb .badge-float .workflow-connector .thread-path .workflow-node .tri-widget-panel .btn-wrapper .btn-gradient .icon-container .btn-inline .pulse-ring .shadow-elegant; any invented lp-flex/lp-btn/lp-text/lp-badge/lp-icon (DO NOT EXIST); new CSS keyframes; indigo/cyan on landing (loader+.range only).
REAL landing primitives: .lp-surface .lp-surface-hover .lp-bar .lp-divider .lp-tab .lp-tab-active .truncate-2 .stepper-connector .custom-scrollbar + plain Tailwind utilities (flex,gap-2,p-5,rounded-md,border,etc.) + Tailwind color families blue/green/yellow/red/slate.
Stage accent inline map (use verbatim): blue=>['border'=>'border-blue-200 dark:border-blue-500/30','text'=>'text-blue-700 dark:text-blue-400','bg'=>'bg-blue-50 dark:bg-blue-500/10']; green/yellow/red same pattern.
Modals: REUSE the existing .fi-modal/.fi-modal-window/.fi-modal-close-overlay/.fi-modal-header/.fi-modal-content classes verbatim — do NOT invent lp-* modal classes.
Motion: Alpine x-transition or CSS transition 150-200ms var(--md-motion) only.
CONTRACT;

$files = [
  'wf'   => 'resources/views/components/filament/landing-page/workflow.blade.php',
  'ins'  => 'resources/views/components/filament/landing-page/insights.blade.php',
  'head' => 'resources/views/components/filament/landing-page/header.blade.php',
  'root' => 'resources/views/components/filament/landing-page.blade.php',
  'alp'  => 'resources/js/insights-alpine.js',
  'load' => 'resources/js/alpine-loader.js',
  'lpen' => 'lang/en/dashboard/strings.php',
  'lpfr' => 'lang/fr/dashboard/strings.php',
  'lpfa' => 'lang/fa/dashboard/strings.php',
];
$ctx = [];
foreach ($files as $k => $rel) {
  $path = $base.$rel;
  if (! is_readable($path)) { fwrite(STDERR, "MISSING: $rel\n"); exit(1); }
  $ctx[$k] = file_get_contents($path);
}

$aSys = 'You are Kimi-K2.7-Code, a parallel code-gen worker. Produce clean, minimal, comment-free code adhering exactly to the injected interface contract and design constraints. Output only the requested files.';
$aSkeleton = <<<'ASKEL'
You are Kimi-K2.7-Code Worker A in a multi-agent pipeline. Write ONLY the Blade/PHP files specified, using the project's REAL classes (the injected contract lists them). Do not invent tokens. Do not add code comments. Output each file as a fenced block prefixed with `### FILE: <path>` then the full file content. Do not explain.

TASK: Produce 3 file outputs + 1 deletion note.

1) REWRITE resources/views/components/filament/landing-page/workflow.blade.php — merge the Insights tab content INTO this workflow tab. Keep the existing 4-stage horizontal stepper structure (number badge, title, description, 2 link rows + count pills, connector chevrons). ADD to each stage card: a compact media affordance row pinned to the bottom (mt-auto pt-3 border-t lp-divider flex items-center gap-1.5) with up to 3 small icon buttons using EXACTLY class="lp-tab flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border lp-divider", each x-show-gated: Listen (heroicon-o-speaker-wave; @click="playPause(@js($insight))"; x-show="insight.audio") / Watch (heroicon-o-film; @click="openVideo(@js($insight))"; x-show="insight.video") / Read (heroicon-o-book-open; @click="openText(@js($insight))"; x-show="hasContent(insight)"). Build $insightByGroup=collect($insightGroups??[])->keyBy('key') and add 'group'=>'request_approval'|'order_processing'|'procurement_payment'|'logistics' to each $step; $insight = $insightByGroup[$step['group']] ?? null; guard the whole media row with @if($insight) and pass @js($insight) (null-safe — if no insight, render card without media row, no Log noise). Wrap the ENTIRE workflow block (stepper + modals + ticker) in a single root <div x-data="workflow(@js($insightGroups ?? []))">. MOVE the text-modal and video-modal markup from insights.blade.php into this file VERBATIM (reuse .fi-modal/.fi-modal-window/.fi-modal-close-overlay/.fi-modal-header/.fi-modal-content classes, the terms/process/dos/donts <details> blocks, the close button x-heroicon-o-x-mark, escape keydown) — they bind to `selected`, `textOpen`, `videoOpen` from the workflow factory. ADD the auto-rotate ticker at the BOTTOM (after the stepper div, before the closing of the root): a div.lp-surface.flex.items-center.gap-3.px-4.py-2.5.mt-8.mb-2 with x-show="tips.length>=2", @mouseenter="paused=true" @mouseleave="paused=false", role="region" aria-label; inside, template x-for="(t,i) in tips" :key="i" containing a button x-show="i===rotateIdx" with x-transition opacity 200ms enter/leave, @click="openText(t.group)", containing: accent dot span.w-2.h-2.rounded-full.shrink-0 :class="accentBg(t.accent)", span.text-xs.font-semibold.shrink-0 :class="accentText(t.accent)" x-text="t.title", span.text-xs.text-slate-500.dark:text-slate-400.truncate-2.flex-1.min-w-0 x-text="t.tip". Then a dot pager div.flex.items-center.gap-1.shrink-0.ms-2 with template x-for over tips: button @click="setTip(i)" :class="i===rotateIdx ? 'w-4 h-1.5 rounded-full '+accentBg(t.accent) : 'w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600'". The factory provides accentBg(accent) and accentText(accent). Use {{ __('dashboard/strings.insights_listen') }} etc. for button labels (existing keys: insights_listen, insights_pause, insights_watch, insights_open_module, insights_next_tip; NEW key workflow_read for Read — use __('dashboard/strings.workflow_read')). Use $isRtl where needed (keep existing chevron logic).
2) EDIT resources/views/components/filament/landing-page/header.blade.php — remove ONLY the @if(!empty($insightGroups)) ... @endif Insights tab button block. Output the full edited file.
3) EDIT resources/views/components/filament/landing-page.blade.php — remove ONLY the <div x-show="activeTab === 'insights'" ...> ... </div> panel block. Output the full edited file.
4) NOTE: resources/views/components/filament/landing-page/insights.blade.php is DELETED (do not output it; just state DELETED).

=== CURRENT workflow.blade.php (reference, rewrite fully) ===
ASKEL;
$aPrompt = $aSkeleton . $ctx['wf'] . "\n\n=== CURRENT insights.blade.php (source for modal markup to MOVE verbatim) ===\n" . $ctx['ins'] . "\n\n=== CURRENT header.blade.php (edit: remove Insights tab) ===\n" . $ctx['head'] . "\n\n=== CURRENT landing-page.blade.php (edit: remove insights panel) ===\n" . $ctx['root'] . "\n\n" . $contract;

$bSys = 'You are Kimi-K2.7-Code, a parallel code-gen worker. Produce clean, minimal, comment-free code adhering exactly to the injected interface contract. Output only the requested files.';
$bSkeleton = <<<'BSKEL'
You are Kimi-K2.7-Code Worker B in a multi-agent pipeline. Write ONLY the files specified, using the project's REAL classes/tokens. Do not invent tokens. Do not add code comments (zero-noise). Output each file as a fenced block prefixed with `### FILE: <path>`.

TASK: Produce these file outputs.

1) CREATE resources/js/workflow-alpine.js — the `workflow(groups)` Alpine factory per the LOCKED interface contract. Transform the existing insights-alpine.js: rename factory to workflow, rename event source 'insights'->'workflow', add flattenTips() building tips=[{tip,accent,title,key,route,group}], startRotate()/destroy(), nextTip()/setTip(i), togglePause(), currentTip getter, accentBg(accent)/accentText(accent) helpers returning Tailwind bg/text classes from an accentMap {blue,green,yellow,red} (mirror the blade accent map EXACTLY per the contract), hasContent(group). Keep the lp-audio-play mutual-exclusion (dispatch before .play(), listener pauses self when source!=='workflow'). Lazy Audio, fresh per playPause, onended->playing=false. prefers-reduced-motion guard in init (skip startRotate). Default export function workflow(groups){return{...}}.
2) EDIT resources/js/alpine-loader.js — replace `import insights from './insights-alpine.js'` with `import workflow from './workflow-alpine.js'`; replace the `if (document.querySelector('[x-data^="insights("]')) Alpine.data('insights', insights);` line with `if (document.querySelector('[x-data^="workflow("]')) Alpine.data('workflow', workflow);`. Keep everything else identical. Output the full file.
3) EDIT resources/css/landing-page.css — APPEND a `.lp-ticker` rule ONLY (do not touch existing rules; do NOT add keyframes). Use existing --custom-*/--lp tokens. Keep minimal. Output ONLY the appended block as `### APPEND TO: resources/css/landing-page.css`.
4) `### EDIT: lang/fr/dashboard/strings.php` — add missing keys 'insights_watch'=>'Regarder' and 'insights_open_module'=>'Ouvrir dans le module' next to existing insights_* keys, AND add 'workflow_read'=>'Lire'. Show only the added lines with surrounding context. Also `### EDIT: lang/en/dashboard/strings.php` adding 'workflow_read'=>'Read', and `### EDIT: lang/fa/dashboard/strings.php` adding 'workflow_read'=>'مطالعه'.

=== CURRENT insights-alpine.js (transform into workflow-alpine.js) ===
BSKEL;
$bPrompt = $bSkeleton . $ctx['alp'] . "\n\n=== CURRENT alpine-loader.js (edit) ===\n" . $ctx['load'] . "\n\n=== lang/en/dashboard/strings.php (add workflow_read) ===\n" . $ctx['lpen'] . "\n\n=== lang/fr/dashboard/strings.php (add insights_watch, insights_open_module, workflow_read) ===\n" . $ctx['lpfr'] . "\n\n=== lang/fa/dashboard/strings.php (add workflow_read) ===\n" . $ctx['lpfa'] . "\n\n" . $contract;

$aPayload = ['model'=>'kimi-k2.7-code:cloud','stream'=>false,'options'=>['temperature'=>0.15,'num_predict'=>8000],
  'messages'=>[['role'=>'system','content'=>$aSys],['role'=>'user','content'=>$aPrompt]]];
$bPayload = ['model'=>'kimi-k2.7-code:cloud','stream'=>false,'options'=>['temperature'=>0.15,'num_predict'=>8000],
  'messages'=>[['role'=>'system','content'=>$bSys],['role'=>'user','content'=>$bPrompt]]];

$aJson = json_encode($aPayload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$bJson = json_encode($bPayload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__.'/pipe3_kimiA_payload.json', $aJson);
file_put_contents(__DIR__.'/pipe3_kimiB_payload.json', $bJson);
fwrite(STDERR, "payloads built A=".strlen($aJson)." B=".strlen($bJson)."\n");