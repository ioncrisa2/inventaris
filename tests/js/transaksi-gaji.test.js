import { beforeEach, describe, expect, it } from 'vitest';
import '../../resources/js/pages/transaksi-gaji';

describe('form transaksi gaji', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <table><tbody>
                <tr data-salary-row>
                    <td><input type="checkbox" data-salary-row-toggle></td>
                    <td>
                        <div data-salary-list data-next-index="1">
                            <button type="button" data-salary-list-add>Tambah</button>
                            <div data-salary-list-rows>
                                <div data-salary-list-row>
                                    <input name="baris[master_1][rincian][0][keterangan]">
                                    <button type="button" data-salary-list-remove>Hapus</button>
                                </div>
                            </div>
                            <p data-salary-list-empty class="d-none">Kosong</p>
                            <template data-salary-list-template>
                                <div data-salary-list-row>
                                    <input name="baris[master_1][rincian][__INDEX__][keterangan]">
                                    <input name="baris[master_1][rincian][__INDEX__][nominal]">
                                    <button type="button" data-salary-list-remove>Hapus</button>
                                </div>
                            </template>
                        </div>
                    </td>
                </tr>
            </tbody></table>`;

        document.dispatchEvent(new Event('DOMContentLoaded'));
    });

    it('mengaktifkan kontrol saat komponen dicentang lalu menambah dan menghapus rincian', () => {
        const toggle = document.querySelector('[data-salary-row-toggle]');
        const addButton = document.querySelector('[data-salary-list-add]');

        expect(addButton.disabled).toBe(true);

        toggle.checked = true;
        toggle.dispatchEvent(new Event('change'));
        expect(addButton.disabled).toBe(false);

        addButton.click();

        const rows = document.querySelectorAll('[data-salary-list-row]');
        expect(rows).toHaveLength(2);
        expect(rows[1].querySelector('input').name).toBe('baris[master_1][rincian][1][keterangan]');

        rows[0].querySelector('[data-salary-list-remove]').click();
        expect(document.querySelectorAll('[data-salary-list-row]')).toHaveLength(1);

        document.querySelector('[data-salary-list-remove]').click();
        expect(document.querySelectorAll('[data-salary-list-row]')).toHaveLength(0);
        expect(document.querySelector('[data-salary-list-empty]').classList.contains('d-none')).toBe(false);
    });
});
