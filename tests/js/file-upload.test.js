import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { FileUpload, formatBytes } from '../../resources/js/components/file-upload';

const policy = {
    name: 'product_attachments',
    extensions: ['pdf', 'txt'],
    mimeByExtension: {
        pdf: ['application/pdf'],
        txt: ['text/plain'],
    },
    maxFiles: 3,
    maxFileBytes: 10 * 1024 * 1024,
    maxTotalBytes: 20 * 1024 * 1024,
    maxImageDimension: 8000,
    maxImageMegapixels: 40,
    preview: true,
    camera: false,
    cropAspectRatio: null,
    clientMaxDimension: null,
    txtPreviewBytes: 204800,
};

const makeRoot = (policyOverrides = {}, required = false) => {
    document.body.innerHTML = `
        <form>
            <div data-file-upload>
                <div data-file-picker>
                    <input type="file" name="attachments[]" data-file-upload-input multiple ${required ? 'required' : ''}>
                    <span data-file-picker-status></span>
                </div>
                <div data-file-upload-summary hidden></div>
                <div data-file-upload-list></div>
            </div>
            <button type="submit">Simpan</button>
        </form>`;
    const root = document.querySelector('[data-file-upload]');
    root.dataset.uploadPolicy = JSON.stringify({ ...policy, ...policyOverrides });
    return root;
};

class FakeXMLHttpRequest {
    static instances = [];

    constructor() {
        this.upload = new EventTarget();
        this.events = new EventTarget();
        this.status = 0;
        this.response = null;
        FakeXMLHttpRequest.instances.push(this);
    }

    open(method, url) {
        this.method = method;
        this.url = url;
    }

    setRequestHeader() {}

    addEventListener(type, callback) {
        this.events.addEventListener(type, callback);
    }

    send(body) {
        this.body = body;
    }

    respond(status, payload) {
        this.status = status;
        this.response = payload;
        this.events.dispatchEvent(new Event('load'));
    }

    abort() {
        this.events.dispatchEvent(new Event('abort'));
    }
}

describe('file upload', () => {
    beforeEach(() => {
        FakeXMLHttpRequest.instances = [];
        vi.stubGlobal('DataTransfer', undefined);
        vi.stubGlobal('URL', {
            createObjectURL: vi.fn(() => 'blob:test'),
            revokeObjectURL: vi.fn(),
        });
        window.HTMLElement.prototype.scrollIntoView = vi.fn();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.replaceChildren();
    });

    it('memformat ukuran untuk pembaca Indonesia', () => {
        expect(formatBytes(0)).toBe('0 B');
        expect(formatBytes(1536)).toBe('1,5 KB');
        expect(formatBytes(10 * 1024 * 1024)).toBe('10 MB');
    });

    it('menampilkan file valid lengkap dengan ukuran dan status', async () => {
        const upload = new FileUpload(makeRoot());
        await upload.receiveFiles([new File(['halo'], 'catatan.txt', { type: 'text/plain' })]);

        expect(upload.root.dataset.uploadState).toBe('selected');
        expect(upload.root.querySelector('[data-file-picker-status]').textContent).toContain('1 file');
        expect(upload.root.querySelector('.file-upload__name').textContent).toBe('catatan.txt');
        expect(upload.root.querySelector('.file-upload__validation').textContent).toContain('Siap diunggah');
    });

    it('menolak ekstensi yang tidak didukung dengan pesan inline', async () => {
        const upload = new FileUpload(makeRoot());
        await upload.receiveFiles([new File(['x'], 'program.exe', { type: 'application/x-msdownload' })]);

        expect(upload.root.dataset.uploadState).toBe('invalid');
        expect(upload.input.validationMessage).toContain('Periksa kembali');
        expect(upload.root.querySelector('.file-upload__validation').textContent).toContain('tidak didukung');
    });

    it('menerapkan batas jumlah dan dapat menghapus item', async () => {
        const upload = new FileUpload(makeRoot());
        const files = [1, 2, 3, 4].map((number) => new File(['x'], `${number}.txt`, { type: 'text/plain' }));
        await upload.receiveFiles(files);

        expect(upload.hasErrors()).toBe(true);
        expect(upload.root.querySelectorAll('.file-upload__item')).toHaveLength(4);

        upload.remove(3);

        expect(upload.hasErrors()).toBe(false);
        expect(upload.root.querySelectorAll('.file-upload__item')).toHaveLength(3);
    });

    it('mengirim upload XHR, menampilkan progres, dan membentuk token UUID', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
        const upload = new FileUpload(makeRoot({
            asyncEnabled: true,
            asyncStoreUrl: '/uploads',
            asyncStatusUrl: '/uploads/__UUID__',
            asyncDeleteUrl: '/uploads/__UUID__',
            maxParallelUploads: 3,
        }, true));
        const pending = upload.receiveFiles([new File(['halo'], 'catatan.txt', { type: 'text/plain' })]);
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(1));

        const xhr = FakeXMLHttpRequest.instances[0];
        xhr.upload.dispatchEvent(new ProgressEvent('progress', {
            lengthComputable: true,
            loaded: 5,
            total: 10,
        }));
        expect(upload.root.querySelector('[data-upload-progress]').getAttribute('aria-valuenow')).toBe('50');

        xhr.respond(202, {
            uuid: '4f7da8ea-08b8-42da-98ba-f1b1f9e19966',
            status: 'ready',
            scan_status: 'clean',
        });
        await pending;

        const token = upload.root.querySelector('[data-upload-token]');
        expect(token.name).toBe('attachments_upload_uuids[]');
        expect(token.value).toBe('4f7da8ea-08b8-42da-98ba-f1b1f9e19966');
        expect(upload.input.required).toBe(false);
        expect(upload.root.dataset.uploadState).toBe('success');
    });

    it('membatalkan staging server dan membersihkan token ketika item dihapus', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
        const fetch = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', fetch);
        const upload = new FileUpload(makeRoot({
            asyncEnabled: true,
            asyncStoreUrl: '/uploads',
            asyncStatusUrl: '/uploads/__UUID__',
            asyncDeleteUrl: '/uploads/__UUID__',
        }, true));
        const pending = upload.receiveFiles([new File(['halo'], 'catatan.txt', { type: 'text/plain' })]);
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(1));
        FakeXMLHttpRequest.instances[0].respond(202, {
            uuid: '5b8b85c7-c32f-49cc-a48a-7ec139825eb3',
            status: 'pending_scan',
        });
        await pending;

        await upload.remove(0);

        expect(fetch).toHaveBeenCalledWith('/uploads/5b8b85c7-c32f-49cc-a48a-7ec139825eb3', expect.objectContaining({ method: 'DELETE' }));
        expect(upload.root.querySelector('[data-upload-token]')).toBeNull();
        expect(upload.input.required).toBe(true);
        expect(upload.root.dataset.uploadState).toBe('idle');
        upload.dispose();
    });

    it('menawarkan retry setelah XHR gagal dan membersihkan object URL saat dispose', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
        const upload = new FileUpload(makeRoot({
            asyncEnabled: true,
            asyncStoreUrl: '/uploads',
            asyncStatusUrl: '/uploads/__UUID__',
            asyncDeleteUrl: '/uploads/__UUID__',
        }));
        const pending = upload.receiveFiles([new File(['halo'], 'catatan.txt', { type: 'text/plain' })]);
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(1));
        FakeXMLHttpRequest.instances[0].respond(500, { message: 'Server sibuk.' });
        await pending;

        expect(upload.root.dataset.uploadState).toBe('failed');
        const retry = [...upload.root.querySelectorAll('button')].find((button) => button.textContent.includes('Coba lagi'));
        retry.click();
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(2));
        FakeXMLHttpRequest.instances[1].respond(202, {
            uuid: 'b07381c5-5283-4d95-a80b-145fac790e4e',
            status: 'ready',
        });
        await vi.waitFor(() => expect(upload.root.dataset.uploadState).toBe('success'));

        upload.objectUrls.add('blob:manual');
        upload.dispose();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:manual');
    });

    it('menolak file ketika total ukuran melampaui batas koleksi', async () => {
        // maxTotalBytes = 500 KB; kirim dua file 300 KB masing-masing
        const upload = new FileUpload(makeRoot({ maxTotalBytes: 500 * 1024 }));
        const big1 = new File([new ArrayBuffer(300 * 1024)], 'besar1.txt', { type: 'text/plain' });
        const big2 = new File([new ArrayBuffer(300 * 1024)], 'besar2.txt', { type: 'text/plain' });
        await upload.receiveFiles([big1, big2]);

        expect(upload.hasErrors()).toBe(true);
        expect(upload.root.dataset.uploadState).toBe('invalid');
        // Setidaknya satu item harus menampilkan pesan tentang batas total
        const validations = [...upload.root.querySelectorAll('.file-upload__validation')].map((el) => el.textContent);
        expect(validations.some((msg) => /total|melebihi|batas/i.test(msg))).toBe(true);
    });

    it('menolak MIME yang tidak sesuai ekstensi dengan pesan jelas', async () => {
        const upload = new FileUpload(makeRoot());
        // File bernama .pdf tapi type dideklarasikan text/plain (mismatch)
        const misleading = new File(['%PDF-fake'], 'dokumen.pdf', { type: 'text/plain' });
        await upload.receiveFiles([misleading]);

        expect(upload.root.dataset.uploadState).toBe('invalid');
        const msg = upload.root.querySelector('.file-upload__validation').textContent;
        expect(msg).toMatch(/tidak sesuai|tipe file|ekstensi/i);
    });

    it('polling memperbarui status dari pending_scan lalu ready', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
        vi.useFakeTimers();

        const pollResponses = [
            { uuid: 'poll-uuid', status: 'pending_scan', scan_status: 'pending' },
            { uuid: 'poll-uuid', status: 'processing', scan_status: 'clean' },
            { uuid: 'poll-uuid', status: 'ready', scan_status: 'clean' },
        ];
        let callIdx = 0;
        vi.stubGlobal('fetch', vi.fn(() => {
            const payload = pollResponses[Math.min(callIdx++, pollResponses.length - 1)];
            return Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
        }));

        const upload = new FileUpload(makeRoot({
            asyncEnabled: true,
            asyncStoreUrl: '/uploads',
            asyncStatusUrl: '/uploads/__UUID__',
            asyncDeleteUrl: '/uploads/__UUID__',
        }));
        const pending = upload.receiveFiles([new File(['x'], 'nota.txt', { type: 'text/plain' })]);
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(1));
        FakeXMLHttpRequest.instances[0].respond(202, {
            uuid: 'poll-uuid',
            status: 'pending_scan',
            scan_status: 'pending',
        });
        await pending;

        // Majukan timer polling (2 detik, lalu 4 detik, lalu 8 detik)
        await vi.advanceTimersByTimeAsync(2500);
        await vi.advanceTimersByTimeAsync(5000);
        await vi.advanceTimersByTimeAsync(10000);

        // Tunggu sampai state berubah ke success
        await vi.waitFor(() => expect(upload.root.dataset.uploadState).toBe('success'));
        expect(callIdx).toBeGreaterThanOrEqual(1);

        vi.useRealTimers();
        upload.dispose();
    });

    it('memblokir pengiriman form ketika ada upload sedang berlangsung', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest);
        const root = makeRoot({
            asyncEnabled: true,
            asyncStoreUrl: '/uploads',
            asyncStatusUrl: '/uploads/__UUID__',
            asyncDeleteUrl: '/uploads/__UUID__',
        }, true);
        const upload = new FileUpload(root);
        upload.receiveFiles([new File(['x'], 'nota.txt', { type: 'text/plain' })]);
        await vi.waitFor(() => expect(FakeXMLHttpRequest.instances).toHaveLength(1));

        // Saat upload berlangsung, submit form harus dicegah
        const form = root.closest('form');
        const event = new Event('submit', { cancelable: true, bubbles: true });
        form.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);

        // Selesaikan upload → submit sekarang harus diizinkan
        FakeXMLHttpRequest.instances[0].respond(202, {
            uuid: 'done-uuid',
            status: 'ready',
            scan_status: 'not_required',
        });
        await vi.waitFor(() => expect(upload.root.dataset.uploadState).toBe('success'));
        const event2 = new Event('submit', { cancelable: true, bubbles: true });
        form.dispatchEvent(event2);
        expect(event2.defaultPrevented).toBe(false);
    });

    it('menampilkan daftar multi-file dan ringkasan total ukuran', async () => {
        const upload = new FileUpload(makeRoot({ maxFiles: 3, maxTotalBytes: 20 * 1024 * 1024 }));
        const files = ['laporan-a.txt', 'laporan-b.txt'].map(
            (name) => new File(['konten singkat'], name, { type: 'text/plain' }),
        );
        await upload.receiveFiles(files);

        expect(upload.root.querySelectorAll('.file-upload__item')).toHaveLength(2);
        // Ringkasan total harus terlihat
        const summary = upload.root.querySelector('[data-file-upload-summary]');
        expect(summary.hidden).toBe(false);
        expect(summary.textContent).toContain('2');
    });

    it('kembali ke state idle setelah semua file dihapus', async () => {
        const upload = new FileUpload(makeRoot());
        await upload.receiveFiles([new File(['x'], 'catatan.txt', { type: 'text/plain' })]);
        expect(upload.root.dataset.uploadState).toBe('selected');

        await upload.remove(0);

        expect(upload.root.dataset.uploadState).toBe('idle');
        expect(upload.root.querySelectorAll('.file-upload__item')).toHaveLength(0);
    });
});
