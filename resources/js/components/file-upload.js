import { Modal } from 'bootstrap';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const instances = new WeakMap();
const imageDimensions = new WeakMap();
let activeCameraUpload = null;
let cameraStream = null;
let activeCropper = null;
let cropResolution = null;
let previewObjectUrl = null;
let activeAsyncUploads = 0;
const asyncUploadQueue = [];

const runQueuedUploads = () => {
    const maximum = Math.max(1, Number(document.documentElement.dataset.maxParallelUploads || 3));
    while (activeAsyncUploads < maximum && asyncUploadQueue.length) {
        const task = asyncUploadQueue.shift();
        activeAsyncUploads += 1;
        task.run().then(task.resolve, task.reject).finally(() => {
            activeAsyncUploads -= 1;
            runQueuedUploads();
        });
    }
};

const enqueueUpload = (run, maximum = 3) => new Promise((resolve, reject) => {
    document.documentElement.dataset.maxParallelUploads = String(maximum);
    asyncUploadQueue.push({ run, resolve, reject });
    runQueuedUploads();
});

export const formatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB'];
    const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / (1024 ** unitIndex);

    return `${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: unitIndex === 0 ? 0 : 1,
    }).format(value)} ${units[unitIndex]}`;
};

const extensionOf = (name) => name.includes('.') ? name.split('.').pop().toLowerCase() : '';
const isImage = (file) => file.type.startsWith('image/');
const canTransferFiles = () => typeof DataTransfer !== 'undefined';

const loadImage = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
        URL.revokeObjectURL(url);
        resolve(image);
    };
    image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Gambar rusak atau tidak dapat dibaca.'));
    };
    image.src = url;
});

const canvasToBlob = (canvas, preferredType = 'image/webp', quality = 0.84) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
        if (blob) {
            resolve(blob);
            return;
        }

        canvas.toBlob((jpegBlob) => {
            if (jpegBlob) resolve(jpegBlob);
            else reject(new Error('Browser tidak dapat mengolah gambar ini.'));
        }, 'image/jpeg', quality);
    }, preferredType, quality);
});

const compressedName = (name, mime) => {
    const base = name.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9._-]+/g, '-');
    return `${base || 'foto'}.${mime === 'image/webp' ? 'webp' : 'jpg'}`;
};

export const compressImage = async (file, maxDimension) => {
    if (!maxDimension || !isImage(file)) return file;

    const image = await loadImage(file);
    const longestSide = Math.max(image.naturalWidth, image.naturalHeight);
    imageDimensions.set(file, { width: image.naturalWidth, height: image.naturalHeight });

    if (longestSide <= maxDimension) return file;

    const scale = maxDimension / longestSide;
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
    const context = canvas.getContext('2d');

    if (!context) throw new Error('Browser tidak menyediakan Canvas untuk optimasi gambar.');

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const blob = await canvasToBlob(canvas);
    const optimized = new File([blob], compressedName(file.name, blob.type), {
        type: blob.type,
        lastModified: Date.now(),
    });
    imageDimensions.set(optimized, { width: canvas.width, height: canvas.height });

    return optimized;
};

const ensurePreviewModal = () => {
    let element = document.getElementById('fileUploadPreviewModal');
    if (element) return element;

    element = document.createElement('div');
    element.className = 'modal fade';
    element.id = 'fileUploadPreviewModal';
    element.tabIndex = -1;
    element.setAttribute('aria-labelledby', 'fileUploadPreviewTitle');
    element.setAttribute('aria-hidden', 'true');
    element.innerHTML = `
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="fileUploadPreviewTitle">Preview file</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body file-upload-preview" data-file-upload-preview-body></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(element);
    element.addEventListener('hidden.bs.modal', () => {
        const body = element.querySelector('[data-file-upload-preview-body]');
        if (body) body.replaceChildren();
        if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
    });

    return element;
};

const ensureCameraModal = () => {
    let element = document.getElementById('fileUploadCameraModal');
    if (element) return element;

    element = document.createElement('div');
    element.className = 'modal fade';
    element.id = 'fileUploadCameraModal';
    element.tabIndex = -1;
    element.setAttribute('aria-labelledby', 'fileUploadCameraTitle');
    element.setAttribute('aria-hidden', 'true');
    element.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="fileUploadCameraTitle">Ambil foto</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="fileUploadCameraDevice">Pilih kamera</label>
                    <select class="form-select mb-3" id="fileUploadCameraDevice" data-camera-device></select>
                    <div class="file-upload-camera__viewport">
                        <video data-camera-video autoplay playsinline muted></video>
                    </div>
                    <p class="text-danger small mt-2 mb-0" data-camera-error role="alert" hidden></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" data-camera-capture>
                        <i class="bi bi-camera" aria-hidden="true"></i> Gunakan foto
                    </button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(element);

    const select = element.querySelector('[data-camera-device]');
    select.addEventListener('change', () => startCamera(select.value));
    element.querySelector('[data-camera-capture]').addEventListener('click', captureCameraFrame);
    element.addEventListener('hidden.bs.modal', stopCamera);

    return element;
};

const stopCamera = () => {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    activeCameraUpload = null;
};

const startCamera = async (deviceId = '') => {
    const modal = ensureCameraModal();
    const error = modal.querySelector('[data-camera-error]');
    const video = modal.querySelector('[data-camera-video]');
    const select = modal.querySelector('[data-camera-device]');

    error.hidden = true;
    cameraStream?.getTracks().forEach((track) => track.stop());

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } }),
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
        });
        video.srcObject = cameraStream;
        await video.play();

        const devices = (await navigator.mediaDevices.enumerateDevices())
            .filter((device) => device.kind === 'videoinput');
        const activeDeviceId = cameraStream.getVideoTracks()[0]?.getSettings().deviceId;
        select.replaceChildren(...devices.map((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Kamera ${index + 1}`;
            option.selected = device.deviceId === activeDeviceId;
            return option;
        }));
        select.closest('.mb-3')?.removeAttribute('hidden');
    } catch (exception) {
        error.textContent = exception?.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Anda tetap dapat memilih foto dari perangkat.'
            : 'Kamera tidak dapat digunakan. Periksa sambungan kamera lalu coba lagi.';
        error.hidden = false;
        activeCameraUpload?.disableCamera();
    }
};

const captureCameraFrame = async () => {
    const modal = ensureCameraModal();
    const video = modal.querySelector('[data-camera-video]');
    if (!activeCameraUpload || !video.videoWidth || !video.videoHeight) return;

    const target = activeCameraUpload;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d')?.drawImage(video, 0, 0);

    try {
        const blob = await canvasToBlob(canvas, 'image/jpeg', 0.92);
        const file = new File([blob], `foto-kamera-${Date.now()}.jpg`, {
            type: blob.type,
            lastModified: Date.now(),
        });
        Modal.getOrCreateInstance(modal).hide();
        await target.receiveFiles([file]);
    } catch {
        target.showGlobalError('Foto dari kamera tidak dapat diproses. Silakan pilih file dari perangkat.');
    }
};

const ensureCropModal = () => {
    let element = document.getElementById('fileUploadCropModal');
    if (element) return element;

    element = document.createElement('div');
    element.className = 'modal fade';
    element.id = 'fileUploadCropModal';
    element.tabIndex = -1;
    element.setAttribute('aria-labelledby', 'fileUploadCropTitle');
    element.setAttribute('aria-hidden', 'true');
    element.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="fileUploadCropTitle">Atur foto karyawan</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-body-secondary small">Geser dan perbesar foto agar wajah berada di dalam bidang persegi.</p>
                    <div class="file-upload-crop__viewport"><img data-crop-image alt="Foto yang akan dipotong"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" data-crop-apply>Gunakan foto</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(element);
    element.querySelector('[data-crop-apply]').addEventListener('click', async () => {
        if (!activeCropper || !cropResolution) return;

        const { resolve, sourceName } = cropResolution;
        try {
            const canvas = activeCropper.getCroppedCanvas({
                width: 1280,
                height: 1280,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            const blob = await canvasToBlob(canvas, 'image/webp', 0.84);
            const file = new File([blob], compressedName(sourceName, blob.type), {
                type: blob.type,
                lastModified: Date.now(),
            });
            imageDimensions.set(file, { width: canvas.width, height: canvas.height });
            cropResolution = null;
            resolve(file);
            Modal.getOrCreateInstance(element).hide();
        } catch {
            cropResolution = null;
            resolve(null);
            Modal.getOrCreateInstance(element).hide();
        }
    });
    element.addEventListener('hidden.bs.modal', () => {
        activeCropper?.destroy();
        activeCropper = null;
        if (cropResolution) cropResolution.resolve(null);
        cropResolution = null;
        const image = element.querySelector('[data-crop-image]');
        if (image.dataset.objectUrl) URL.revokeObjectURL(image.dataset.objectUrl);
        image.removeAttribute('src');
        delete image.dataset.objectUrl;
    });

    return element;
};

const cropEmployeePhoto = (file) => new Promise((resolve) => {
    const modal = ensureCropModal();
    const image = modal.querySelector('[data-crop-image]');
    const url = URL.createObjectURL(file);
    image.src = url;
    image.dataset.objectUrl = url;
    cropResolution = { resolve, sourceName: file.name };

    image.onload = () => {
        activeCropper = new Cropper(image, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            background: false,
            responsive: true,
        });
    };
    image.onerror = () => {
        cropResolution = null;
        URL.revokeObjectURL(url);
        delete image.dataset.objectUrl;
        resolve(null);
        Modal.getOrCreateInstance(modal).hide();
    };
    Modal.getOrCreateInstance(modal).show();
});

export class FileUpload {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-file-upload-input]');
        this.picker = root.querySelector('[data-file-picker]');
        this.status = root.querySelector('[data-file-picker-status]');
        this.summary = root.querySelector('[data-file-upload-summary]');
        this.list = root.querySelector('[data-file-upload-list]');
        this.cameraButton = root.querySelector('[data-file-upload-camera]');
        this.policy = JSON.parse(root.dataset.uploadPolicy || '{}');
        this.items = [];
        this.objectUrls = new Set();
        this.replacingIndex = null;
        this.processing = false;
        this.originalRequired = this.input?.required || false;
        this.pollTimers = new Set();
        instances.set(root, this);

        this.input?.addEventListener('change', () => this.onInputChange());
        this.cameraButton?.addEventListener('click', () => this.openCamera());
        this.form = root.closest('form');
        this.bindForm();
        this.updateCameraAvailability();
        this.setState('idle');
    }

    bindForm() {
        if (!this.form || this.form.dataset.fileUploadBound === 'true') return;

        this.form.dataset.fileUploadBound = 'true';
        this.form.addEventListener('submit', (event) => {
            const uploads = [...this.form.querySelectorAll('[data-file-upload]')]
                .map((root) => instances.get(root))
                .filter(Boolean);

            if (uploads.some((upload) => upload.blocksSubmit())) {
                event.preventDefault();
                uploads.find((upload) => upload.blocksSubmit())?.root.scrollIntoView({ block: 'center' });
                return;
            }

            if (this.form.dataset.uploadSubmitting === 'true') {
                event.preventDefault();
                return;
            }

            this.form.dataset.uploadSubmitting = 'true';
            uploads.forEach((upload) => upload.setState('submitting'));
            this.form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Mengirim…';
            });
        });
    }

    updateCameraAvailability() {
        if (!this.cameraButton) return;
        const available = window.isSecureContext
            && canTransferFiles()
            && Boolean(navigator.mediaDevices?.getUserMedia)
            && Boolean(navigator.mediaDevices?.enumerateDevices);
        this.cameraButton.hidden = !available;
    }

    disableCamera() {
        if (this.cameraButton) this.cameraButton.hidden = true;
    }

    async openCamera() {
        activeCameraUpload = this;
        const modal = ensureCameraModal();
        Modal.getOrCreateInstance(modal).show();
        await startCamera();
    }

    async onInputChange() {
        const selected = [...(this.input.files || [])];
        if (!selected.length) return;

        if (this.replacingIndex !== null) {
            const files = this.items.map((item) => item.file);
            files.splice(this.replacingIndex, 1, selected[0]);
            this.replacingIndex = null;
            await this.receiveFiles(files);
            return;
        }

        await this.receiveFiles(selected);
    }

    async receiveFiles(files) {
        const previousItems = [...this.items];
        this.processing = true;
        this.setState('selected');
        this.status.textContent = 'Memeriksa dan menyiapkan file…';

        try {
            let prepared = [...files];
            if (this.policy.cropAspectRatio === 1 && prepared[0] && !this.basicValidationError(prepared[0])) {
                const cropped = await cropEmployeePhoto(prepared[0]);
                if (!cropped) {
                    this.syncInput(this.items.filter((item) => !item.error).map((item) => item.file));
                    this.render();
                    return;
                }
                prepared = [cropped];
            } else if (this.policy.clientMaxDimension) {
                prepared = await Promise.all(prepared.map(async (file) => {
                    if (!isImage(file)) return file;
                    try {
                        return await compressImage(file, this.policy.clientMaxDimension);
                    } catch {
                        return file;
                    }
                }));
            }

            const nextItems = await Promise.all(prepared.map(async (file) => (
                previousItems.find((item) => item.file === file) || this.inspectFile(file)
            )));
            const discarded = previousItems.filter((item) => !nextItems.includes(item));
            if (this.policy.asyncEnabled) {
                await Promise.all(discarded.map((item) => this.cancelAsyncItem(item)));
            }
            this.items = nextItems;
            this.applyCollectionValidation();
            this.syncInput(this.items.map((item) => item.file));
            this.render();
            if (this.policy.asyncEnabled && !this.hasErrors()) {
                await Promise.all(this.items.map((item) => (
                    item.uuid ? Promise.resolve() : this.uploadItem(item)
                )));
                this.syncAsyncTokens();
                this.render();
            }
        } finally {
            this.processing = false;
        }
    }

    async inspectFile(file) {
        let error = this.basicValidationError(file);
        let dimensions = imageDimensions.get(file) || null;

        if (!error && isImage(file)) {
            try {
                if (!dimensions) {
                    const image = await loadImage(file);
                    dimensions = { width: image.naturalWidth, height: image.naturalHeight };
                    imageDimensions.set(file, dimensions);
                }
                if (dimensions.width > this.policy.maxImageDimension || dimensions.height > this.policy.maxImageDimension) {
                    error = `Dimensi gambar maksimal ${this.policy.maxImageDimension} piksel pada setiap sisi.`;
                } else if ((dimensions.width * dimensions.height) > (this.policy.maxImageMegapixels * 1_000_000)) {
                    error = `Resolusi gambar maksimal ${this.policy.maxImageMegapixels} megapiksel.`;
                }
            } catch (exception) {
                error = exception.message;
            }
        }

        return {
            file,
            error,
            dimensions,
            status: 'selected',
            progress: 0,
            uuid: null,
            xhr: null,
            canceled: false,
        };
    }

    basicValidationError(file) {
        const extension = extensionOf(file.name);
        if (!this.policy.extensions.includes(extension)) {
            return `Format .${extension || '?'} tidak didukung.`;
        }
        const allowedMimes = this.policy.mimeByExtension[extension] || [];
        if (file.type && !allowedMimes.includes(file.type.toLowerCase())) {
            return 'Ekstensi tidak sesuai dengan tipe file.';
        }
        if (file.size > this.policy.maxFileBytes) {
            return `Ukuran file maksimal ${formatBytes(this.policy.maxFileBytes)}.`;
        }
        return '';
    }

    applyCollectionValidation() {
        const total = this.items.reduce((sum, item) => sum + item.file.size, 0);
        if (this.items.length > this.policy.maxFiles) {
            const message = `Maksimal ${this.policy.maxFiles} file dalam satu pilihan.`;
            this.items.slice(this.policy.maxFiles).forEach((item) => { item.error ||= message; });
        }
        if (total > this.policy.maxTotalBytes) {
            const message = `Total ukuran file maksimal ${formatBytes(this.policy.maxTotalBytes)}.`;
            this.items.forEach((item) => { item.error ||= message; });
        }
    }

    syncInput(files) {
        if (!canTransferFiles()) return;
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        this.input.files = transfer.files;
    }

    async uploadItem(item) {
        if (item.error || item.uuid || !this.policy.asyncEnabled) return;
        item.canceled = false;
        item.status = 'uploading';
        item.progress = 0;
        this.render();

        try {
            const payload = await enqueueUpload(
                () => {
                    if (item.canceled || !this.items.includes(item)) {
                        return Promise.reject(new DOMException('Upload dibatalkan.', 'AbortError'));
                    }

                    return this.sendUpload(item);
                },
                this.policy.maxParallelUploads || 3,
            );
            if (item.canceled || !this.items.includes(item)) return;
            item.uuid = payload.uuid;
            item.status = payload.status;
            item.progress = 100;
            item.error = '';
            this.syncAsyncTokens();
            if (!['ready', 'failed', 'infected', 'canceled'].includes(item.status)) {
                this.schedulePoll(item, 2000);
            }
        } catch (exception) {
            if (exception?.name === 'AbortError') {
                item.status = 'canceled';
                item.error = 'Upload dibatalkan.';
            } else {
                item.status = 'failed';
                item.error = exception?.message || 'Upload gagal. Silakan coba lagi.';
            }
        } finally {
            item.xhr = null;
            this.render();
        }
    }

    sendUpload(item) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            item.xhr = xhr;
            xhr.open('POST', this.policy.asyncStoreUrl, true);
            xhr.responseType = 'json';
            xhr.setRequestHeader('Accept', 'application/json');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) return;
                const progress = Math.min(99, Math.round((event.loaded / event.total) * 100));
                if (progress !== item.progress) {
                    item.progress = progress;
                    this.updateItemProgress(item);
                }
            });
            xhr.addEventListener('load', () => {
                const payload = xhr.response || {};
                if (xhr.status >= 200 && xhr.status < 300 && payload.uuid) {
                    resolve(payload);
                    return;
                }
                const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                reject(new Error(validation || payload.message || 'Upload gagal diproses server.'));
            });
            xhr.addEventListener('error', () => reject(new Error('Koneksi upload terputus.')));
            xhr.addEventListener('abort', () => reject(new DOMException('Upload dibatalkan.', 'AbortError')));

            const body = new FormData();
            body.append('policy', this.policy.name);
            body.append('file', item.file, item.file.name);
            if (this.policy.targetTenantId) body.append('koperasi_id', String(this.policy.targetTenantId));
            xhr.send(body);
        });
    }

    updateItemProgress(item) {
        const index = this.items.indexOf(item);
        const row = index >= 0 ? this.list.children[index] : null;
        const progress = row?.querySelector('[data-upload-progress]');
        if (progress) {
            progress.style.width = `${item.progress}%`;
            progress.setAttribute('aria-valuenow', String(item.progress));
        }
        const validation = row?.querySelector('.file-upload__validation');
        if (validation) validation.textContent = `Mengunggah ${item.progress}%`;
    }

    syncAsyncTokens() {
        this.root.querySelectorAll('[data-upload-token]').forEach((element) => element.remove());
        const tokenName = this.tokenFieldName();
        this.items.filter((item) => item.uuid).forEach((item) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = tokenName;
            hidden.value = item.uuid;
            hidden.dataset.uploadToken = '';
            this.root.appendChild(hidden);
        });

        const allAccepted = this.items.length > 0 && this.items.every((item) => Boolean(item.uuid));
        if (this.policy.asyncEnabled && this.items.length > 0) {
            this.input.value = '';
        }
        this.input.required = this.originalRequired && !allAccepted;
    }

    tokenFieldName() {
        const name = this.input.name;
        if (name.endsWith('[]')) return `${name.slice(0, -2)}_upload_uuids[]`;
        const nested = name.match(/^(.*)\[([^\]]+)]$/);
        if (nested) return `${nested[1]}[${nested[2]}_upload_uuid]`;

        return `${name}_upload_uuid`;
    }

    schedulePoll(item, delay) {
        const timer = window.setTimeout(async () => {
            this.pollTimers.delete(timer);
            if (!this.items.includes(item) || !item.uuid) return;
            if (document.hidden) {
                this.schedulePoll(item, delay);
                return;
            }

            try {
                const url = this.policy.asyncStatusUrl.replace('__UUID__', encodeURIComponent(item.uuid));
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Status upload tidak dapat dibaca.');
                const payload = await response.json();
                item.status = payload.status;
                item.error = ['failed', 'infected'].includes(payload.status)
                    ? this.statusLabel(payload.status, payload.failure_code)
                    : '';
                this.render();
                if (!['ready', 'failed', 'infected', 'canceled'].includes(item.status)) {
                    this.schedulePoll(item, Math.min(10000, Math.round(delay * 1.5)));
                }
            } catch {
                this.schedulePoll(item, Math.min(10000, Math.round(delay * 1.5)));
            }
        }, delay);
        this.pollTimers.add(timer);
    }

    statusLabel(status, failureCode = null) {
        const labels = {
            selected: 'Siap diunggah',
            uploading: `Mengunggah…`,
            pending_scan: 'Menunggu pemindaian antivirus',
            processing: 'Sedang diproses',
            ready: 'Siap digunakan',
            infected: 'File ditolak karena terdeteksi berbahaya',
            failed: 'Pemrosesan file gagal',
            canceled: 'Upload dibatalkan',
        };
        if (failureCode === 'scanner_unavailable') return 'Antivirus belum tersedia; file tetap terkunci dan akan dicoba lagi.';

        return labels[status] || 'Menunggu pemrosesan';
    }

    async cancelAsyncItem(item) {
        item.canceled = true;
        item.xhr?.abort();
        if (!item.uuid) return;
        try {
            const url = this.policy.asyncDeleteUrl.replace('__UUID__', encodeURIComponent(item.uuid));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
            });
        } catch {
            // Cleanup server tetap menangani staging kedaluwarsa.
        }
    }

    hasErrors() {
        return this.items.some((item) => Boolean(item.error));
    }

    blocksSubmit() {
        if (this.hasErrors() || this.processing) return true;
        if (!this.policy.asyncEnabled) return false;

        return this.items.some((item) => !item.uuid || ['selected', 'uploading', 'failed', 'infected', 'canceled'].includes(item.status));
    }

    setState(state) {
        this.root.dataset.uploadState = state;
        this.root.setAttribute('aria-busy', ['submitting', 'uploading', 'pending_scan', 'processing'].includes(state) ? 'true' : 'false');
    }

    showGlobalError(message) {
        this.input.setCustomValidity(message);
        this.picker.classList.add('is-invalid');
        this.status.textContent = message;
        this.setState('failed');
    }

    render() {
        this.revokeObjectUrls();
        this.list.replaceChildren();
        const total = this.items.reduce((sum, item) => sum + item.file.size, 0);
        const hasErrors = this.hasErrors();

        this.input.setCustomValidity(hasErrors ? 'Periksa kembali file yang dipilih.' : '');
        this.picker.classList.toggle('is-invalid', hasErrors);
        const activeStatus = this.items.find((item) => ['uploading', 'pending_scan', 'processing'].includes(item.status))?.status;
        const allReady = this.items.length > 0 && this.items.every((item) => item.status === 'ready');
        const failedStatus = this.items.find((item) => ['infected', 'failed', 'canceled'].includes(item.status))?.status;
        this.setState(failedStatus || (hasErrors ? 'invalid' : activeStatus || (allReady ? 'success' : this.items.length ? 'selected' : 'idle')));
        this.status.textContent = this.items.length
            ? `${this.items.length} file · ${hasErrors ? 'perlu diperiksa' : activeStatus ? this.statusLabel(activeStatus) : allReady ? 'siap digunakan' : 'dipilih'}`
            : 'Belum ada file dipilih';
        this.summary.hidden = this.items.length < 2;
        this.summary.textContent = this.items.length
            ? `${this.items.length} file · ${formatBytes(total)} dari ${formatBytes(this.policy.maxTotalBytes)}`
            : '';

        this.items.forEach((item, index) => this.list.appendChild(this.renderItem(item, index)));
    }

    renderItem(item, index) {
        const row = document.createElement('div');
        row.className = `file-upload__item${item.error ? ' is-invalid' : ''}`;
        row.setAttribute('role', 'listitem');

        const visual = document.createElement('div');
        visual.className = 'file-upload__visual';
        if (isImage(item.file)) {
            const url = URL.createObjectURL(item.file);
            this.objectUrls.add(url);
            const image = document.createElement('img');
            image.src = url;
            image.alt = '';
            visual.appendChild(image);
        } else {
            const icon = document.createElement('i');
            icon.className = `bi ${this.iconFor(item.file)}`;
            icon.setAttribute('aria-hidden', 'true');
            visual.appendChild(icon);
        }

        const info = document.createElement('div');
        info.className = 'file-upload__info';
        const name = document.createElement('div');
        name.className = 'file-upload__name';
        name.textContent = item.file.name;
        name.title = item.file.name;
        const meta = document.createElement('div');
        meta.className = 'file-upload__meta';
        const dimensionText = item.dimensions ? ` · ${item.dimensions.width} × ${item.dimensions.height}` : '';
        meta.textContent = `${formatBytes(item.file.size)} · ${item.file.type || extensionOf(item.file.name).toUpperCase()}${dimensionText}`;
        const validation = document.createElement('div');
        const successful = !item.error && ['selected', 'ready'].includes(item.status);
        validation.className = item.error ? 'file-upload__validation text-danger' : `file-upload__validation ${successful ? 'text-success' : 'text-body-secondary'}`;
        validation.setAttribute('role', item.error ? 'alert' : 'status');
        validation.innerHTML = `<i class="bi ${item.error ? 'bi-exclamation-circle' : successful ? 'bi-check-circle' : 'bi-hourglass-split'}" aria-hidden="true"></i> `;
        validation.append(document.createTextNode(item.error || this.statusLabel(item.status)));
        info.append(name, meta, validation);
        if (item.status === 'uploading') {
            const progress = document.createElement('div');
            progress.className = 'progress mt-2';
            progress.style.height = '0.35rem';
            progress.innerHTML = `<div class="progress-bar" data-upload-progress role="progressbar" style="width:${item.progress}%" aria-label="Progres upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${item.progress}"></div>`;
            info.appendChild(progress);
        }

        const actions = document.createElement('div');
        actions.className = 'file-upload__item-actions';
        if (this.policy.preview) actions.appendChild(this.actionButton('Preview', 'bi-eye', () => this.preview(item)));
        if (item.status === 'failed') {
            actions.appendChild(this.actionButton('Coba lagi', 'bi-arrow-clockwise', async () => {
                await this.cancelAsyncItem(item);
                item.uuid = null;
                item.error = '';
                item.canceled = false;
                this.uploadItem(item);
            }));
        } else if (!['uploading', 'pending_scan', 'processing'].includes(item.status)) {
            actions.appendChild(this.actionButton('Ganti', 'bi-arrow-repeat', () => this.replace(index)));
        }
        if (['uploading', 'pending_scan', 'processing'].includes(item.status)) {
            actions.appendChild(this.actionButton('Batal', 'bi-x-circle', () => this.remove(index), true));
        } else {
            actions.appendChild(this.actionButton('Hapus', 'bi-trash', () => this.remove(index), true));
        }

        row.append(visual, info, actions);
        return row;
    }

    actionButton(label, iconName, handler, danger = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn btn-sm btn-link file-upload__action${danger ? ' text-danger' : ''}`;
        button.innerHTML = `<i class="bi ${iconName}" aria-hidden="true"></i><span>${label}</span>`;
        button.addEventListener('click', handler);
        return button;
    }

    iconFor(file) {
        const extension = extensionOf(file.name);
        if (extension === 'pdf') return 'bi-file-earmark-pdf';
        if (['xlsx', 'xls', 'csv'].includes(extension)) return 'bi-file-earmark-spreadsheet';
        if (extension === 'txt') return 'bi-file-earmark-text';
        return 'bi-file-earmark';
    }

    async preview(item) {
        const modal = ensurePreviewModal();
        const title = modal.querySelector('#fileUploadPreviewTitle');
        const body = modal.querySelector('[data-file-upload-preview-body]');
        if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
        title.textContent = item.file.name;
        body.replaceChildren();

        if (isImage(item.file)) {
            previewObjectUrl = URL.createObjectURL(item.file);
            const image = document.createElement('img');
            image.src = previewObjectUrl;
            image.alt = `Preview ${item.file.name}`;
            image.className = 'file-upload-preview__image';
            body.appendChild(image);
        } else if (item.file.type === 'application/pdf') {
            previewObjectUrl = URL.createObjectURL(item.file);
            const iframe = document.createElement('iframe');
            iframe.src = previewObjectUrl;
            iframe.title = `Preview ${item.file.name}`;
            iframe.className = 'file-upload-preview__document';
            body.appendChild(iframe);
        } else if (extensionOf(item.file.name) === 'txt') {
            const pre = document.createElement('pre');
            pre.className = 'file-upload-preview__text';
            const slice = item.file.slice(0, this.policy.txtPreviewBytes);
            pre.textContent = await slice.text();
            body.appendChild(pre);
            if (item.file.size > this.policy.txtPreviewBytes) {
                const notice = document.createElement('p');
                notice.className = 'form-text';
                notice.textContent = 'Preview dipotong karena isi file terlalu panjang.';
                body.appendChild(notice);
            }
        } else {
            const metadata = document.createElement('dl');
            metadata.className = 'row mb-0';
            [['Nama', item.file.name], ['Tipe', item.file.type || 'Tidak diketahui'], ['Ukuran', formatBytes(item.file.size)]]
                .forEach(([term, value]) => {
                    const dt = document.createElement('dt');
                    dt.className = 'col-sm-3';
                    dt.textContent = term;
                    const dd = document.createElement('dd');
                    dd.className = 'col-sm-9';
                    dd.textContent = value;
                    metadata.append(dt, dd);
                });
            body.appendChild(metadata);
        }

        Modal.getOrCreateInstance(modal).show();
    }

    replace(index) {
        this.replacingIndex = index;
        this.input.click();
    }

    async remove(index) {
        const [item] = this.items.splice(index, 1);
        if (item && this.policy.asyncEnabled) await this.cancelAsyncItem(item);
        this.syncInput(this.items.map((item) => item.file));
        this.syncAsyncTokens();
        this.render();
    }

    revokeObjectUrls() {
        this.objectUrls.forEach((url) => URL.revokeObjectURL(url));
        this.objectUrls.clear();
    }

    dispose() {
        this.revokeObjectUrls();
        this.pollTimers.forEach((timer) => window.clearTimeout(timer));
        this.pollTimers.clear();
        this.items.forEach((item) => {
            item.canceled = true;
            item.xhr?.abort();
        });
        if (activeCameraUpload === this) stopCamera();
        instances.delete(this.root);
    }
}

export const initFileUploads = (scope = document) => {
    const roots = [];
    if (scope instanceof Element && scope.matches('[data-file-upload]')) roots.push(scope);
    roots.push(...scope.querySelectorAll('[data-file-upload]'));
    roots.forEach((root) => {
        if (instances.has(root)) return;
        instances.set(root, new FileUpload(root));
    });
};

document.addEventListener('DOMContentLoaded', () => initFileUploads());
document.addEventListener('file-upload:added', (event) => initFileUploads(event.target));
document.addEventListener('file-upload:dispose', (event) => {
    event.target.querySelectorAll('[data-file-upload]').forEach((root) => instances.get(root)?.dispose());
});
window.addEventListener('pagehide', () => {
    document.querySelectorAll('[data-file-upload]').forEach((root) => instances.get(root)?.dispose());
    if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
    previewObjectUrl = null;
    stopCamera();
});
