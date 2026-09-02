import { beforeEach, describe, expect, it } from 'vitest';
import '../../resources/js/pages/komponen-gaji';

describe('form komponen gaji', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div data-component-salary-form>
                <select data-component-calculation-method>
                    <option value="nominal_tetap" selected>Nominal Tetap</option>
                    <option value="nominal_tidak_tetap">Nominal Tidak Tetap</option>
                    <option value="nominal_tetap_list">Nominal Tetap - List</option>
                </select>
                <div data-component-default-value>
                    <span data-component-default-prefix></span>
                    <input data-component-default-input>
                    <span data-component-default-suffix></span>
                    <span data-component-default-help></span>
                </div>
                <div data-component-transaction-note class="d-none">
                    <span data-component-transaction-note-text></span>
                </div>
                <div data-component-fixed-list class="d-none" data-next-index="1">
                    <button type="button" data-component-fixed-list-add>Tambah</button>
                    <div data-component-fixed-list-rows>
                        <div data-component-fixed-list-row>
                            <input name="rincian[0][keterangan]">
                            <input name="rincian[0][nominal]">
                            <button type="button" data-component-fixed-list-remove>Hapus</button>
                        </div>
                    </div>
                    <p data-component-fixed-list-empty class="d-none">Kosong</p>
                    <template data-component-fixed-list-template>
                        <div data-component-fixed-list-row>
                            <input name="rincian[__INDEX__][keterangan]">
                            <input name="rincian[__INDEX__][nominal]">
                            <button type="button" data-component-fixed-list-remove>Hapus</button>
                        </div>
                    </template>
                </div>
            </div>`;

        document.dispatchEvent(new Event('DOMContentLoaded'));
    });

    it('menampilkan repeater untuk nominal tetap list lalu menambah dan menghapus rincian', () => {
        const method = document.querySelector('[data-component-calculation-method]');
        const fixedList = document.querySelector('[data-component-fixed-list]');
        const defaultValue = document.querySelector('[data-component-default-value]');

        method.value = 'nominal_tetap_list';
        method.dispatchEvent(new Event('change'));

        expect(fixedList.classList.contains('d-none')).toBe(false);
        expect(defaultValue.classList.contains('d-none')).toBe(true);
        expect(fixedList.querySelector('input').disabled).toBe(false);
        expect(fixedList.querySelector('input').required).toBe(true);

        fixedList.querySelector('[data-component-fixed-list-add]').click();

        const rows = fixedList.querySelectorAll('[data-component-fixed-list-row]');
        expect(rows).toHaveLength(2);
        expect(rows[1].querySelector('input').name).toBe('rincian[1][keterangan]');

        rows[0].querySelector('[data-component-fixed-list-remove]').click();
        fixedList.querySelector('[data-component-fixed-list-remove]').click();

        expect(fixedList.querySelectorAll('[data-component-fixed-list-row]')).toHaveLength(0);
        expect(fixedList.querySelector('[data-component-fixed-list-empty]').classList.contains('d-none')).toBe(false);
    });

    it('menonaktifkan field daftar ketika metode diganti', () => {
        const method = document.querySelector('[data-component-calculation-method]');
        const fixedList = document.querySelector('[data-component-fixed-list]');

        method.value = 'nominal_tetap_list';
        method.dispatchEvent(new Event('change'));
        method.value = 'nominal_tidak_tetap';
        method.dispatchEvent(new Event('change'));

        expect(fixedList.classList.contains('d-none')).toBe(true);
        expect(fixedList.querySelector('input').disabled).toBe(true);
        expect(document.querySelector('[data-component-transaction-note]').classList.contains('d-none')).toBe(false);
    });
});
