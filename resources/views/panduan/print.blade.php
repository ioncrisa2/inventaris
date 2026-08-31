@extends('layouts.print')

@section('title', $guide['title'].' - PHS Sumsel')
@section('print_layout', 'a4-portrait')
@section('back_url', route('panduan-singkat'))

@section('content')
<style>
    .guide-print { color: #1f2937; font: 10.5px/1.5 Arial, Helvetica, sans-serif; }
    .guide-print__header { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #1e3a8a; text-align: center; }
    .guide-print__org { margin-bottom: 3px; color: #1e3a8a; font-size: 14px; font-weight: 800; text-transform: uppercase; }
    .guide-print__header h1 { margin: 0 0 3px; font-size: 16px; }
    .guide-print__header p { margin: 0; color: #4b5563; }
    .guide-print__meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 18px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; }
    .guide-print__meta strong { color: #111827; }
    .guide-section { margin: 0 0 18px; }
    .guide-section__header { margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #cbd5e1; }
    .guide-section__header h2 { margin: 0 0 2px; color: #1e3a8a; font-size: 12px; }
    .guide-section__header p { margin: 0; color: #64748b; }
    .quick-guide-list { margin: 0; padding-left: 24px; }
    .quick-guide-list > li { margin-bottom: 10px; padding-left: 4px; break-inside: avoid; }
    .quick-guide-list h3 { margin: 0 0 2px; color: #1f2937; font-size: 11px; }
    .quick-guide-list p { margin: 0 0 3px; color: #475569; }
    .quick-guide-points { margin: 3px 0 0; padding-left: 16px; color: #475569; }
    .quick-guide-points li { margin-bottom: 2px; }
    .role-access-notice { margin-top: 16px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; break-inside: avoid; }
    .role-access-notice__heading { display: flex; align-items: center; gap: 5px; margin: 0 0 5px; color: #1f2937; font-size: 11px; font-weight: 700; }
    .role-access-notice__heading h2 { margin: 0; font: inherit; }
    .role-access-notice__content { padding: 0; }
    .role-access-notice__content ul { margin: 0; padding-left: 16px; }
    .quick-guide-footer { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-top: 24px; padding-top: 8px; border-top: 1px solid #cbd5e1; color: #64748b; font-size: 9px; }
    .quick-guide-footer__credit { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 3px 8px; text-align: right; }
    .quick-guide-footer__item { display: inline-flex; gap: 8px; }
    .quick-guide-footer__credit a { color: inherit; text-decoration: none; }
</style>

<article class="guide-print">
    <header class="guide-print__header">
        <div class="guide-print__org">Puskopdit Handriya Sanggraha Sumatera Selatan</div>
        <h1>{{ $guide['title'] }}</h1>
        <p>{{ $guide['subtitle'] }}</p>
    </header>

    <div class="guide-print__meta">
        <span><strong>Sasaran:</strong> {{ $guide['audience'] }}</span>
        <span><strong>Lingkup:</strong> {{ $guide['scope'] }}</span>
        <span><strong>Tanggal:</strong> {{ now()->translatedFormat('d F Y') }}</span>
    </div>

    @php($isPrint = true)
    @include($guide['content_view'])

    @include('panduan.partials.footer')
</article>
@endsection
