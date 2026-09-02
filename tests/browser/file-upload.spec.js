import { expect, test } from '@playwright/test';

const harness = '/tests/browser/fixtures/file-upload.html';
const readyUuid = 'b07381c5-5283-4d95-a80b-145fac790e4e';

const installFakeUploadTransport = async (page) => {
    await page.addInitScript(({ uuid }) => {
        window.__uploadOutcomes = [];
        window.__uploadRequests = [];

        class FakeXMLHttpRequest {
            constructor() {
                this.upload = new EventTarget();
                this.events = new EventTarget();
                this.status = 0;
                this.response = null;
                this.responseType = '';
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
                window.__uploadRequests.push({ method: this.method, url: this.url, body });
                const outcome = window.__uploadOutcomes.shift() || 'success';
                window.setTimeout(() => {
                    this.upload.dispatchEvent(new ProgressEvent('progress', {
                        lengthComputable: true,
                        loaded: 4,
                        total: 10,
                    }));
                }, 25);
                if (outcome === 'pending') return;
                window.setTimeout(() => {
                    this.status = outcome === 'failure' ? 500 : 202;
                    this.response = outcome === 'failure'
                        ? { message: 'Server sibuk.' }
                        : { uuid, status: 'ready', scan_status: 'clean' };
                    this.events.dispatchEvent(new Event('load'));
                }, 150);
            }

            abort() {
                this.events.dispatchEvent(new Event('abort'));
            }
        }

        window.XMLHttpRequest = FakeXMLHttpRequest;
    }, { uuid: readyUuid });
};

test('preview TXT dapat dibuka dengan keyboard dan modal menjaga fokus', async ({ page }) => {
    await page.goto(harness);
    await page.locator('#document-input').setInputFiles({
        name: 'catatan.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('<script>tidak boleh dieksekusi</script>'),
    });

    const previewButton = page.getByRole('button', { name: 'Preview' }).first();
    await previewButton.focus();
    await page.keyboard.press('Enter');

    const modal = page.locator('#fileUploadPreviewModal');
    await expect(modal).toBeVisible();
    await expect(modal.getByText('<script>tidak boleh dieksekusi</script>')).toBeVisible();
    await expect(modal.locator('script')).toHaveCount(0);

    await expect.poll(() => page.evaluate(() => (
        document.querySelector('#fileUploadPreviewModal').contains(document.activeElement)
    ))).toBe(true);
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.querySelector('#fileUploadPreviewModal').contains(document.activeElement))).toBe(true);
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
});

test('upload asinkron menampilkan progres lalu dapat retry hingga menghasilkan token', async ({ page }) => {
    await installFakeUploadTransport(page);
    await page.goto(harness);
    await page.evaluate(() => { window.__uploadOutcomes = ['failure', 'success']; });

    await page.locator('#async-input').setInputFiles({
        name: 'lampiran.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('isi lampiran'),
    });

    const asyncRoot = page.locator('#async-input').locator('xpath=ancestor::*[@data-file-upload]');
    await expect(asyncRoot.locator('[data-upload-progress]')).toHaveAttribute('aria-valuenow', '40');
    await expect(asyncRoot.getByRole('button', { name: 'Coba lagi' })).toBeVisible();
    await asyncRoot.getByRole('button', { name: 'Coba lagi' }).click();

    await expect(asyncRoot.locator('[data-upload-token]')).toHaveValue(readyUuid);
    await expect(asyncRoot).toHaveAttribute('data-upload-state', 'success');
});

test('upload berjalan dapat dibatalkan dan layout tetap muat di layar ponsel', async ({ page }) => {
    await installFakeUploadTransport(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(harness);
    await page.evaluate(() => { window.__uploadOutcomes = ['pending']; });

    await page.locator('#async-input').setInputFiles({
        name: 'batal.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('batalkan saya'),
    });

    const asyncRoot = page.locator('#async-input').locator('xpath=ancestor::*[@data-file-upload]');
    await expect(asyncRoot.locator('[data-upload-progress]')).toHaveAttribute('aria-valuenow', '40');
    await asyncRoot.getByRole('button', { name: 'Batal' }).click();
    await expect(asyncRoot.locator('[role="listitem"]')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('kamera hanya dimulai setelah tombol ditekan dan memakai constraint yang ditetapkan', async ({ page }) => {
    await page.addInitScript(() => {
        window.__cameraCalls = [];
        window.__cameraTracksStopped = 0;
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: async (constraints) => {
                    window.__cameraCalls.push(constraints);
                    const stream = document.createElement('canvas').captureStream(1);
                    const track = stream.getVideoTracks()[0];
                    const nativeStop = track.stop.bind(track);
                    track.stop = () => {
                        window.__cameraTracksStopped += 1;
                        nativeStop();
                    };
                    return stream;
                },
                enumerateDevices: async () => [{
                    deviceId: 'usb-camera',
                    kind: 'videoinput',
                    label: 'USB Camera',
                    groupId: 'test',
                }],
            },
        });
        HTMLMediaElement.prototype.play = async () => {};
    });
    await page.goto(harness);

    const cameraRoot = page.locator('#camera-input').locator('xpath=ancestor::*[@data-file-upload]');
    const cameraButton = cameraRoot.getByRole('button', { name: 'Ambil foto' });
    await expect(cameraButton).toBeVisible();
    expect(await page.evaluate(() => window.__cameraCalls.length)).toBe(0);
    await cameraButton.click();

    await expect(page.locator('#fileUploadCameraModal')).toBeVisible();
    await expect(page.locator('[data-camera-device]')).toHaveValue('usb-camera');
    const constraints = await page.evaluate(() => window.__cameraCalls[0]);
    expect(constraints.audio).toBe(false);
    expect(constraints.video.width.ideal).toBe(1920);
    expect(constraints.video.height.ideal).toBe(1080);
    await expect.poll(() => page.evaluate(() => (
        document.querySelector('#fileUploadCameraModal').contains(document.activeElement)
    ))).toBe(true);
    await page.keyboard.press('Escape');
    await expect(page.locator('#fileUploadCameraModal')).toBeHidden();
    await expect.poll(() => page.evaluate(() => window.__cameraTracksStopped)).toBe(1);
});

test('foto karyawan selalu membuka crop persegi sebelum masuk daftar', async ({ page }) => {
    await page.goto(harness);
    await page.locator('#employee-input').setInputFiles({
        name: 'karyawan.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR4nGP8z8DAwMDAxMDAwMAAAAwBAQDJ/pLvAAAAAElFTkSuQmCC',
            'base64',
        ),
    });

    const cropModal = page.locator('#fileUploadCropModal');
    await expect(cropModal).toBeVisible();
    await expect(cropModal.getByRole('heading', { name: 'Atur foto karyawan' })).toBeVisible();
    await cropModal.getByRole('button', { name: 'Batal' }).click();
    await expect(cropModal).toBeHidden();
    await expect(page.locator('#employee-input').locator('xpath=ancestor::*[@data-file-upload]').locator('[role="listitem"]')).toHaveCount(0);
});
