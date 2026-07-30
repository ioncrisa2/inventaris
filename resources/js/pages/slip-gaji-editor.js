const REQUIRED_TYPES = new Set(['employee', 'payroll', 'signatures']);
const SINGLETON_TYPES = new Set(['logo', 'organization', 'title', 'employee', 'payroll', 'signatures', 'footer']);
const BLOCK_LABELS = {
    logo: 'Logo',
    organization: 'Identitas koperasi',
    title: 'Judul & periode',
    employee: 'Identitas karyawan',
    payroll: 'Data gaji',
    signatures: 'Penanda tangan',
    footer: 'Tanggal cetak',
    text: 'Teks statis',
    line: 'Garis',
    box: 'Kotak',
};
const VARIANTS = {
    payroll: [
        ['stacked', 'Tunjangan lalu potongan'],
        ['columns', 'Dua kolom'],
    ],
    signatures: [
        ['three_columns', 'Tiga kolom'],
        ['two_plus_one', 'Dua atas, satu bawah'],
    ],
    line: [
        ['solid', 'Garis penuh'],
        ['dashed', 'Garis putus-putus'],
    ],
    box: [
        ['solid', 'Kotak garis penuh'],
        ['dashed', 'Kotak garis putus-putus'],
    ],
};
const COLOR_VALUES = {
    black: '#111111',
    dark: '#3f3f46',
    medium: '#71717a',
    light: '#a1a1aa',
};
const PAPER_LAYOUTS = new Set(['left_right', 'top_bottom']);

const clone = (value) => JSON.parse(JSON.stringify(value));
const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const makeId = (type) => `${type}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;

const makeBlock = (type) => ({
    id: makeId(type),
    type,
    width: type === 'logo' ? 25 : 100,
    align: type === 'footer' ? 'right' : 'center',
    text_align: ['employee', 'payroll'].includes(type) ? 'left' : 'center',
    font_family: 'Arial',
    font_size: type === 'title' ? 13 : (type === 'footer' ? 6 : 8),
    font_weight: ['organization', 'title'].includes(type) ? 700 : 400,
    color: 'black',
    spacing_before: 0,
    spacing_after: 1,
    variant: type === 'payroll'
        ? 'stacked'
        : (type === 'signatures' ? 'three_columns' : (['line', 'box'].includes(type) ? 'solid' : 'default')),
    content: type === 'text' ? 'Teks tambahan' : '',
    height: 8,
});

const sampleContent = (block) => {
    if (block.type === 'logo') {
        return '<div class="slip-sample-logo"><i class="bi bi-building" aria-hidden="true"></i><span>LOGO</span></div>';
    }

    if (block.type === 'organization') {
        return '<strong class="slip-sample-organization">KOPERASI SEJAHTERA BERSAMA</strong><small>Jl. Contoh No. 10, Palembang</small>';
    }

    if (block.type === 'title') {
        return '<strong class="slip-sample-title">SLIP GAJI</strong><small>Juli 2026</small>';
    }

    if (block.type === 'employee') {
        return `
            <div class="slip-sample-meta">
                <span>Nama</span><strong>Andreas Suwandi</strong>
                <span>NIK</span><strong>EMP-001</strong>
                <span>Unit / Jabatan</span><strong>Keuangan · Staf</strong>
            </div>`;
    }

    if (block.type === 'payroll') {
        return `
            <div class="slip-sample-payroll slip-sample-payroll--${escapeHtml(block.variant)}">
                <div class="slip-sample-payroll__base"><span>Gaji Pokok</span><strong>Rp 5.000.000</strong></div>
                <section>
                    <h4>Tunjangan</h4>
                    <div><span>Tunjangan Jabatan</span><strong>Rp 500.000</strong></div>
                    <div><span>Uang Makan (20 hari)</span><strong>Rp 600.000</strong></div>
                </section>
                <section>
                    <h4>Potongan</h4>
                    <div><span>BPJS Kesehatan</span><strong>Rp 200.000</strong></div>
                </section>
                <div class="slip-sample-payroll__net"><span>Penerimaan Akhir</span><strong>Rp 5.900.000</strong></div>
            </div>`;
    }

    if (block.type === 'signatures') {
        return `
            <div class="slip-sample-signatures slip-sample-signatures--${escapeHtml(block.variant)}">
                <div><span>Dibuat oleh,</span><b>Siti Pembuat</b></div>
                <div><span>Diterima oleh,</span><b>Andreas Suwandi</b></div>
                <div><span>Mengetahui,</span><b>Rina Mengetahui</b></div>
            </div>`;
    }

    if (block.type === 'footer') {
        return '<small>TG-000001 · Dicetak 30 Juli 2026 10:00</small>';
    }

    if (block.type === 'text') {
        return `<p class="slip-sample-static-text">${escapeHtml(block.content || 'Teks tambahan')}</p>`;
    }

    if (block.type === 'line') {
        return `<hr class="slip-sample-line slip-sample-line--${escapeHtml(block.variant)}">`;
    }

    return `<div class="slip-sample-box slip-sample-box--${escapeHtml(block.variant)}" style="height:${Number(block.height) || 8}mm">${escapeHtml(block.content)}</div>`;
};

document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-slip-template-editor]');
    if (!editor) return;

    const configurationInput = editor.querySelector('[data-slip-template-configuration]');
    const defaultInput = editor.querySelector('[data-default-slip-configuration]');
    const canvas = editor.querySelector('[data-slip-editor-canvas]');
    const form = editor.querySelector('[data-slip-template-form]');
    const propertyFields = editor.querySelector('[data-property-fields]');
    const propertyEmpty = editor.querySelector('[data-property-empty]');
    const propertyHelp = editor.querySelector('[data-property-help]');
    const announcement = editor.querySelector('[data-editor-announcement]');
    const undoButton = editor.querySelector('[data-editor-undo]');
    const redoButton = editor.querySelector('[data-editor-redo]');
    const deleteButton = editor.querySelector('[data-delete-block]');
    const paperLayoutDefault = editor.querySelector('[data-paper-layout-default]');
    const paperLayoutPreview = document.querySelector('[data-paper-layout-preview]');
    const pagePreview = document.querySelector('[data-slip-page-preview]');
    const slotDimensions = editor.querySelector('[data-slip-slot-dimensions]');
    const readOnly = editor.dataset.readonly === 'true';

    let configuration;
    let defaultConfiguration;

    try {
        configuration = JSON.parse(configurationInput.value);
        defaultConfiguration = JSON.parse(defaultInput.value);
    } catch {
        return;
    }

    configuration.page ??= {};
    configuration.page.paper_layout = PAPER_LAYOUTS.has(configuration.page.paper_layout)
        ? configuration.page.paper_layout
        : 'left_right';

    let selectedId = configuration.blocks[0]?.id ?? null;
    let draggedId = null;
    let history = [clone(configuration)];
    let historyIndex = 0;

    const selectedBlock = () => configuration.blocks.find((block) => block.id === selectedId);

    const announce = (message) => {
        announcement.textContent = '';
        window.requestAnimationFrame(() => {
            announcement.textContent = message;
        });
    };

    const syncInput = () => {
        configurationInput.value = JSON.stringify(configuration);
    };

    const updateHistoryButtons = () => {
        undoButton.disabled = readOnly || historyIndex === 0;
        redoButton.disabled = readOnly || historyIndex === history.length - 1;
    };

    const pushHistory = () => {
        history = history.slice(0, historyIndex + 1);
        history.push(clone(configuration));
        history = history.slice(-50);
        historyIndex = history.length - 1;
        updateHistoryButtons();
    };

    const blockStyles = (block) => [
        `width:${block.width}%`,
        `font-family:${escapeHtml(block.font_family)}`,
        `font-size:${Number(block.font_size)}pt`,
        `font-weight:${Number(block.font_weight)}`,
        `color:${COLOR_VALUES[block.color] || COLOR_VALUES.black}`,
        `text-align:${block.text_align}`,
        `margin-top:${Number(block.spacing_before)}mm`,
        `margin-bottom:${Number(block.spacing_after)}mm`,
        block.align === 'center' ? 'margin-left:auto;margin-right:auto' : '',
        block.align === 'right' ? 'margin-left:auto' : '',
    ].filter(Boolean).join(';');

    const updatePaperLayoutUi = () => {
        const layout = configuration.page.paper_layout;
        editor.dataset.paperLayout = layout;
        paperLayoutDefault.value = layout;
        slotDimensions.textContent = layout === 'left_right'
            ? 'Slot slip 100 × 320 mm'
            : 'Slot slip 200 × 160 mm';
    };

    const renderCanvas = () => {
        canvas.innerHTML = '';

        configuration.blocks.forEach((block, index) => {
            const element = document.createElement('section');
            element.className = `slip-editor-block ${block.id === selectedId ? 'is-selected' : ''}`;
            element.dataset.blockId = block.id;
            element.dataset.blockType = block.type;
            element.style.cssText = blockStyles(block);
            element.tabIndex = 0;
            element.setAttribute('role', 'group');
            element.setAttribute('aria-label', `${BLOCK_LABELS[block.type]}, urutan ${index + 1} dari ${configuration.blocks.length}`);
            element.draggable = !readOnly;
            element.innerHTML = `
                <button type="button" class="slip-editor-block__handle" aria-label="Pilih dan tarik ${escapeHtml(BLOCK_LABELS[block.type])}" ${readOnly ? 'disabled' : ''}>
                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                    <span>${escapeHtml(BLOCK_LABELS[block.type])}</span>
                </button>
                <div class="slip-editor-block__content">${sampleContent(block)}</div>`;

            element.addEventListener('click', () => select(block.id));
            element.addEventListener('focus', () => select(block.id));
            element.addEventListener('keydown', (event) => {
                if (readOnly) return;

                if (event.altKey && ['ArrowUp', 'ArrowDown'].includes(event.key)) {
                    event.preventDefault();
                    moveBlock(event.key === 'ArrowUp' ? -1 : 1);
                }

                if (event.key === 'Delete' && !REQUIRED_TYPES.has(block.type)) {
                    event.preventDefault();
                    deleteSelected();
                }
            });
            element.addEventListener('dragstart', (event) => {
                draggedId = block.id;
                element.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', block.id);
            });
            element.addEventListener('dragover', (event) => {
                if (!readOnly) event.preventDefault();
            });
            element.addEventListener('drop', (event) => {
                event.preventDefault();
                reorder(draggedId, block.id);
            });
            element.addEventListener('dragend', () => {
                draggedId = null;
                element.classList.remove('is-dragging');
            });

            canvas.append(element);
        });

        updatePalette();
        renderProperties();
        updatePaperLayoutUi();
        syncInput();
    };

    const select = (id) => {
        selectedId = id;
        renderCanvas();
    };

    const mutate = (callback, message) => {
        if (readOnly) return;
        callback();
        pushHistory();
        renderCanvas();
        if (message) announce(message);
    };

    const reorder = (sourceId, targetId) => {
        if (!sourceId || sourceId === targetId) return;

        mutate(() => {
            const sourceIndex = configuration.blocks.findIndex((block) => block.id === sourceId);
            const targetIndex = configuration.blocks.findIndex((block) => block.id === targetId);
            const [block] = configuration.blocks.splice(sourceIndex, 1);
            configuration.blocks.splice(targetIndex, 0, block);
            selectedId = sourceId;
        }, `${BLOCK_LABELS[selectedBlock()?.type] || 'Blok'} dipindahkan.`);
    };

    const moveBlock = (direction) => {
        const index = configuration.blocks.findIndex((block) => block.id === selectedId);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= configuration.blocks.length) return;

        mutate(() => {
            const [block] = configuration.blocks.splice(index, 1);
            configuration.blocks.splice(target, 0, block);
        }, `${BLOCK_LABELS[selectedBlock().type]} dipindahkan ke urutan ${target + 1}.`);
    };

    const deleteSelected = () => {
        const block = selectedBlock();
        if (!block || REQUIRED_TYPES.has(block.type)) return;

        mutate(() => {
            const index = configuration.blocks.findIndex((item) => item.id === selectedId);
            configuration.blocks.splice(index, 1);
            selectedId = configuration.blocks[Math.min(index, configuration.blocks.length - 1)]?.id ?? null;
        }, `${BLOCK_LABELS[block.type]} dihapus. Gunakan Undo untuk mengembalikan.`);
    };

    const updatePalette = () => {
        const usedTypes = new Set(configuration.blocks.map((block) => block.type));
        editor.querySelectorAll('[data-add-slip-block]').forEach((button) => {
            const unavailable = SINGLETON_TYPES.has(button.dataset.addSlipBlock)
                && usedTypes.has(button.dataset.addSlipBlock);
            button.disabled = readOnly || unavailable;
            button.title = unavailable ? 'Komponen ini sudah ada pada slip.' : '';
        });
    };

    const setControlValue = (control, value) => {
        if (control.type === 'radio') {
            control.checked = String(control.value) === String(value);
        } else {
            control.value = value;
        }
    };

    const renderProperties = () => {
        const block = selectedBlock();
        propertyEmpty.hidden = Boolean(block);
        propertyFields.hidden = !block;
        propertyFields.disabled = readOnly || !block;

        if (!block) {
            propertyHelp.textContent = 'Pilih satu blok pada kanvas.';
            return;
        }

        propertyHelp.textContent = `${BLOCK_LABELS[block.type]} · ${REQUIRED_TYPES.has(block.type) ? 'wajib' : 'opsional'}`;
        editor.querySelectorAll('[data-block-property]').forEach((control) => {
            setControlValue(control, block[control.dataset.blockProperty]);
        });

        const contentGroup = editor.querySelector('[data-content-property]');
        const variantGroup = editor.querySelector('[data-variant-property]');
        const heightGroup = editor.querySelector('[data-height-property]');
        const variantSelect = editor.querySelector('#slipBlockVariant');
        const variants = VARIANTS[block.type] || [];

        contentGroup.hidden = !['text', 'box'].includes(block.type);
        heightGroup.hidden = block.type !== 'box';
        variantGroup.hidden = variants.length === 0;
        variantSelect.innerHTML = variants
            .map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`)
            .join('');
        variantSelect.value = block.variant;

        deleteButton.disabled = readOnly || REQUIRED_TYPES.has(block.type);
        deleteButton.title = REQUIRED_TYPES.has(block.type)
            ? 'Blok wajib tidak dapat dihapus.'
            : '';
    };

    editor.querySelectorAll('[data-add-slip-block]').forEach((button) => {
        button.addEventListener('click', () => {
            const block = makeBlock(button.dataset.addSlipBlock);
            mutate(() => {
                configuration.blocks.push(block);
                selectedId = block.id;
            }, `${BLOCK_LABELS[block.type]} ditambahkan.`);
        });
    });

    editor.querySelectorAll('[data-block-property]').forEach((control) => {
        control.addEventListener('change', () => {
            const block = selectedBlock();
            if (!block) return;

            const property = control.dataset.blockProperty;
            let value = control.value;

            if (['width', 'font_weight'].includes(property)) value = Number.parseInt(value, 10);
            if (['font_size', 'spacing_before', 'spacing_after', 'height'].includes(property)) value = Number.parseFloat(value);

            mutate(() => {
                block[property] = value;
            }, `${BLOCK_LABELS[block.type]} diperbarui.`);
        });
    });

    paperLayoutDefault.addEventListener('change', () => {
        if (!PAPER_LAYOUTS.has(paperLayoutDefault.value)) return;

        mutate(() => {
            configuration.page.paper_layout = paperLayoutDefault.value;
        }, `Pembagian default diubah menjadi ${paperLayoutDefault.value === 'left_right' ? 'kiri–kanan' : 'atas–bawah'}.`);
    });

    editor.querySelectorAll('[data-move-block]').forEach((button) => {
        button.addEventListener('click', () => moveBlock(Number.parseInt(button.dataset.moveBlock, 10)));
    });

    deleteButton.addEventListener('click', deleteSelected);

    undoButton.addEventListener('click', () => {
        if (historyIndex === 0) return;
        historyIndex -= 1;
        configuration = clone(history[historyIndex]);
        selectedId = configuration.blocks.find((block) => block.id === selectedId)?.id
            ?? configuration.blocks[0]?.id;
        renderCanvas();
        updateHistoryButtons();
        announce('Perubahan terakhir dibatalkan.');
    });

    redoButton.addEventListener('click', () => {
        if (historyIndex >= history.length - 1) return;
        historyIndex += 1;
        configuration = clone(history[historyIndex]);
        selectedId = configuration.blocks.find((block) => block.id === selectedId)?.id
            ?? configuration.blocks[0]?.id;
        renderCanvas();
        updateHistoryButtons();
        announce('Perubahan diterapkan kembali.');
    });

    editor.querySelector('[data-editor-reset]').addEventListener('click', () => {
        mutate(() => {
            configuration = clone(defaultConfiguration);
            selectedId = configuration.blocks[0]?.id;
        }, 'Pola bawaan dimuat. Gunakan Undo jika ingin membatalkan.');
    });

    const renderPaperPreview = () => {
        const layout = PAPER_LAYOUTS.has(paperLayoutPreview.value)
            ? paperLayoutPreview.value
            : configuration.page.paper_layout;

        pagePreview.dataset.paperLayout = layout;

        document.querySelectorAll('#slipTemplatePreviewModal [data-slip-preview-slot]').forEach((slot) => {
            const preview = canvas.cloneNode(true);
            preview.querySelectorAll('.slip-editor-block').forEach((block) => {
                block.removeAttribute('tabindex');
                block.removeAttribute('draggable');
                block.classList.remove('is-selected', 'is-dragging');
            });
            preview.querySelectorAll('.slip-editor-block__handle').forEach((handle) => handle.remove());
            slot.replaceChildren(preview);
        });
    };

    editor.querySelector('[data-editor-preview]').addEventListener('click', () => {
        paperLayoutPreview.value = configuration.page.paper_layout;
        renderPaperPreview();
    });

    paperLayoutPreview.addEventListener('change', renderPaperPreview);

    canvas.addEventListener('dragover', (event) => {
        if (!readOnly) event.preventDefault();
    });
    canvas.addEventListener('drop', (event) => {
        if (!draggedId || event.target.closest('[data-block-id]')) return;
        event.preventDefault();
        mutate(() => {
            const index = configuration.blocks.findIndex((block) => block.id === draggedId);
            const [block] = configuration.blocks.splice(index, 1);
            configuration.blocks.push(block);
            selectedId = draggedId;
        }, 'Blok dipindahkan ke urutan terakhir.');
    });

    form.addEventListener('submit', syncInput);

    renderCanvas();
    updateHistoryButtons();
});
