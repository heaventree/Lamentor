<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Models\Document;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

/**
 * Original Lamentor widget: a menu with dropdowns and optional "mega" panels (another document rendered
 * inside the dropdown). Accessible: buttons with aria-expanded, Escape closes, keyboard focus opens.
 */
class NavMenu extends Widget
{
    private const CSS = '.lamentor-nav>ul{display:flex;list-style:none;margin:0;padding:0;gap:20px;flex-wrap:wrap}.lamentor-nav-vertical .lamentor-nav>ul{flex-direction:column}'
        . '.lamentor-nav li{position:relative}.lamentor-nav a,.lamentor-nav button{all:unset;cursor:pointer;display:inline-block;padding:6px 2px}.lamentor-nav a:focus-visible,.lamentor-nav button:focus-visible{outline:2px solid currentColor}'
        . '.lamentor-nav-panel{display:none;position:absolute;left:0;top:100%;min-width:200px;background:#fff;box-shadow:0 6px 24px rgba(0,0,0,.15);padding:10px;z-index:50;list-style:none;margin:0}'
        . '.lamentor-nav li.is-open>.lamentor-nav-panel{display:block}.lamentor-nav-panel a{display:block;color:#222}.lamentor-nav .lamentor-nav-toggle{display:none}'
        . '@media(max-width:767px){.lamentor-nav-collapse-mobile .lamentor-nav-toggle{display:inline-block}.lamentor-nav-collapse-mobile .lamentor-nav>ul{display:none;flex-direction:column}.lamentor-nav-collapse-mobile .lamentor-nav.is-open>ul{display:flex}.lamentor-nav-collapse-mobile .lamentor-nav-panel{position:static;box-shadow:none}}'
        . '@media(max-width:1024px){.lamentor-nav-collapse-tablet .lamentor-nav-toggle{display:inline-block}.lamentor-nav-collapse-tablet .lamentor-nav>ul{display:none;flex-direction:column}.lamentor-nav-collapse-tablet .lamentor-nav.is-open>ul{display:flex}.lamentor-nav-collapse-tablet .lamentor-nav-panel{position:static;box-shadow:none}}';

    private const JS = '(function(){var n=document.currentScript.previousElementSibling;if(!n)return;var t=n.querySelector(".lamentor-nav-toggle");t&&t.addEventListener("click",function(){var o=n.classList.toggle("is-open");t.setAttribute("aria-expanded",o)});'
        . 'n.querySelectorAll("li>button").forEach(function(b){b.addEventListener("click",function(){var li=b.parentNode,o=!li.classList.contains("is-open");n.querySelectorAll("li.is-open").forEach(function(x){x.classList.remove("is-open");x.firstElementChild&&x.firstElementChild.setAttribute&&x.querySelector("button")&&x.querySelector("button").setAttribute("aria-expanded","false")});li.classList.toggle("is-open",o);b.setAttribute("aria-expanded",o)})});'
        . 'n.addEventListener("keydown",function(e){if(e.key==="Escape"){n.querySelectorAll("li.is-open").forEach(function(x){x.classList.remove("is-open");var b=x.querySelector("button");b&&b.setAttribute("aria-expanded","false")})}});})();';

    public static function name(): string
    {
        return 'nav-menu';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $items = '';
        foreach ($this->items('menu_items', $settings) as $item) {
            $label = e((string) ($item['label'] ?? ''));
            $sub = $this->subLinks((string) ($item['submenu'] ?? ''));
            $mega = $this->megaHtml((int) ($item['mega_template'] ?? 0), $renderer);

            if (! $sub && $mega === '') {
                $a = new Attributes();
                $this->addLink($a, 'a', (array) ($item['link'] ?? []));
                $items .= '<li>' . ($a->string('a') !== '' ? "<a {$a->string('a')}>{$label}</a>" : "<span>{$label}</span>") . '</li>';

                continue;
            }

            $panel = $mega !== '' ? $mega : '<ul>' . implode('', array_map(fn ($l) => '<li><a href="' . e($l['url']) . '">' . e($l['label']) . '</a></li>', $sub)) . '</ul>';
            $items .= '<li><button type="button" aria-expanded="false" aria-haspopup="true">' . $label . ' ▾</button>'
                . '<div class="lamentor-nav-panel">' . $panel . '</div></li>';
        }

        return '<style>' . self::CSS . '</style><nav class="lamentor-nav" aria-label="Menu">'
            . '<button type="button" class="lamentor-nav-toggle" aria-expanded="false" aria-label="Toggle menu">☰</button><ul>' . $items . '</ul></nav>'
            . '<script>' . self::JS . '</script>';
    }

    /** @return list<array{label:string,url:string}> only http(s), relative and mailto links survive */
    private function subLinks(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label !== '' && preg_match('#^(/(?!/)|https?://|mailto:|\#)#i', $url)) {
                $out[] = ['label' => $label, 'url' => $url];
            }
        }

        return $out;
    }

    private function megaHtml(int $id, Renderer $renderer): string
    {
        if ($id <= 0) {
            return '';
        }
        $doc = Document::query()->whereKey($id)->whereIn('type', ['section', 'mega-menu'])->where('status', 'publish')->first();

        return $doc ? $renderer->renderAll(array_map(fn (array $n) => Element::fromArray($n), $doc->data ?? [])) : '';
    }
}
