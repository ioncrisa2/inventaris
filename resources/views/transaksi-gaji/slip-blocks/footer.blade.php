<footer class="salary-slip__footer">
    <span>TG-{{ str_pad($transaksi->id, 6, '0', STR_PAD_LEFT) }}</span>
    <span>Dicetak {{ $printedAt->translatedFormat('d F Y H:i') }}</span>
</footer>
