<?php

namespace App\Support\Widgets;

use Illuminate\Support\Str;

class WidgetSnippets
{
    /**
     * Third-party share endpoints, with placeholders for the URL and title.
     *
     * @var array<string, array{label: string, mark: string, color: string, url: string}>
     */
    private const NETWORKS = [
        'x' => ['label' => 'X', 'mark' => 'X', 'color' => '#0f172a', 'url' => 'https://twitter.com/intent/tweet?url={u}&text={t}'],
        'facebook' => ['label' => 'Facebook', 'mark' => 'f', 'color' => '#1877f2', 'url' => 'https://www.facebook.com/sharer/sharer.php?u={u}'],
        'linkedin' => ['label' => 'LinkedIn', 'mark' => 'in', 'color' => '#0a66c2', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url={u}'],
        'whatsapp' => ['label' => 'WhatsApp', 'mark' => 'W', 'color' => '#25d366', 'url' => 'https://wa.me/?text={t}%20{u}'],
        'telegram' => ['label' => 'Telegram', 'mark' => 'T', 'color' => '#229ed9', 'url' => 'https://t.me/share/url?url={u}&text={t}'],
        'reddit' => ['label' => 'Reddit', 'mark' => 'r', 'color' => '#ff4500', 'url' => 'https://www.reddit.com/submit?url={u}&title={t}'],
        'email' => ['label' => 'Email', 'mark' => '@', 'color' => '#475569', 'url' => 'mailto:?subject={t}&body={u}'],
    ];

    /**
     * The embed code for one widget.
     *
     * @param  array<string, mixed>  $config
     */
    public static function render(string $type, array $config, bool $credit = false): string
    {
        $snippet = match ($type) {
            'social-share' => self::socialShare($config),
            'adblock-detector' => self::adblockDetector($config),
            'cookie-banner' => self::cookieBanner($config),
            'whatsapp-button' => self::whatsappButton($config),
            'announcement-bar' => self::announcementBar($config),
            'countdown' => self::countdown($config),
            'back-to-top' => self::backToTop($config),
            'scroll-progress' => self::scrollProgress($config),
            default => '',
        };

        if ($snippet === '' || ! $credit) {
            return $snippet;
        }

        return $snippet.self::credit();
    }

    /**
     * A small footer credit added to widgets built on the free plan.
     */
    private static function credit(): string
    {
        $brand = self::html((string) config('app.name'));
        $url = self::html(route('home'));

        return '<div class="onw-credit" style="margin-top:10px;text-align:center;font-family:inherit;font-size:10px;line-height:1.4;color:inherit;opacity:.6">'
            .'<a href="'.$url.'" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none">Made with '.$brand.'</a></div>';
    }

    /**
     * The one line embed code visitors paste into their website.
     */
    public static function embedTag(string $url): string
    {
        return '<script src="'.self::html($url).'" async></script>';
    }

    /**
     * The hosted script that renders a widget when the embed tag loads it.
     *
     * @param  array<string, mixed>  $config
     */
    public static function embedSource(string $type, array $config, bool $credit = false, string $key = ''): string
    {
        $markup = (string) json_encode(
            self::render($type, $config, $credit),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES,
        );

        $behaviour = (string) json_encode(
            self::behaviour($config),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES,
        );

        $memoryKey = 'onw-seen-'.substr(md5($key !== '' ? $key : $type.$behaviour), 0, 10);

        $template = <<<'JS'
(function () {
    'use strict';
    var behaviour = __BEHAVIOUR__;
    var memoryKey = __MEMORY_KEY__;
    var self = document.currentScript;

    function parseTime(value) {
        if (!value) { return null; }
        var parsed = new Date(String(value).replace(' ', 'T'));
        return isNaN(parsed.getTime()) ? null : parsed.getTime();
    }

    function inSchedule() {
        var now = Date.now();
        var from = parseTime(behaviour.from);
        var until = parseTime(behaviour.until);
        if (from !== null && now < from) { return false; }
        if (until !== null && now > until) { return false; }
        return true;
    }

    function onDevice() {
        if (behaviour.device === 'all') { return true; }
        var narrow;
        try {
            narrow = window.matchMedia('(max-width: 767px)').matches;
        } catch (error) {
            narrow = (window.innerWidth || 0) < 768;
        }
        return behaviour.device === 'mobile' ? narrow : !narrow;
    }

    function matches(pattern, page) {
        var parts = pattern.split('*');
        var index = 0;
        for (var i = 0; i < parts.length; i++) {
            if (!parts[i]) { continue; }
            var found = page.indexOf(parts[i], index);
            if (found === -1) { return false; }
            index = found + parts[i].length;
        }
        return true;
    }

    function onPage() {
        if (behaviour.pages === 'everywhere' || !behaviour.paths.length) { return true; }
        var page = (window.location.pathname + window.location.search).toLowerCase();
        var listed = false;
        for (var i = 0; i < behaviour.paths.length; i++) {
            if (matches(String(behaviour.paths[i]).toLowerCase(), page)) { listed = true; break; }
        }
        return behaviour.pages === 'include' ? listed : !listed;
    }

    function storage() {
        try {
            return behaviour.frequency === 'session' ? window.sessionStorage : window.localStorage;
        } catch (error) {
            return null;
        }
    }

    function seenBefore() {
        var store = storage();
        if (behaviour.frequency === 'always' || !store) { return false; }
        try {
            var raw = store.getItem(memoryKey);
            if (!raw) { return false; }
            if (behaviour.frequency !== 'day') { return true; }
            var at = Number(raw);
            return isFinite(at) && (Date.now() - at) < 86400000;
        } catch (error) {
            return false;
        }
    }

    function remember() {
        var store = storage();
        if (behaviour.frequency === 'always' || !store) { return; }
        try {
            store.setItem(memoryKey, behaviour.frequency === 'day' ? String(Date.now()) : '1');
        } catch (error) {}
    }

    function reducedMotion() {
        try {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (error) {
            return false;
        }
    }

    if (behaviour.paused || !onDevice() || !onPage() || !inSchedule() || seenBefore()) {
        return;
    }

    window.setTimeout(show, behaviour.delay);

    function show() {
        var host = document.createElement('div');
        host.className = 'onw-embed';
        var animate = behaviour.animation !== 'none' && !reducedMotion();
        if (animate) {
            host.style.opacity = '0';
            host.style.transition = 'opacity .35s ease, transform .35s ease';
            if (behaviour.animation === 'slide') { host.style.transform = 'translateY(14px)'; }
            if (behaviour.animation === 'scale') { host.style.transform = 'scale(.96)'; }
        }
        if (self && self.parentNode) {
            self.parentNode.insertBefore(host, self.nextSibling);
        } else {
            (document.body || document.documentElement).appendChild(host);
        }
        host.innerHTML = __MARKUP__;
        var scripts = host.querySelectorAll('script');
        for (var i = 0; i < scripts.length; i++) {
            var node = scripts[i];
            var code = node.textContent || '';
            node.parentNode.removeChild(node);
            if (!code) { continue; }
            try { (new Function(code))(); } catch (error) {}
        }
        if (animate) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    host.style.opacity = '1';
                    host.style.transform = 'none';
                });
            });
            window.setTimeout(function () {
                host.style.transition = '';
                host.style.opacity = '';
                host.style.transform = '';
            }, 520);
        }
        remember();
    }
})();
JS;

        return strtr($template, [
            '__MARKUP__' => $markup,
            '__BEHAVIOUR__' => $behaviour,
            '__MEMORY_KEY__' => self::js($memoryKey),
        ]);
    }

    /**
     * The delivery rules checked in the visitor's browser before a widget
     * renders. The preview ignores them so every state stays editable.
     *
     * @param  array<string, mixed>  $config
     * @return array{paused: bool, device: string, pages: string, paths: list<string>, delay: int, frequency: string, from: string, until: string, animation: string}
     */
    public static function behaviour(array $config): array
    {
        $device = self::text($config, 'behaviour_device', 'all');
        $pages = self::text($config, 'behaviour_pages', 'everywhere');
        $frequency = self::text($config, 'behaviour_frequency', 'always');
        $animation = self::text($config, 'behaviour_animation', 'none');

        $paths = array_values(array_filter(array_map(
            static fn (string $path): string => Str::limit(trim($path), 120, ''),
            explode(',', self::text($config, 'behaviour_paths')),
        ), static fn (string $path): bool => $path !== ''));

        return [
            'paused' => self::flag($config, 'behaviour_paused'),
            'device' => in_array($device, ['all', 'desktop', 'mobile'], true) ? $device : 'all',
            'pages' => in_array($pages, ['everywhere', 'include', 'exclude'], true) ? $pages : 'everywhere',
            'paths' => array_slice($paths, 0, 12),
            'delay' => self::number($config, 'behaviour_delay', 0, 0, 15000),
            'frequency' => in_array($frequency, ['always', 'session', 'day', 'visitor'], true) ? $frequency : 'always',
            'from' => self::text($config, 'behaviour_from'),
            'until' => self::text($config, 'behaviour_until'),
            'animation' => in_array($animation, ['none', 'fade', 'slide', 'scale'], true) ? $animation : 'none',
        ];
    }

    /**
     * A sample page that shows the widget in context, used by the iframe
     * preview. Widgets that live in the page flow are placed inside the
     * article, overlays are appended to the document, and widgets that only
     * react to scrolling are scrolled into their active state.
     *
     * @param  array<string, mixed>  $config
     */
    public static function preview(string $type, array $config, bool $credit = false): string
    {
        $snippet = self::render($type, $config, $credit);
        $inline = in_array($type, ['social-share', 'countdown'], true);

        $topPadding = $type === 'announcement-bar'
            && self::text($config, 'position', 'top') !== 'bottom'
            ? 44
            : 0;

        $scrollPixels = 0;
        $scrollRatio = 0;

        if ($type === 'back-to-top') {
            $scrollPixels = self::number($config, 'offset', 400, 100, 5000) + 120;
        } elseif ($type === 'scroll-progress') {
            $scrollRatio = 0.4;
        }

        $document = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* Behaves like an ad blocker so the ad blocker notice is visible in the preview. */
.adsbox,.ads,.ad-placement,.ad-banner,.text-ad{display:none!important}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:#fff;color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;-webkit-font-smoothing:antialiased}
.onw-page{--onw-ink:#0f172a;--onw-muted:#5a6577;--onw-line:#e7eaf1;--onw-tint:#f5f7fb;--onw-accent:#4f46e5;--onw-top:0px}
.onw-nav{position:sticky;top:var(--onw-top);z-index:5;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 24px;background:rgba(255,255,255,.94);border-bottom:1px solid var(--onw-line)}
.onw-brand{display:inline-flex;align-items:center;gap:9px;font-size:13.5px;font-weight:700;letter-spacing:-.01em;white-space:nowrap}
.onw-mark{width:22px;height:22px;border-radius:7px;background:linear-gradient(135deg,#4f46e5,#0ea5e9);box-shadow:0 4px 10px rgba(79,70,229,.32)}
.onw-menu{display:flex;align-items:center;gap:16px;font-size:12.5px;font-weight:500;color:var(--onw-muted)}
.onw-nav-item{white-space:nowrap}
.onw-cta{padding:6px 12px;border-radius:8px;background:var(--onw-ink);color:#fff;font-size:11.5px;font-weight:600;text-decoration:none;white-space:nowrap}
.onw-shell{max-width:720px;margin:0 auto;padding:0 24px}
.onw-hero{padding:38px 0 26px}
.onw-eyebrow{margin:0 0 12px;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--onw-accent)}
.onw-hero h1{margin:0 0 12px;font-size:30px;line-height:1.18;letter-spacing:-.025em}
.onw-hero p{margin:0 0 18px;max-width:56ch;color:var(--onw-muted);font-size:13.5px;line-height:1.7}
.onw-actions{display:flex;flex-wrap:wrap;gap:8px}
.onw-btn{display:inline-flex;align-items:center;padding:8px 14px;border:1px solid var(--onw-line);border-radius:8px;background:#fff;color:var(--onw-ink);font-size:12.5px;font-weight:600}
.onw-btn-primary{background:var(--onw-accent);border-color:var(--onw-accent);color:#fff}
.onw-inline{margin:0 0 30px}
.onw-body{padding-bottom:130px}
.onw-meta{display:flex;align-items:center;gap:8px;margin:0 0 20px;font-size:11.5px;color:#8792a8}
.onw-avatar{width:24px;height:24px;border-radius:999px;background:linear-gradient(135deg,#f59e0b,#ef4444);flex:none}
.onw-article .onw-lede{font-size:15px;line-height:1.7;font-weight:500;color:var(--onw-ink)}
.onw-article p{margin:0 0 14px;font-size:13.5px;line-height:1.75;color:#3d4657}
.onw-article h2{margin:24px 0 10px;font-size:17px;letter-spacing:-.01em}
.onw-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:24px 0 0}
.onw-card{border:1px solid var(--onw-line);border-radius:12px;padding:14px 16px;background:var(--onw-tint)}
.onw-card-title{margin:0 0 4px;font-size:12.5px;font-weight:700}
.onw-card-text{margin:0;font-size:12px;line-height:1.6;color:var(--onw-muted)}
.onw-footer{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;padding:16px 24px;border-top:1px solid var(--onw-line);background:var(--onw-tint);font-size:11.5px;color:#8792a8}
@media (max-width:520px){.onw-nav{padding:12px 16px}.onw-nav-item{display:none}.onw-shell{padding:0 16px}.onw-hero{padding:26px 0 20px}.onw-hero h1{font-size:24px}}
</style>
</head>
<body>
<div class="onw-page" style="--onw-top:__TOP_PAD__px">
<header class="onw-nav">
<span class="onw-brand"><span class="onw-mark"></span>Northwind Studio</span>
<nav class="onw-menu">
<span class="onw-nav-item">Work</span>
<span class="onw-nav-item">Journal</span>
<span class="onw-nav-item">About</span>
<a class="onw-cta" href="#">Book a call</a>
</nav>
</header>
<main class="onw-shell">
<section class="onw-hero">
<p class="onw-eyebrow">Journal · Field notes</p>
<h1>Designing pages that stay calm while doing more</h1>
<p>This sample page is here so you can judge the widget in context — real type, real spacing, real colours. Your own site keeps its design; only the widget comes from the embed code.</p>
<div class="onw-actions">
<span class="onw-btn onw-btn-primary">Start reading</span>
<span class="onw-btn">All articles</span>
</div>
</section>
__INLINE__
<section class="onw-body">
<div class="onw-meta"><span class="onw-avatar"></span>Mara Lindqvist · 6 min read · Updated today</div>
<article class="onw-article">
<p class="onw-lede">Most interfaces fail quietly: nothing is broken, yet the page feels busy. The fix is rarely another feature.</p>
<p>Spacing, contrast and one clear accent do most of the work. When a page respects the reader's attention, every element earns the space it takes up.</p>
<p>We start by writing the page as plain text, then decide what deserves emphasis. Anything that survives that edit is usually the only thing you need on screen.</p>
<h2>Start with the reading rhythm</h2>
<p>A single spacing scale keeps sections from competing. Headlines, paragraphs and cards share the same vertical beat, so the eye can move without re-learning the layout.</p>
<p>Colour follows the same rule: one accent, used sparingly, marks the next action. Everything else stays neutral so the accent keeps its meaning.</p>
<h2>Let one thing be loud</h2>
<p>Decide what a visitor should do next, then let that be the loudest element on the page. Secondary actions stay quiet, visible but never louder than the primary one.</p>
<div class="onw-grid">
<div class="onw-card"><p class="onw-card-title">Measured spacing</p><p class="onw-card-text">One scale, applied everywhere, so nothing competes for attention.</p></div>
<div class="onw-card"><p class="onw-card-title">Quiet colour</p><p class="onw-card-text">A neutral base with a single accent that points at the next step.</p></div>
</div>
</article>
</section>
</main>
<footer class="onw-footer">
<span>© 2026 Northwind Studio</span>
<span>Privacy · Terms · Contact</span>
</footer>
</div>
__OVERLAY__
<script>
(function () {
    'use strict';
    var pixels = __SCROLL_PX__;
    var ratio = __SCROLL_RATIO__;
    if (pixels <= 0 && ratio <= 0) { return; }
    function showActiveState() {
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        if (max <= 0) { return; }
        var target = ratio > 0 ? Math.round(max * ratio) : pixels;
        window.scrollTo(0, Math.min(max, target));
    }
    window.requestAnimationFrame(function () { window.setTimeout(showActiveState, 80); });
})();
</script>
</body>
</html>
HTML;

        return strtr($document, [
            '__INLINE__' => $inline ? '<div class="onw-inline">'.$snippet.'</div>' : '',
            '__OVERLAY__' => $inline ? '' : $snippet,
            '__TOP_PAD__' => (string) $topPadding,
            '__SCROLL_PX__' => (string) $scrollPixels,
            '__SCROLL_RATIO__' => (string) $scrollRatio,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function socialShare(array $config): string
    {
        $label = self::text($config, 'label', 'Share this page');
        $style = self::text($config, 'style', 'labelled');
        $theme = self::text($config, 'theme', 'light');
        $shareUrl = self::text($config, 'url');
        $shareText = self::text($config, 'text');
        $align = in_array(self::text($config, 'align', 'left'), ['left', 'center', 'right'], true) ? self::text($config, 'align', 'left') : 'left';
        $copy = self::flag($config, 'copy');
        $selected = self::networks($config);
        $id = 'onw-share-'.substr(md5($label.$style.$theme.$shareUrl.$shareText.$align.implode(',', $selected).($copy ? 'copy' : '')), 0, 8);

        $links = [];

        foreach (self::NETWORKS as $key => $network) {
            if (in_array($key, $selected, true)) {
                $links[] = [$network['label'], $network['url'], $network['mark'], $network['color']];
            }
        }

        $labelBlock = $label === ''
            ? ''
            : '<span class="onw-label">'.self::html($label).'</span>';

        $template = <<<'HTML'
<div id="__ID__" class="onw-share onw-__STYLE__ onw-__THEME__ onw-align-__ALIGN__">__LABEL__<div class="onw-list"></div></div>
<style>
#__ID__{display:flex;flex-wrap:wrap;align-items:center;gap:10px;font-family:inherit;--onw-bg:#fff;--onw-fg:#0f172a;--onw-line:#e2e8f0}
#__ID__.onw-dark{--onw-bg:#1e293b;--onw-fg:#f8fafc;--onw-line:#334155}
#__ID__ .onw-label{font-size:13px;font-weight:600;color:var(--onw-fg)}
#__ID__ .onw-list{display:flex;flex-wrap:wrap;gap:8px}
#__ID__ a{display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:8px 13px;border:1px solid var(--onw-line);border-radius:999px;background:var(--onw-bg);color:var(--onw-fg);font-size:12.5px;font-weight:600;line-height:1;text-decoration:none;transition:transform .15s ease,opacity .15s ease}
#__ID__ a:hover{transform:translateY(-1px);opacity:.9}
#__ID__.onw-icons a{width:36px;padding:0;font-size:12px}
#__ID__.onw-brand a{background:var(--onw-brand);border-color:transparent;color:#fff}
#__ID__.onw-align-center{justify-content:center;text-align:center}
#__ID__.onw-align-right{justify-content:flex-end}
#__ID__ button{display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:8px 13px;border:1px solid var(--onw-line);border-radius:999px;background:var(--onw-bg);color:var(--onw-fg);font-family:inherit;font-size:12.5px;font-weight:600;line-height:1;cursor:pointer}
#__ID__.onw-icons button{width:36px;padding:0;font-size:11px}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var list = root.querySelector('.onw-list');
    if (!list) { return; }
    var pageUrl = __URL__ || window.location.href;
    var encodedUrl = encodeURIComponent(pageUrl);
    var encodedTitle = encodeURIComponent(__TEXT__ || document.title || '');
    __LINKS__.forEach(function (network) {
        var link = document.createElement('a');
        link.href = network[1].replace('{u}', encodedUrl).replace('{t}', encodedTitle);
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', 'Share on ' + network[0]);
        link.textContent = __ICONS__ ? network[2] : 'Share on ' + network[0];
        link.style.setProperty('--onw-brand', network[3]);
        list.appendChild(link);
    });
    if (__COPY__) {
        var copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.textContent = __COPY_LABEL__;
        copyButton.addEventListener('click', function () {
            var confirmCopy = function () {
                copyButton.textContent = __COPIED_LABEL__;
                window.setTimeout(function () { copyButton.textContent = __COPY_LABEL__; }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(pageUrl).then(confirmCopy, function () {});
                return;
            }
            var field = document.createElement('textarea');
            field.value = pageUrl;
            field.setAttribute('readonly', 'readonly');
            field.style.cssText = 'position:absolute;left:-9999px;top:0';
            document.body.appendChild(field);
            field.select();
            try { document.execCommand('copy'); confirmCopy(); } catch (error) {}
            if (field.parentNode) { field.parentNode.removeChild(field); }
        });
        list.appendChild(copyButton);
    }
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__STYLE__' => $style === 'icons' ? 'icons' : 'labelled',
            '__THEME__' => in_array($theme, ['light', 'dark', 'brand'], true) ? $theme : 'light',
            '__LABEL__' => $labelBlock,
            '__ALIGN__' => $align,
            '__URL__' => self::js($shareUrl),
            '__TEXT__' => self::js($shareText),
            '__LINKS__' => self::json($links),
            '__ICONS__' => $style === 'icons' ? 'true' : 'false',
            '__COPY__' => $copy ? 'true' : 'false',
            '__COPY_LABEL__' => self::js($style === 'icons' ? 'Link' : 'Copy link'),
            '__COPIED_LABEL__' => self::js('Copied'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function adblockDetector(array $config): string
    {
        $title = self::text($config, 'title', 'Please support this site');
        $message = self::text($config, 'message', 'We noticed an ad blocker is running. Ads keep our articles free — consider allowing ads on this site.');
        $style = self::text($config, 'style', 'banner');
        $accent = self::color($config, 'accent', '#4f46e5');
        $delay = self::number($config, 'delay', 1200, 200, 8000);
        $position = self::text($config, 'position', 'right') === 'left' ? 'left' : 'right';
        $ctaLabel = self::text($config, 'cta_label');
        $ctaUrl = self::text($config, 'cta_url');
        $remember = self::flag($config, 'remember', true);
        $id = 'onw-adblock-'.substr(md5($title.$style.$accent.$position.$ctaLabel.$ctaUrl), 0, 8);
        $memoryKey = 'onw-adblock-'.substr(md5($title.$ctaLabel.$ctaUrl), 0, 6);

        $template = <<<'HTML'
<div id="__ID__" class="onw-ab" data-style="__STYLE__" data-position="__POSITION__" role="dialog" aria-live="polite" aria-label="Ad blocker notice"></div>
<style>
#__ID__{font-family:inherit;--onw-accent:__ACCENT__}
#__ID__ .onw-ab-box{position:fixed;z-index:2147483000;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 18px 45px rgba(15,23,42,.18);padding:18px 20px;max-width:420px;font-size:13px;line-height:1.55}
#__ID__ .onw-ab-title{margin:0 0 4px;font-size:14px;font-weight:700;padding-right:20px}
#__ID__ .onw-ab-text{margin:0;color:#475569}
#__ID__ .onw-ab-close{position:absolute;top:8px;right:10px;border:0;background:transparent;color:#94a3b8;font-size:18px;line-height:1;cursor:pointer;padding:2px 4px}
#__ID__ .onw-ab-close:hover{color:#0f172a}
#__ID__[data-style="banner"] .onw-ab-box{left:16px;right:16px;bottom:16px;max-width:none;border-top:3px solid var(--onw-accent)}
#__ID__[data-style="toast"] .onw-ab-box{bottom:16px;right:16px;left:auto}
#__ID__[data-style="toast"][data-position="left"] .onw-ab-box{left:16px;right:auto}
#__ID__ .onw-ab-cta{display:inline-flex;align-items:center;margin-top:12px;padding:7px 12px;border-radius:8px;background:var(--onw-accent);color:#fff;font-size:12px;font-weight:600;text-decoration:none}
#__ID__[data-style="modal"]{position:fixed;inset:0;z-index:2147483000;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:20px}
#__ID__[data-style="modal"]:empty{display:none}
#__ID__[data-style="modal"] .onw-ab-box{position:relative;max-width:400px;border-top:3px solid var(--onw-accent)}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var memoryKey = __MEMORY_KEY__;
    var remember = __REMEMBER__;
    try {
        if (remember && window.localStorage.getItem(memoryKey)) { return; }
    } catch (error) {}
    var probe = document.createElement('div');
    probe.className = 'adsbox ads ad-placement ad-banner text-ad';
    probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:300px;height:250px;';
    probe.innerHTML = '&nbsp;';
    document.body.appendChild(probe);
    window.setTimeout(function () {
        var computed = window.getComputedStyle(probe);
        var blocked = probe.offsetHeight === 0
            || probe.offsetWidth === 0
            || probe.clientHeight === 0
            || computed.display === 'none'
            || computed.visibility === 'hidden';
        if (probe.parentNode) { probe.parentNode.removeChild(probe); }
        if (!blocked) { return; }
        show();
    }, __DELAY__);
    function show() {
        var box = document.createElement('div');
        box.className = 'onw-ab-box';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'onw-ab-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '\u00d7';
        var heading = document.createElement('p');
        heading.className = 'onw-ab-title';
        heading.textContent = __TITLE__;
        var text = document.createElement('p');
        text.className = 'onw-ab-text';
        text.textContent = __MESSAGE__;
        close.addEventListener('click', function () {
            if (box.parentNode) { box.parentNode.removeChild(box); }
            if (__STYLE_JS__ === 'modal') { root.style.display = 'none'; }
            if (remember) {
                try { window.localStorage.setItem(memoryKey, '1'); } catch (error) {}
            }
        });
        box.appendChild(close);
        box.appendChild(heading);
        box.appendChild(text);
        if (__CTA_LABEL__) {
            var cta = document.createElement('a');
            cta.className = 'onw-ab-cta';
            cta.href = __CTA_URL__ || '#';
            cta.target = '_blank';
            cta.rel = 'noopener noreferrer';
            cta.textContent = __CTA_LABEL__;
            box.appendChild(cta);
        }
        root.appendChild(box);
    }
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__STYLE__' => in_array($style, ['banner', 'modal', 'toast'], true) ? $style : 'banner',
            '__STYLE_JS__' => self::js(in_array($style, ['banner', 'modal', 'toast'], true) ? $style : 'banner'),
            '__POSITION__' => $position,
            '__ACCENT__' => self::html($accent),
            '__DELAY__' => (string) $delay,
            '__TITLE__' => self::js($title),
            '__MESSAGE__' => self::js($message),
            '__CTA_LABEL__' => self::js($ctaLabel),
            '__CTA_URL__' => self::js($ctaUrl),
            '__REMEMBER__' => $remember ? 'true' : 'false',
            '__MEMORY_KEY__' => self::js($memoryKey),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function cookieBanner(array $config): string
    {
        $message = self::text($config, 'message', 'We use cookies to improve your experience and analyse traffic.');
        $accept = self::text($config, 'accept_label', 'Accept all');
        $decline = self::text($config, 'decline_label', 'Decline');
        $policyLabel = self::text($config, 'policy_label', 'Cookie policy');
        $policyUrl = self::text($config, 'policy_url');
        $position = self::text($config, 'position', 'bottom');
        $accent = self::color($config, 'accent', '#4f46e5');
        $theme = self::text($config, 'theme', 'light');
        $showDecline = self::flag($config, 'show_decline', true);
        $expiresDays = self::number($config, 'expires_days', 180, 1, 365);
        $id = 'onw-cookie-'.substr(md5($message.$position.$accent.$theme.($showDecline ? 'decline' : '')), 0, 8);
        $key = 'onw-cookie-'.substr(md5($message.$policyUrl.$expiresDays), 0, 6);

        $template = <<<'HTML'
<div id="__ID__" class="onw-ck onw-__POSITION__ onw-__THEME__" role="region" aria-label="Cookie consent"></div>
<style>
#__ID__{font-family:inherit;--onw-accent:__ACCENT__;--onw-bg:#fff;--onw-fg:#0f172a;--onw-muted:#475569;--onw-line:#e2e8f0}
#__ID__.onw-dark{--onw-bg:#0f172a;--onw-fg:#f8fafc;--onw-muted:#cbd5e1;--onw-line:#334155}
#__ID__ .onw-ck-box{position:fixed;z-index:2147482000;display:flex;flex-wrap:wrap;align-items:center;gap:12px;background:var(--onw-bg);color:var(--onw-fg);border-top:1px solid var(--onw-line);padding:14px 18px;font-size:12.5px;line-height:1.5;box-shadow:0 -8px 30px rgba(15,23,42,.12)}
#__ID__.onw-bottom .onw-ck-box{left:0;right:0;bottom:0}
#__ID__.onw-top .onw-ck-box{left:0;right:0;top:0;border-top:0;border-bottom:1px solid var(--onw-line);box-shadow:0 8px 30px rgba(15,23,42,.12)}
#__ID__.onw-bottom-left .onw-ck-box{left:18px;bottom:18px;right:auto;max-width:380px;border:1px solid var(--onw-line);border-radius:14px;box-shadow:0 18px 45px rgba(15,23,42,.18)}
#__ID__ .onw-ck-text{margin:0;flex:1 1 240px;color:var(--onw-muted)}
#__ID__ .onw-ck-link{color:var(--onw-accent);font-weight:600;text-decoration:underline;text-underline-offset:2px}
#__ID__ .onw-ck-actions{display:flex;gap:8px;margin-left:auto}
#__ID__ button{border-radius:999px;font-size:12.5px;font-weight:600;padding:8px 14px;cursor:pointer;border:1px solid var(--onw-line);background:transparent;color:var(--onw-fg)}
#__ID__ .onw-ck-accept{background:var(--onw-accent);border-color:var(--onw-accent);color:#fff}
#__ID__ button:hover{opacity:.9}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var key = __KEY__;
    var days = __EXPIRES__;
    var stored = null;
    try { stored = window.localStorage.getItem(key); } catch (error) {}
    if (stored) {
        var record = null;
        try { record = JSON.parse(stored); } catch (error) {}
        if (record && record.value) {
            var age = (Date.now() - Number(record.at || 0)) / 86400000;
            if (!(age >= days)) { return; }
        }
    }
    var box = document.createElement('div');
    box.className = 'onw-ck-box';
    var text = document.createElement('p');
    text.className = 'onw-ck-text';
    text.textContent = __MESSAGE__;
    box.appendChild(text);
    if (__POLICY_URL__) {
        var link = document.createElement('a');
        link.className = 'onw-ck-link';
        link.href = __POLICY_URL__;
        link.textContent = __POLICY_LABEL__;
        box.appendChild(link);
    }
    var actions = document.createElement('div');
    actions.className = 'onw-ck-actions';
    var accept = document.createElement('button');
    accept.type = 'button';
    accept.className = 'onw-ck-accept';
    accept.textContent = __ACCEPT__;
    accept.addEventListener('click', function () { remember('accepted'); });
    if (__SHOW_DECLINE__) {
        var decline = document.createElement('button');
        decline.type = 'button';
        decline.textContent = __DECLINE__;
        decline.addEventListener('click', function () { remember('declined'); });
        actions.appendChild(decline);
    }
    function remember(value) {
        try { window.localStorage.setItem(key, JSON.stringify({ value: value, at: Date.now() })); } catch (error) {}
        if (box.parentNode) { box.parentNode.removeChild(box); }
    }
    actions.appendChild(accept);
    box.appendChild(actions);
    root.appendChild(box);
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__POSITION__' => in_array($position, ['bottom', 'top', 'bottom-left'], true) ? $position : 'bottom',
            '__THEME__' => $theme === 'dark' ? 'dark' : 'light',
            '__ACCENT__' => self::html($accent),
            '__KEY__' => self::js($key),
            '__EXPIRES__' => (string) $expiresDays,
            '__SHOW_DECLINE__' => $showDecline ? 'true' : 'false',
            '__MESSAGE__' => self::js($message),
            '__POLICY_URL__' => self::js($policyUrl),
            '__POLICY_LABEL__' => self::js($policyLabel),
            '__ACCEPT__' => self::js($accept),
            '__DECLINE__' => self::js($decline),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function whatsappButton(array $config): string
    {
        $phone = preg_replace('/\D+/', '', self::text($config, 'phone')) ?? '';
        $message = self::text($config, 'message', 'Hi! I would like to know more.');
        $label = self::text($config, 'label', 'Chat with us');
        $position = self::text($config, 'position', 'right');
        $color = self::color($config, 'color', '#25d366');
        $greeting = self::text($config, 'greeting');
        $alwaysLabel = self::flag($config, 'always_label');

        if ($phone === '') {
            return '<div style="font-family:inherit;font-size:12.5px;line-height:1.5;padding:12px 14px;border:1px dashed #cbd5e1;border-radius:10px;color:#475569">Add your WhatsApp number in the settings to activate this button.</div>';
        }

        $id = 'onw-wa-'.substr(md5($phone.$label.$position.$color.$greeting.($alwaysLabel ? 'label' : '')), 0, 8);

        $template = <<<'HTML'
<div id="__ID__" class="onw-wa onw-__POSITION__ __ALWAYS_CLASS__"></div>
<style>
#__ID__{font-family:inherit;--onw-bg:__COLOR__}
#__ID__.onw-right{position:fixed;right:20px;bottom:20px;z-index:2147482000}
#__ID__.onw-left{position:fixed;left:20px;bottom:20px;z-index:2147482000}
#__ID__ a{display:inline-flex;align-items:center;gap:10px;padding:10px 14px;border-radius:999px;background:var(--onw-bg);color:#fff;text-decoration:none;font-size:13px;font-weight:600;box-shadow:0 12px 30px rgba(15,23,42,.24);transition:transform .15s ease}
#__ID__ a:hover{transform:translateY(-2px)}
#__ID__ svg{width:20px;height:20px;flex:none}
#__ID__ span{max-width:0;overflow:hidden;white-space:nowrap;opacity:0;transition:max-width .25s ease,opacity .2s ease}
#__ID__ a:hover span,#__ID__ a:focus-visible span,#__ID__.onw-always a span{max-width:180px;opacity:1}
#__ID__ .onw-wa-bubble{position:absolute;right:0;bottom:calc(100% + 10px);width:216px;padding:10px 26px 10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;line-height:1.5;text-align:left;box-shadow:0 14px 34px rgba(15,23,42,.18)}
#__ID__.onw-left .onw-wa-bubble{right:auto;left:0}
#__ID__ .onw-wa-bubble button{position:absolute;top:3px;right:5px;padding:2px 4px;border:0;background:transparent;color:#94a3b8;font-size:14px;line-height:1;cursor:pointer}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var link = document.createElement('a');
    link.href = 'https://wa.me/__PHONE__?text=' + encodeURIComponent(__MESSAGE__);
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.setAttribute('aria-label', __LABEL__);
    link.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 2a8 8 0 1 1-4.2 14.8l-.4-.2-2.6.7.7-2.5-.3-.4A8 8 0 0 1 12 4Z"/></svg><span></span>';
    var label = link.querySelector('span');
    if (label) { label.textContent = __LABEL__; }
    root.appendChild(link);
    if (__GREETING__) {
        var bubble = document.createElement('div');
        bubble.className = 'onw-wa-bubble';
        var greeting = document.createElement('span');
        greeting.textContent = __GREETING__;
        var dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.setAttribute('aria-label', 'Dismiss message');
        dismiss.textContent = '\u00d7';
        dismiss.addEventListener('click', function () {
            if (bubble.parentNode) { bubble.parentNode.removeChild(bubble); }
        });
        bubble.appendChild(greeting);
        bubble.appendChild(dismiss);
        root.appendChild(bubble);
        window.setTimeout(function () {
            if (bubble.parentNode) { bubble.parentNode.removeChild(bubble); }
        }, 15000);
    }
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__POSITION__' => $position === 'left' ? 'left' : 'right',
            '__COLOR__' => self::html($color),
            '__PHONE__' => self::html($phone),
            '__MESSAGE__' => self::js($message),
            '__LABEL__' => self::js($label),
            '__ALWAYS_CLASS__' => $alwaysLabel ? 'onw-always' : '',
            '__GREETING__' => self::js($greeting),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function announcementBar(array $config): string
    {
        $message = self::text($config, 'message', 'Free shipping on orders over $50 — this week only.');
        $linkLabel = self::text($config, 'link_label', 'Shop now');
        $linkUrl = self::text($config, 'link_url', '/');
        $position = self::text($config, 'position', 'top');
        $background = self::color($config, 'background', '#4f46e5');
        $textColor = self::color($config, 'text_color', '#ffffff');
        $dismissible = self::flag($config, 'dismissible');
        $linkNewTab = self::flag($config, 'link_new_tab');
        $dismissDays = self::number($config, 'dismiss_days', 0, 0, 365);
        $id = 'onw-announce-'.substr(md5($message.$background.$position.$dismissDays), 0, 8);
        $key = 'onw-announce-'.substr(md5($message.$position.$dismissDays), 0, 6);

        $template = <<<'HTML'
<div id="__ID__" class="onw-bar onw-__POSITION__" role="region" aria-label="Announcement" style="--onw-bg:__BACKGROUND__;--onw-fg:__TEXT_COLOR__">
<div class="onw-bar-inner">
<span class="onw-bar-text">__MESSAGE__</span>
<a class="onw-bar-link" href="__LINK_URL__"__LINK_TARGET__>__LINK_LABEL__</a>
__DISMISS__
</div>
</div>
<style>
#__ID__{position:fixed;left:0;right:0;z-index:2147481000;background:var(--onw-bg);color:var(--onw-fg);font-family:inherit;font-size:12.5px;box-shadow:0 4px 18px rgba(15,23,42,.16)}
#__ID__.onw-top{top:0}
#__ID__.onw-bottom{bottom:0}
#__ID__ .onw-bar-inner{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;padding:10px 42px;text-align:center}
#__ID__ .onw-bar-text{font-weight:600;letter-spacing:.01em}
#__ID__ .onw-bar-link{color:inherit;text-decoration:underline;text-underline-offset:3px;font-weight:700;white-space:nowrap}
#__ID__ .onw-bar-close{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:inherit;font-size:18px;line-height:1;cursor:pointer;opacity:.75;padding:4px 6px}
#__ID__ .onw-bar-close:hover{opacity:1}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var close = root.querySelector('.onw-bar-close');
    if (!close) { return; }
    var key = __KEY__;
    var days = __DISMISS_DAYS__;
    var storage = null;
    try { storage = days > 0 ? window.localStorage : window.sessionStorage; } catch (error) {}
    function dismissed() {
        if (!storage) { return false; }
        try {
            var raw = storage.getItem(key);
            if (!raw) { return false; }
            if (days <= 0) { return raw === '1'; }
            var at = Number(raw);
            return isFinite(at) && (Date.now() - at) / 86400000 < days;
        } catch (error) { return false; }
    }
    if (dismissed()) { root.style.display = 'none'; return; }
    close.addEventListener('click', function () {
        try { if (storage) { storage.setItem(key, days > 0 ? String(Date.now()) : '1'); } } catch (error) {}
        root.style.display = 'none';
    });
})();
</script>
HTML;

        $dismissBlock = $dismissible
            ? '<button type="button" class="onw-bar-close" aria-label="Dismiss announcement">&times;</button>'
            : '';

        return strtr($template, [
            '__ID__' => $id,
            '__POSITION__' => $position === 'bottom' ? 'bottom' : 'top',
            '__BACKGROUND__' => self::html($background),
            '__TEXT_COLOR__' => self::html($textColor),
            '__MESSAGE__' => self::html($message),
            '__LINK_URL__' => self::html($linkUrl === '' ? '/' : $linkUrl),
            '__LINK_LABEL__' => self::html($linkLabel),
            '__DISMISS__' => $dismissBlock,
            '__KEY__' => self::js($key),
            '__DISMISS_DAYS__' => (string) $dismissDays,
            '__LINK_TARGET__' => $linkNewTab ? ' target="_blank" rel="noopener noreferrer"' : '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function countdown(array $config): string
    {
        $title = self::text($config, 'title', 'Launching soon');
        $endsAt = self::text($config, 'ends_at');
        $background = self::color($config, 'background', '#0f172a');
        $textColor = self::color($config, 'text_color', '#ffffff');
        $accent = self::color($config, 'accent', '#818cf8');
        $expired = self::text($config, 'expired_text', 'The offer has ended.');
        $showLabels = self::flag($config, 'show_labels', true);
        $hideWhenDone = self::flag($config, 'hide_when_done');
        $id = 'onw-countdown-'.substr(md5($title.$endsAt.$background.$accent.($showLabels ? 'labels' : '').($hideWhenDone ? 'hide' : '')), 0, 8);

        $template = <<<'HTML'
<div id="__ID__" class="onw-cd" data-labels="__LABELS__" style="--onw-bg:__BACKGROUND__;--onw-fg:__TEXT_COLOR__;--onw-accent:__ACCENT__">
<p class="onw-cd-title">__TITLE__</p>
<div class="onw-cd-grid" aria-live="off">
<div><span data-unit="days">00</span><small>Days</small></div>
<div><span data-unit="hours">00</span><small>Hours</small></div>
<div><span data-unit="minutes">00</span><small>Minutes</small></div>
<div><span data-unit="seconds">00</span><small>Seconds</small></div>
</div>
<p class="onw-cd-ended" hidden>__EXPIRED__</p>
</div>
<style>
#__ID__{background:var(--onw-bg);color:var(--onw-fg);font-family:inherit;padding:22px 24px;border-radius:16px;text-align:center}
#__ID__ .onw-cd-title{margin:0 0 14px;font-size:15px;font-weight:700;letter-spacing:.01em}
#__ID__ .onw-cd-grid{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
#__ID__ .onw-cd-grid>div{min-width:64px;padding:10px 8px;border-radius:12px;background:rgba(255,255,255,.08)}
#__ID__ .onw-cd-grid span{display:block;font-size:24px;font-weight:700;color:var(--onw-accent);font-variant-numeric:tabular-nums;line-height:1.1}
#__ID__ .onw-cd-grid small{display:block;margin-top:4px;font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;opacity:.7}
#__ID__[data-labels="0"] .onw-cd-grid small{display:none}
#__ID__ .onw-cd-ended{margin:0;font-size:13px;font-weight:600}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var grid = root.querySelector('.onw-cd-grid');
    var ended = root.querySelector('.onw-cd-ended');
    var raw = __ENDS_AT__;
    if (!raw) { return; }
    var target = new Date(raw.replace(' ', 'T'));
    if (isNaN(target.getTime())) { return; }
    var units = {
        days: root.querySelector('[data-unit="days"]'),
        hours: root.querySelector('[data-unit="hours"]'),
        minutes: root.querySelector('[data-unit="minutes"]'),
        seconds: root.querySelector('[data-unit="seconds"]')
    };
    function pad(value) { return value < 10 ? '0' + value : '' + value; }
    function tick() {
        var diff = target.getTime() - Date.now();
        if (diff <= 0) {
            if (__HIDE_WHEN_DONE__) {
                root.style.display = 'none';
            } else {
                if (grid) { grid.setAttribute('hidden', 'hidden'); }
                if (ended) { ended.removeAttribute('hidden'); }
            }
            window.clearInterval(timer);
            return;
        }
        var days = Math.floor(diff / 86400000);
        var hours = Math.floor((diff % 86400000) / 3600000);
        var minutes = Math.floor((diff % 3600000) / 60000);
        var seconds = Math.floor((diff % 60000) / 1000);
        if (units.days) { units.days.textContent = pad(days); }
        if (units.hours) { units.hours.textContent = pad(hours); }
        if (units.minutes) { units.minutes.textContent = pad(minutes); }
        if (units.seconds) { units.seconds.textContent = pad(seconds); }
    }
    var timer = window.setInterval(tick, 1000);
    tick();
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__BACKGROUND__' => self::html($background),
            '__TEXT_COLOR__' => self::html($textColor),
            '__ACCENT__' => self::html($accent),
            '__TITLE__' => self::html($title),
            '__EXPIRED__' => self::html($expired),
            '__ENDS_AT__' => self::js($endsAt),
            '__LABELS__' => $showLabels ? '1' : '0',
            '__HIDE_WHEN_DONE__' => $hideWhenDone ? 'true' : 'false',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function backToTop(array $config): string
    {
        $label = self::text($config, 'label');
        $position = self::text($config, 'position', 'right');
        $offset = self::number($config, 'offset', 400, 100, 5000);
        $background = self::color($config, 'background', '#0f172a');
        $shape = self::text($config, 'shape', 'pill') === 'circle' ? 'circle' : 'pill';
        $smooth = self::flag($config, 'smooth', true);
        $id = 'onw-top-'.substr(md5($label.$position.$background.$offset.$shape.($smooth ? 'smooth' : '')), 0, 8);

        $template = <<<'HTML'
<button id="__ID__" type="button" class="onw-top onw-__POSITION__ onw-__SHAPE__" aria-label="Back to top" hidden style="--onw-bg:__BACKGROUND__">
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M5 12l7-7 7 7"/></svg>
__LABEL_BLOCK__
</button>
<style>
#__ID__{position:fixed;bottom:22px;z-index:2147482000;display:inline-flex;align-items:center;gap:8px;padding:11px 13px;border:0;border-radius:999px;background:var(--onw-bg);color:#fff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;box-shadow:0 12px 28px rgba(15,23,42,.28);transition:transform .15s ease,opacity .15s ease}
#__ID__[hidden]{display:none}
#__ID__.onw-right{right:22px}
#__ID__.onw-left{left:22px}
#__ID__.onw-circle{padding:10px;width:42px;height:42px;justify-content:center;gap:0}
#__ID__.onw-circle span{display:none}
#__ID__:hover{transform:translateY(-2px)}
#__ID__ svg{width:18px;height:18px;flex:none}
</style>
<script>
(function () {
    'use strict';
    var button = document.getElementById('__ID__');
    if (!button) { return; }
    function toggle() {
        if (window.scrollY > __OFFSET__) { button.removeAttribute('hidden'); }
        else { button.setAttribute('hidden', 'hidden'); }
    }
    window.addEventListener('scroll', toggle, { passive: true });
    button.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: __BEHAVIOR__ });
    });
    toggle();
})();
</script>
HTML;

        $labelBlock = $label === '' ? '' : '<span>'.self::html($label).'</span>';

        return strtr($template, [
            '__ID__' => $id,
            '__POSITION__' => $position === 'left' ? 'left' : 'right',
            '__BACKGROUND__' => self::html($background),
            '__OFFSET__' => (string) $offset,
            '__LABEL_BLOCK__' => $labelBlock,
            '__SHAPE__' => $shape,
            '__BEHAVIOR__' => self::js($smooth ? 'smooth' : 'auto'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function scrollProgress(array $config): string
    {
        $color = self::color($config, 'color', '#4f46e5');
        $height = self::number($config, 'height', 3, 1, 12);
        $position = self::text($config, 'position', 'top');
        $trackColor = self::color($config, 'track_color', '#e5e7eb');
        $centered = self::text($config, 'align', 'full') === 'center';
        $rounded = self::flag($config, 'rounded');
        $id = 'onw-progress-'.substr(md5($color.$height.$position.$trackColor.($centered ? 'center' : '').($rounded ? 'rounded' : '')), 0, 8);

        $template = <<<'HTML'
<div id="__ID__" class="onw-progress onw-__POSITION__ __ALIGN_CLASS__ __ROUNDED_CLASS__" aria-hidden="true" style="--onw-color:__COLOR__;--onw-track:__TRACK__;--onw-height:__HEIGHT__px"><span></span></div>
<style>
#__ID__{position:fixed;left:0;right:0;height:var(--onw-height);z-index:2147481500;pointer-events:none;background:var(--onw-track)}
#__ID__.onw-center{left:50%;right:auto;transform:translateX(-50%);width:64%;border-radius:999px;overflow:hidden}
#__ID__.onw-rounded span{border-radius:999px}
#__ID__.onw-top{top:0}
#__ID__.onw-bottom{bottom:0}
#__ID__ span{display:block;height:100%;width:0;background:var(--onw-color);transition:width .08s linear}
</style>
<script>
(function () {
    'use strict';
    var root = document.getElementById('__ID__');
    if (!root) { return; }
    var bar = root.querySelector('span');
    if (!bar) { return; }
    function update() {
        var scrollable = document.documentElement.scrollHeight - window.innerHeight;
        var progress = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
        bar.style.width = Math.max(0, Math.min(100, progress)) + '%';
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
</script>
HTML;

        return strtr($template, [
            '__ID__' => $id,
            '__POSITION__' => $position === 'bottom' ? 'bottom' : 'top',
            '__COLOR__' => self::html($color),
            '__TRACK__' => self::html($trackColor),
            '__ALIGN_CLASS__' => $centered ? 'onw-center' : '',
            '__ROUNDED_CLASS__' => $rounded ? 'onw-rounded' : '',
            '__HEIGHT__' => (string) $height,
        ]);
    }

    /**
     * The known share targets selected in the settings.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function networks(array $config): array
    {
        $raw = strtolower(self::text($config, 'networks', 'x,facebook,linkedin,whatsapp'));
        $selected = array_filter(array_map(trim(...), explode(',', $raw)));
        $selected = array_values(array_intersect($selected, array_keys(self::NETWORKS)));

        return $selected === [] ? ['x', 'facebook', 'linkedin', 'whatsapp'] : $selected;
    }

    /**
     * Read a trimmed string setting.
     *
     * @param  array<string, mixed>  $config
     */
    private static function text(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Read a hex colour setting.
     *
     * @param  array<string, mixed>  $config
     */
    private static function color(array $config, string $key, string $default): string
    {
        $value = self::text($config, $key, $default);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $default;
    }

    /**
     * Read a whole number setting within bounds.
     *
     * @param  array<string, mixed>  $config
     */
    private static function number(array $config, string $key, int $default, int $min, int $max): int
    {
        $value = $config[$key] ?? $default;

        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    /**
     * Read a true/false setting.
     *
     * @param  array<string, mixed>  $config
     */
    private static function flag(array $config, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $config)) {
            return $default;
        }

        return filter_var($config[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Encode a value for safe use inside a script tag.
     */
    private static function js(string $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Encode data for safe use inside a script tag.
     *
     * @param  array<int, array<int, string>>  $value
     */
    private static function json(array $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Escape text for HTML output.
     */
    private static function html(string $value): string
    {
        return (string) e($value);
    }
}
