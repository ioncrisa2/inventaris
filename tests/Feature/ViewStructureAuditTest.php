<?php

use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Koperasi;
use App\Models\PlatformAnnouncement;
use App\Models\Role;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use App\Models\User;
use App\Support\SlipGajiPaperLayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

/** MessageBag yang menganggap setiap field punya error, supaya semua cabang @error ter-render. */
class VsaAllErrorsBag extends MessageBag
{
    public function has($key): bool { return true; }
    public function hasAny($keys = []): bool { return true; }
    public function first($key = null, $format = null): string { return 'Pesan error audit'; }
    public function get($key, $format = null): array { return ['Pesan error audit']; }
    public function any(): bool { return true; }
    public function isEmpty(): bool { return false; }
    public function isNotEmpty(): bool { return true; }
    public function all($format = null): array { return ['Pesan error audit']; }
    public function count(): int { return 1; }
}

function vsaLabel(string $tag, string $attrs): string
{
    $id = preg_match('/\sid\s*=\s*"([^"]*)"/', $attrs, $m) ? '#'.$m[1] : '';
    $class = preg_match('/\sclass\s*=\s*"([^"]*)"/', $attrs, $m) ? '.'.implode('.', array_slice(preg_split('/\s+/', trim($m[1])), 0, 3)) : '';

    return $tag.$id.$class;
}

function vsaElementLabel(Dom\Element $el): string
{
    return strtolower($el->tagName).'.'.str_replace(' ', '.', (string) $el->getAttribute('class'));
}

/** Tokenizer HTML sederhana: cari tag liar/tidak berpasangan dan konten yang akan direstruktur parser browser. */
function vsaTokenAudit(string $html): array
{
    $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'param'];
    $optional = ['li', 'p', 'td', 'th', 'tr', 'option', 'thead', 'tbody', 'tfoot', 'dt', 'dd', 'optgroup', 'colgroup', 'caption', 'html', 'body', 'head'];
    $raw = ['script', 'style', 'textarea', 'title'];
    $tableCtx = ['table', 'thead', 'tbody', 'tfoot', 'tr'];
    $tableAllowed = ['caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'script', 'template', 'style'];
    $closesP = ['address', 'article', 'aside', 'blockquote', 'details', 'dialog', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul', 'li', 'dd', 'dt'];
    $scopeBoundary = ['table', 'td', 'th', 'button', 'caption', 'template', 'html'];

    $html = preg_replace_callback('/<!--.*?-->/s', fn ($m) => str_repeat("\n", substr_count($m[0], "\n")), $html);
    $issues = [];
    $stack = [];
    $pos = 0;
    $path = fn () => implode(' > ', array_map(fn ($e) => $e['label'], array_slice($stack, -4)));
    $inScope = function (string $tag) use (&$stack, $scopeBoundary) {
        for ($k = count($stack) - 1; $k >= 0; $k--) {
            if ($stack[$k]['tag'] === $tag) {
                return true;
            }
            if (in_array($stack[$k]['tag'], $scopeBoundary, true)) {
                return false;
            }
        }

        return false;
    };

    while (preg_match('/<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $start = $m[0][1];
        $pos = $start + strlen($m[0][0]);
        $close = $m[1][0] === '/';
        $tag = strtolower($m[2][0]);
        $attrs = $m[3][0];
        $label = vsaLabel($tag, $attrs);

        if (! $close) {
            $top = $stack ? $stack[count($stack) - 1]['tag'] : null;
            $hiddenInput = $tag === 'input' && preg_match('/type\s*=\s*"hidden"/i', $attrs);
            if (in_array($top, $tableCtx, true) && ! in_array($tag, $tableAllowed, true) && ! $hiddenInput) {
                $issues[] = ['FOSTER_PARENT', "<{$label}> langsung di dalam <{$top}> — browser memindahkannya ke luar tabel", $path()];
            }
            if (in_array($tag, $closesP, true) && $inScope('p')) {
                $issues[] = ['P_AUTOCLOSE', "<{$label}> di dalam <p> — browser menutup <p> lebih awal", $path()];
            }
            if ($tag === 'form' && in_array('form', array_column($stack, 'tag'), true)) {
                $issues[] = ['NESTED_FORM', "<{$label}> bersarang di dalam <form> lain — form dalam diabaikan browser", $path()];
            }
            if ($tag === 'a' && in_array('a', array_column($stack, 'tag'), true)) {
                $issues[] = ['NESTED_A', "<{$label}> bersarang di dalam <a>", $path()];
            }
            if ($tag === 'button' && $inScope('button')) {
                $issues[] = ['NESTED_BUTTON', "<{$label}> bersarang di dalam <button>", $path()];
            }
            if (str_starts_with($tag, 'x-')) {
                $issues[] = ['LEAKED_COMPONENT', "<{$label}> tidak ter-render sebagai komponen Blade", $path()];
            }
            if (in_array($tag, $void, true) || str_ends_with(rtrim($attrs), '/')) {
                continue;
            }
            if (in_array($tag, $raw, true)) {
                $end = stripos($html, '</'.$tag, $pos);
                $pos = $end === false ? strlen($html) : $end;
                $stack[] = ['tag' => $tag, 'label' => $label];

                continue;
            }
            $stack[] = ['tag' => $tag, 'label' => $label];

            continue;
        }

        if (in_array($tag, $void, true)) {
            continue;
        }
        $idx = null;
        for ($k = count($stack) - 1; $k >= 0; $k--) {
            if ($stack[$k]['tag'] === $tag) {
                $idx = $k;
                break;
            }
        }
        if ($idx === null) {
            $issues[] = ['STRAY_CLOSE', "</{$tag}> tanpa tag pembuka", $path()];

            continue;
        }
        $between = array_slice($stack, $idx + 1);
        $unclosed = array_filter($between, fn ($e) => ! in_array($e['tag'], $optional, true));
        if ($unclosed) {
            $issues[] = ['MISMATCH', "</{$tag}> menutup ".$stack[$idx]['label'].' padahal masih terbuka: '.implode(', ', array_map(fn ($e) => '<'.$e['label'].'>', $unclosed)), $path()];
        }
        array_splice($stack, $idx);
    }

    $left = array_filter($stack, fn ($e) => ! in_array($e['tag'], $optional, true));
    if ($left) {
        $issues[] = ['UNCLOSED', 'Tag tidak ditutup sampai akhir dokumen: '.implode(' > ', array_map(fn ($e) => '<'.$e['label'].'>', $left)), ''];
    }

    return $issues;
}

/** Audit berbasis DOM hasil parse HTML5 (setelah browser-style error recovery). */
function vsaDomAudit(string $html): array
{
    $issues = [];
    $doc = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $xp = new Dom\XPath($doc);

    // Invariant layout: footer & seluruh isi halaman harus berada di dalam .app-page
    $appMain = $doc->querySelector('.app-main-content');
    if ($appMain) {
        foreach ($xp->query('./*', $appMain) as $child) {
            if (! $child instanceof Dom\Element) {
                continue;
            }
            // Modal & script sengaja diletakkan setelah </x-app-page>; posisinya fixed/tidak tampil.
            if ($child->classList->contains('modal') || strtolower($child->tagName) === 'script') {
                continue;
            }
            if (! $child->classList->contains('main-content')) {
                $issues[] = ['LAYOUT_ESCAPE', '<'.vsaElementLabel($child).'> keluar dari .main-content (langsung di .app-main-content)', ''];
            }
        }
        foreach ($doc->querySelectorAll('.main-content') as $mc) {
            foreach ($xp->query('./*', $mc) as $child) {
                if ($child instanceof Dom\Element && ! $child->classList->contains('app-page')) {
                    $issues[] = ['LAYOUT_ESCAPE', '<'.vsaElementLabel($child).'> keluar dari .app-page (langsung di .main-content)', ''];
                }
            }
        }
        foreach ($doc->querySelectorAll('.app-footer') as $footer) {
            if (! $footer->closest('.app-page')) {
                $issues[] = ['LAYOUT_ESCAPE', 'footer.app-footer berada di luar .app-page', ''];
            }
        }
    }

    // Sisa sintaks Blade yang bocor ke teks halaman
    $bladeRe = '/(?<![\w.])@(if|elseif|else|endif|foreach|endforeach|forelse|endforelse|endempty|can|endcan|cannot|endcannot|canany|endcanany|isset|endisset|unless|endunless|php|endphp|csrf|method|error|enderror|section|endsection|push|endpush|include|props|class|selected|checked|disabled|switch|case|endswitch|auth|endauth)\b|\{\{|\}\}|\{!!|!!\}/';
    foreach ($xp->query('//body//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::textarea) and not(ancestor::template)]') as $text) {
        if (preg_match($bladeRe, $text->textContent, $m)) {
            $parent = $text->parentElement;
            $issues[] = ['LEAKED_BLADE', 'Sintaks Blade bocor ke teks: "'.trim(mb_substr(preg_replace('/\s+/', ' ', $text->textContent), 0, 100)).'"', $parent ? vsaElementLabel($parent) : ''];
        }
    }

    $ids = [];
    foreach ($doc->querySelectorAll('[id]') as $el) {
        $ids[$el->id] = ($ids[$el->id] ?? 0) + 1;
    }
    foreach ($ids as $id => $count) {
        if ($count > 1) {
            $issues[] = ['DUPLICATE_ID', "id=\"{$id}\" muncul {$count}x", ''];
        }
    }

    foreach ($doc->getElementsByTagName('*') as $el) {
        $tag = strtolower($el->tagName);
        foreach ($el->attributes as $attr) {
            $name = $attr->name;
            if (preg_match('/-$|[(){}$]|^@/', $name)) {
                $issues[] = ['BAD_ATTR', "<{$tag}> atribut janggal \"{$name}\"", (string) $el->getAttribute('class')];
            }
            if (preg_match('/\{\{|\{!!|(?<![\w.])@(if|endif|else|can|endcan|isset|error)\b/', $attr->value)) {
                $issues[] = ['LEAKED_BLADE', "<{$tag} {$name}> berisi sintaks Blade mentah: ".mb_substr($attr->value, 0, 80), ''];
            }
        }

        $targets = [];
        if ($el->hasAttribute('data-bs-target')) {
            $targets[] = ['data-bs-target', $el->getAttribute('data-bs-target')];
        } elseif ($el->hasAttribute('data-bs-toggle') && str_starts_with((string) $el->getAttribute('href'), '#')) {
            $targets[] = ['href', $el->getAttribute('href')];
        }
        if ($el->hasAttribute('aria-controls')) {
            foreach (preg_split('/\s+/', trim((string) $el->getAttribute('aria-controls'))) as $ref) {
                $targets[] = ['aria-controls', '#'.$ref];
            }
        }
        foreach ($targets as [$attrName, $sel]) {
            if (! str_starts_with($sel, '#') || $sel === '#') {
                continue;
            }
            if (! isset($ids[substr($sel, 1)])) {
                $issues[] = ['BROKEN_TARGET', "<{$tag}> {$attrName}=\"{$sel}\" tidak menunjuk elemen yang ada", (string) $el->getAttribute('class')];
            }
        }
    }

    return $issues;
}

test('audit struktur tampilan semua halaman GET untuk setiap role', function () {
    $this->travelTo('2026-07-21 12:00:00');
    Storage::fake('public');
    Storage::fake('local');
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);

    $rukun = Koperasi::where('nama', 'Koperasi Rukun')->firstOrFail();
    $owner = systemOwnerUser(['name' => 'Owner Audit']);
    $superAdmin = User::where('email', 'admin@example.com')->firstOrFail();
    $adminPrimer = User::where('email', 'admin.primer.rukun@example.com')->firstOrFail();
    $staff = User::where('email', 'staff.keuangan.rukun@example.com')->firstOrFail();

    $hrdRole = new Role(['name' => 'HRD', 'guard_name' => 'web']);
    $hrdRole->koperasi_id = $rukun->id;
    $hrdRole->save();
    $hrdRole->syncPermissions([
        'dashboard.karyawan-aktif.view', 'unit-kerja.view', 'karyawan.view', 'karyawan.create', 'karyawan.update',
        'karyawan.riwayat.view', 'absensi.view', 'absensi.create', 'hari-libur.view', 'laporan.kepegawaian.view',
        'laporan.absensi.view', 'role.view', 'pengguna.view',
    ]);
    $hrd = User::factory()->create(['name' => 'User HRD', 'koperasi_id' => $rukun->id]);
    $hrd->assignRole($hrdRole);

    $emptyRole = new Role(['name' => 'Tanpa Akses', 'guard_name' => 'web']);
    $emptyRole->koperasi_id = $rukun->id;
    $emptyRole->save();
    $noPerm = User::factory()->create(['name' => 'User Tanpa Akses', 'koperasi_id' => $rukun->id]);
    $noPerm->assignRole($emptyRole);

    $staffKaryawan = Karyawan::withoutGlobalScopes()->where('koperasi_id', $rukun->id)->orderBy('id')->firstOrFail();
    $staffKaryawan->forceFill(['user_id' => $staff->id])->saveQuietly();
    $staffSlip = TransaksiGaji::withoutGlobalScopes()->where('karyawan_id', $staffKaryawan->id)->orderByDesc('id')->firstOrFail();
    if ($staffSlip->published_at === null) {
        $staffSlip->forceFill(['published_at' => now()])->saveQuietly();
    }
    $signers = Karyawan::withoutGlobalScopes()->where('koperasi_id', $rukun->id)
        ->whereNull('tanggal_mengundurkan_diri')->orderBy('id')->limit(2)->pluck('id');
    $printQuery = ['dibuat_oleh_id' => $signers[0], 'mengetahui_id' => $signers[1]];
    $routeQuery = [
        'transaksi-gaji.cetak' => $printQuery,
        'transaksi-gaji.cetak-massal' => $printQuery + ['mode' => 'periode', 'bulan' => $staffSlip->bulan, 'tahun' => $staffSlip->tahun],
    ];

    $productRequest = productRequestFor($adminPrimer);
    auth()->logout();
    $announcement = PlatformAnnouncement::create([
        'created_by' => $owner->id, 'target_koperasi_id' => null, 'title' => 'Pengumuman Audit',
        'body' => 'Isi pengumuman audit.', 'priority' => 'warning', 'published_at' => now()->subHour(),
    ]);

    $personas = [
        'system_owner' => [$owner, []],
        'owner_impersonate_admin_primer' => [$adminPrimer, ['impersonated_by' => $owner->id]],
        'super_admin' => [$superAdmin, []],
        'admin_primer' => [$adminPrimer, []],
        'custom_hrd' => [$hrd, []],
        'staff_self_service' => [$staff, []],
        'tanpa_permission' => [$noPerm, []],
    ];

    $skip = '/(download|export|^uploads\.|attachments|^login$|^$)/';
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->getName() && ! preg_match($skip, $r->getName()))
        ->filter(fn ($r) => ! str_starts_with($r->uri(), '_') && ! str_contains($r->getActionName(), 'Laravel\\'));

    $resolveParams = function ($route, User $user) use ($rukun, $productRequest, $announcement, $hrdRole) {
        $name = $route->getName();
        $sets = [[]];
        foreach ($route->parameterNames() as $param) {
            $values = match ($param) {
                'karyawan' => [Karyawan::query()->orderBy('id')->first()],
                'barang' => [Barang::query()->orderBy('id')->first()],
                'komponen_gaji' => [KomponenGaji::query()->orderBy('id')->first()],
                'transaksi_gaji', 'transaksiGaji' => str_starts_with($name, 'me.')
                    ? [TransaksiGaji::query()->whereNotNull('published_at')->whereHas('karyawan', fn ($q) => $q->where('user_id', $user->id))->orderBy('id')->first()]
                    : [TransaksiGaji::query()->orderBy('id')->first()],
                'koperasi' => [$user->koperasi ?? $rukun],
                'tahun' => [2026],
                'role' => [
                    Role::query()->where('koperasi_id', $rukun->id)->where('name', 'admin_primer')->first(),
                    $hrdRole,
                ],
                'pengguna' => [User::query()->where('koperasi_id', $user->koperasi_id ?? $rukun->id)->where('id', '!=', $user->id)->orderBy('id')->first()],
                'productRequest' => [$productRequest->ticket_number],
                'announcement' => [$announcement],
                default => [null],
            };
            $next = [];
            foreach ($sets as $set) {
                foreach ($values as $value) {
                    $next[] = $value === null ? null : $set + [$param => $value];
                }
            }
            $sets = array_values(array_filter($next, fn ($s) => $s !== null));
        }

        return $sets;
    };

    $results = [];
    $issuesBySig = [];
    $firstUnit = UnitKerja::withoutGlobalScopes()->where('koperasi_id', $rukun->id)->value('id');

    foreach ($personas as $persona => [$user, $session]) {
        foreach ($routes as $route) {
            $name = $route->getName();
            $this->actingAs($user);
            $paramSets = $resolveParams($route, $user);
            if ($route->parameterNames() && ! $paramSets) {
                $results[] = [$persona, $name, 'base', 'NO_PARAM', null];

                continue;
            }
            foreach ($paramSets as $params) {
                $variants = ['base' => [$routeQuery[$name] ?? [], null]];
                if (isset($routeQuery[$name])) {
                    foreach (SlipGajiPaperLayout::VALUES as $layout) {
                        $variants['kertas-'.$layout] = [$routeQuery[$name] + ['paper_layout' => $layout], null];
                    }
                }
                if (str_ends_with($name, '.index') || $name === 'absensi.index') {
                    $variants['kosong'] = [['search' => 'zzzz-tidak-ada-zzzz'], null];
                }
                if (str_starts_with($name, 'laporan.') && $firstUnit) {
                    $variants['filter'] = [['unit_kerja_id' => $firstUnit], null];
                }
                if (preg_match('/\.(create|edit)$|^profile\.show$|^me\.profile$/', $name)) {
                    $variants['error'] = [[], (new ViewErrorBag)->put('default', new VsaAllErrorsBag)];
                }
                foreach ($variants as $variant => [$query, $errors]) {
                    $this->flushSession();
                    $this->actingAs($user);
                    $sessionData = $session;
                    if ($errors) {
                        $sessionData['errors'] = $errors;
                    }
                    $url = route($name, $params + $query);
                    $label = $name.($params ? ' ['.implode(',', array_map(fn ($v) => is_object($v) ? class_basename($v).'#'.$v->getKey() : $v, $params)).']' : '').' '.$variant;
                    $response = $this->withSession($sessionData)->get($url);
                    $status = $response->getStatusCode();
                    $type = (string) $response->headers->get('Content-Type');
                    if ($status >= 500) {
                        $results[] = [$persona, $label, $variant, $status, $response->exception?->getMessage()];

                        continue;
                    }
                    if ($status !== 200 || ! str_contains($type, 'text/html')) {
                        $results[] = [$persona, $label, $variant, $status, null];

                        continue;
                    }
                    $html = $response->getContent();
                    $issues = array_merge(vsaTokenAudit($html), vsaDomAudit($html));
                    $results[] = [$persona, $label, $variant, 200, count($issues)];
                    foreach ($issues as [$kind, $msg, $ctx]) {
                        $sig = $kind.' | '.$name.' | '.$msg.' | '.$ctx;
                        $issuesBySig[$sig] ??= ['kind' => $kind, 'route' => $name, 'msg' => $msg, 'ctx' => $ctx, 'where' => []];
                        $issuesBySig[$sig]['where'][] = $persona.' :: '.$label;
                    }
                }
            }
        }
    }

    $report = [
        'rendered' => count(array_filter($results, fn ($r) => $r[3] === 200)),
        'server_errors' => array_values(array_filter($results, fn ($r) => is_int($r[3]) && $r[3] >= 500)),
        'issues' => array_values($issuesBySig),
        'status_matrix' => $results,
    ];
    if (getenv('VIEW_AUDIT_REPORT')) {
        file_put_contents(getenv('VIEW_AUDIT_REPORT'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $describe = fn (array $i) => "[{$i['kind']}] {$i['route']}: {$i['msg']} ({$i['where'][0]})";

    expect($report['rendered'])->toBeGreaterThan(400)
        ->and(array_map(fn ($e) => "{$e[0]} :: {$e[1]} -> {$e[4]}", $report['server_errors']))->toBe([])
        ->and(array_map($describe, $report['issues']))->toBe([]);
});
