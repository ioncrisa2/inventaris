@php
    $transaksi = $slip['transaksi'];
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $warna = [
        'black' => '#111111',
        'dark' => '#3f3f46',
        'medium' => '#71717a',
        'light' => '#a1a1aa',
    ];
@endphp

<article class="salary-slip {{ $slip['is_full_page'] ? 'salary-slip--full-page' : '' }}">
    @foreach($templateConfiguration['blocks'] as $block)
        @php
            $blockStyle = implode(';', [
                'width:'.$block['width'].'%',
                'font-family:'.str_replace([';', '"', "'"], '', $block['font_family']),
                'font-size:'.$block['font_size'].'pt',
                'font-weight:'.$block['font_weight'],
                'color:'.($warna[$block['color']] ?? $warna['black']),
                'text-align:'.$block['text_align'],
                'margin-top:'.$block['spacing_before'].'mm',
                'margin-bottom:'.$block['spacing_after'].'mm',
                $block['align'] === 'center' ? 'margin-left:auto;margin-right:auto' : '',
                $block['align'] === 'right' ? 'margin-left:auto' : '',
            ]);
        @endphp

        <section
            class="salary-slip-block salary-slip-block--{{ $block['type'] }}"
            style="{{ $blockStyle }}"
        >
            @switch($block['type'])
                @case('logo')
                    @include('transaksi-gaji.slip-blocks.logo')
                    @break
                @case('organization')
                    @include('transaksi-gaji.slip-blocks.organization')
                    @break
                @case('title')
                    @include('transaksi-gaji.slip-blocks.title')
                    @break
                @case('employee')
                    @include('transaksi-gaji.slip-blocks.employee')
                    @break
                @case('payroll')
                    @include('transaksi-gaji.slip-blocks.payroll')
                    @break
                @case('signatures')
                    @include('transaksi-gaji.slip-blocks.signatures')
                    @break
                @case('footer')
                    @include('transaksi-gaji.slip-blocks.footer')
                    @break
                @case('text')
                    @include('transaksi-gaji.slip-blocks.text')
                    @break
                @case('line')
                    @include('transaksi-gaji.slip-blocks.line')
                    @break
                @case('box')
                    @include('transaksi-gaji.slip-blocks.box')
                    @break
            @endswitch
        </section>
    @endforeach
</article>
