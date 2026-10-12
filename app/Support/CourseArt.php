<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Str;

/**
 * The illustrated cover a course shows when no thumbnail has been uploaded.
 *
 * Replaces the flat two-tone block with a small scene that says what the course
 * is about: an editor for development, a chart for data, a shield for security.
 * The scene is chosen from the category name and the colours from the course id,
 * so a course keeps the same face on every page.
 *
 * Every value inside the SVG is a literal from this file. No course data is
 * interpolated into the markup, so the output is safe to print unescaped.
 */
final class CourseArt
{
    /** Deep brand tone, then the lighter tone the glow fades to. */
    private const PALETTES = [
        ['#052826', '#00615c'],
        ['#2a0705', '#800d07'],
        ['#043b38', '#167871'],
        ['#1a1815', '#4a473f'],
        ['#024e4a', '#3c8e88'],
        ['#470704', '#a31009'],
        ['#052826', '#167871'],
        ['#2e2c27', '#66625a'],
    ];

    /** Category keywords, checked in order, mapped to a scene. */
    private const KEYWORDS = [
        'security' => 'shield', 'cyber' => 'shield',
        'sql' => 'table', 'database' => 'table',
        'machine' => 'scatter', 'ai' => 'scatter', 'ml' => 'scatter',
        'data' => 'chart', 'analytic' => 'chart',
        'cloud' => 'cloud', 'aws' => 'cloud',
        'devops' => 'boxes', 'docker' => 'boxes',
        'linux' => 'term', 'network' => 'term',
        'design' => 'design', 'ui' => 'design', 'ux' => 'design',
        'frontend' => 'tree', 'react' => 'tree', 'mobile' => 'tree',
    ];

    public static function kind(Course $course): string
    {
        // `??` already covers a course with no category: it checks the whole
        // chain, so a null relation falls through to ''.
        $haystack = Str::lower(($course->category->name ?? '').' '.$course->title);

        foreach (self::KEYWORDS as $needle => $kind) {
            if (preg_match('/\b'.preg_quote($needle, '/').'/', $haystack) === 1) {
                return $kind;
            }
        }

        return 'code';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function palette(Course $course): array
    {
        return self::PALETTES[(int) $course->getKey() % count(self::PALETTES)];
    }

    public static function svg(Course $course): string
    {
        [$deep, $glow] = self::palette($course);
        $id = 'art-'.$course->getKey();

        return '<svg viewBox="0 0 320 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true" class="absolute inset-0 h-full w-full">'
            .'<defs><radialGradient id="'.$id.'" cx="80%" cy="10%" r="90%"><stop offset="0" stop-color="'.$glow.'"/><stop offset="1" stop-color="'.$deep.'"/></radialGradient></defs>'
            .'<rect width="320" height="180" fill="url(#'.$id.')"/>'
            .'<circle cx="290" cy="20" r="70" fill="#fff" opacity=".05"/><circle cx="30" cy="170" r="50" fill="#fff" opacity=".04"/>'
            .self::scene(self::kind($course))
            .'</svg>';
    }

    private static function scene(string $kind): string
    {
        return match ($kind) {
            'chart' => self::chart(),
            'table' => self::table(),
            'shield' => self::shield(),
            'cloud' => self::cloud(),
            'boxes' => self::boxes(),
            'design' => self::design(),
            'scatter' => self::scatter(),
            'tree' => self::tree(),
            'term' => self::terminal(),
            default => self::code(),
        };
    }

    private static function window(int $x, int $y, int $w, int $h): string
    {
        return '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" rx="10" fill="#0b1514" opacity=".94"/>'
            .'<circle cx="'.($x + 14).'" cy="'.($y + 13).'" r="3.2" fill="#f2aa84"/>'
            .'<circle cx="'.($x + 25).'" cy="'.($y + 13).'" r="3.2" fill="#fef1de"/>'
            .'<circle cx="'.($x + 36).'" cy="'.($y + 13).'" r="3.2" fill="#6fada8"/>';
    }

    private static function text(int $x, int $y, string $text, string $fill, string $extra = ''): string
    {
        return '<text x="'.$x.'" y="'.$y.'" font-family="JetBrains Mono, monospace" font-size="10.5" fill="'.$fill.'" '.$extra.'>'.$text.'</text>';
    }

    private static function code(): string
    {
        $out = self::window(46, 24, 228, 136);
        $lines = [[0, 60, '#cde3e1'], [0, 96, '#f2aa84'], [14, 120, '#a5ccc9'], [14, 70, '#fef1de'], [28, 104, '#ffffff'], [28, 60, '#6fada8'], [14, 40, '#f2aa84'], [0, 30, '#cde3e1']];

        foreach ($lines as $i => [$indent, $width, $fill]) {
            $out .= '<rect x="'.(62 + $indent).'" y="'.(48 + $i * 13).'" width="'.$width.'" height="6" rx="3" fill="'.$fill.'" opacity=".85"/>';
        }

        return $out.'<rect x="200" y="128" width="88" height="26" rx="13" fill="#00615c" stroke="#a5ccc9"/>'
            .self::text(244, 145, '200 OK', '#fff', 'text-anchor="middle" font-weight="600"');
    }

    private static function chart(): string
    {
        $out = '<rect x="40" y="22" width="240" height="138" rx="12" fill="#fff" opacity=".96"/>';
        $bars = [38, 56, 48, 74, 64, 92, 104];
        $points = [];

        foreach ($bars as $i => $v) {
            $out .= '<rect x="'.(62 + $i * 29).'" y="'.(148 - $v).'" width="17" height="'.$v.'" rx="4" fill="'.($i === 6 ? '#a31009' : '#00615c').'" opacity="'.($i === 6 ? 1 : 0.25 + $i * 0.1).'"/>';
            $points[] = (70 + $i * 29).','.(140 - $v);
        }

        return $out.'<polyline points="'.implode(' ', $points).'" fill="none" stroke="#052826" stroke-width="2.4"/>'
            .self::text(56, 42, 'revenue_by_month', '#8a867b');
    }

    private static function table(): string
    {
        $out = '<rect x="44" y="26" width="232" height="132" rx="12" fill="#fff" opacity=".96"/><rect x="44" y="26" width="232" height="26" rx="12" fill="#052826"/><rect x="44" y="40" width="232" height="12" fill="#052826"/>';

        foreach (['city', 'orders', 'avg_value'] as $i => $h) {
            $out .= self::text(58 + $i * 76, 43, $h, '#fef1de');
        }

        foreach ([['Pune', '1,284', '842'], ['Kochi', '962', '910'], ['Delhi', '2,107', '788'], ['Surat', '655', '1,020']] as $j => $row) {
            $out .= '<rect x="44" y="'.(54 + $j * 25).'" width="232" height="25" fill="'.($j === 2 ? '#fef1de' : ($j % 2 ? '#faf9f6' : '#fff')).'"/>';

            foreach ($row as $i => $value) {
                $out .= self::text(58 + $i * 76, 71 + $j * 25, $value, '#2e2c27');
            }
        }

        return $out;
    }

    private static function shield(): string
    {
        $out = '<path d="M160 26 214 46v38c0 32-24 52-54 64-30-12-54-32-54-64V46z" fill="rgba(255,255,255,.1)" stroke="#f5cbc9" stroke-width="2"/>'
            .'<path d="m138 88 16 16 30-32" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>';

        foreach ([['XSS', 44, 40], ['SQLi', 236, 58], ['CSRF', 40, 118], ['IDOR', 230, 132]] as [$label, $x, $y]) {
            $out .= '<rect x="'.$x.'" y="'.$y.'" width="'.(strlen($label) * 9 + 18).'" height="22" rx="11" fill="rgba(255,255,255,.16)" stroke="rgba(255,255,255,.35)"/>'
                .self::text($x + 9, $y + 15, $label, '#fff');
        }

        return $out;
    }

    private static function cloud(): string
    {
        $out = '';

        foreach ([86, 160, 234] as $x) {
            $out .= '<path d="M160 72 L'.$x.' 106" stroke="#a5ccc9" stroke-width="2" stroke-dasharray="4 4"/>';
        }

        $out .= '<path d="M128 70a20 20 0 0 1 4-30 26 26 0 0 1 48-8 22 22 0 0 1 14 38z" fill="#fff"/>';

        foreach ([['EC2', 86], ['S3', 160], ['RDS', 234]] as [$label, $x]) {
            $out .= '<rect x="'.($x - 28).'" y="106" width="56" height="34" rx="9" fill="rgba(255,255,255,.14)" stroke="#a5ccc9"/>'
                .self::text($x, 127, $label, '#fff', 'text-anchor="middle" font-weight="600"');
        }

        return $out;
    }

    private static function boxes(): string
    {
        $fills = ['#f2aa84', '#6fada8', '#a5ccc9', '#fef1de', '#ffffff', '#cde3e1'];
        $out = '';
        $i = 0;

        for ($row = 0; $row < 3; $row++) {
            for ($col = 0; $col < 3 - $row; $col++) {
                $x = 96 + $col * 44 + $row * 22;
                $y = 118 - $row * 30;
                $out .= '<rect x="'.$x.'" y="'.$y.'" width="40" height="26" rx="5" fill="'.$fills[$i++ % 6].'" opacity=".92"/>'
                    .'<rect x="'.($x + 6).'" y="'.($y + 8).'" width="28" height="3" rx="1.5" fill="#052826" opacity=".35"/>';
            }
        }

        return $out.'<path d="M70 150h180" stroke="rgba(255,255,255,.4)" stroke-width="2"/>'.self::text(222, 40, 'deploy ok', '#cde3e1');
    }

    private static function design(): string
    {
        return '<rect x="44" y="30" width="120" height="124" rx="12" fill="#fff"/><rect x="56" y="44" width="96" height="44" rx="7" fill="#fbeae9"/>'
            .'<rect x="56" y="98" width="70" height="7" rx="3.5" fill="#a31009" opacity=".5"/><rect x="56" y="112" width="90" height="7" rx="3.5" fill="#e5e3dc"/><rect x="56" y="130" width="50" height="14" rx="7" fill="#052826"/>'
            .'<rect x="180" y="50" width="96" height="104" rx="12" fill="rgba(255,255,255,.18)" stroke="rgba(255,255,255,.5)" stroke-dasharray="5 5"/>'
            .'<path d="M196 130 C 214 70, 246 150, 262 74" fill="none" stroke="#fff" stroke-width="2.5"/><circle cx="196" cy="130" r="4" fill="#fff"/><circle cx="262" cy="74" r="4" fill="#fff"/>'
            .'<path d="m238 116 16 30 4-12 12-4z" fill="#fef1de"/>';
    }

    private static function scatter(): string
    {
        $out = '<rect x="42" y="22" width="236" height="138" rx="12" fill="#fff" opacity=".96"/><path d="M62 144h196M62 144V40" stroke="#e5e3dc" stroke-width="1.5"/>';

        foreach ([[78, 128, 0], [92, 122, 0], [100, 130, 0], [112, 112, 0], [126, 116, 0], [138, 100, 1], [150, 104, 0], [162, 88, 1], [174, 92, 1], [186, 76, 1], [198, 80, 1], [212, 64, 1], [226, 70, 1], [238, 54, 1], [118, 124, 0], [204, 90, 1]] as [$x, $y, $group]) {
            $out .= '<circle cx="'.$x.'" cy="'.$y.'" r="4.2" fill="'.($group ? '#00615c' : '#f2aa84').'" opacity=".9"/>';
        }

        return $out.'<path d="M70 134 250 50" stroke="#052826" stroke-width="2.4" stroke-dasharray="6 4"/>'.self::text(72, 40, 'R² = 0.91', '#8a867b');
    }

    private static function tree(): string
    {
        $nodes = [[160, 40, 'App'], [96, 94, 'Nav'], [160, 94, 'Feed'], [224, 94, 'Cart'], [132, 146, 'Card'], [188, 146, 'Card']];
        $out = '';

        foreach ([[0, 1], [0, 2], [0, 3], [2, 4], [2, 5]] as [$a, $b]) {
            $out .= '<path d="M'.$nodes[$a][0].' '.($nodes[$a][1] + 12).' L'.$nodes[$b][0].' '.($nodes[$b][1] - 12).'" stroke="#a5ccc9" stroke-width="2"/>';
        }

        foreach ($nodes as $i => [$x, $y, $label]) {
            $out .= '<rect x="'.($x - 30).'" y="'.($y - 13).'" width="60" height="26" rx="13" fill="'.($i === 0 ? '#fff' : 'rgba(255,255,255,.16)').'" stroke="#cde3e1"/>'
                .self::text($x, $y + 4, '&lt;'.$label.'/&gt;', $i === 0 ? '#052826' : '#fff', 'text-anchor="middle" font-weight="600"');
        }

        return $out;
    }

    private static function terminal(): string
    {
        $out = self::window(40, 24, 240, 136);
        $lines = [['$ ssh deploy@prod-01', '#fff'], ['$ systemctl status nginx', '#fff'], ['● active (running)', '#6fada8'], ['$ tail -f storage/logs/app.log', '#fff'], ['  queue: 128 jobs done', '#a5ccc9'], ['$ _', '#f2aa84']];

        foreach ($lines as $i => [$line, $fill]) {
            $out .= self::text(56, 56 + $i * 17, $line, $fill);
        }

        return $out;
    }
}
