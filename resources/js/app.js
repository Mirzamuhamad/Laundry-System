window.sidebarShell = () => ({
    collapsed: localStorage.getItem('laundry-sidebar-collapsed') === '1',
    logoutOpen: false,
    logoutForm: null,
    toggle() {
        this.collapsed = !this.collapsed;
        localStorage.setItem('laundry-sidebar-collapsed', this.collapsed ? '1' : '0');
    },
    askLogout(event) {
        this.logoutForm = event.currentTarget;
        this.logoutOpen = true;
    },
    cancelLogout() {
        this.logoutOpen = false;
        this.logoutForm = null;
    },
    confirmLogout() {
        this.logoutForm?.submit();
    },
});

window.clockWidget = () => ({
    time: '',
    start() {
        const update = () => this.time = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date());
        update(); setInterval(update, 1000);
    },
});

window.attendanceCamera = ($wire) => ({
    cameraOpen: false, mode: 'in', stream: null, photoData: '', error: '', loading: false,
    async openCamera(mode) {
        this.mode = mode; this.cameraOpen = true; this.photoData = ''; this.error = '';
        await this.$nextTick();
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 960 } }, audio: false });
            this.$refs.video.srcObject = this.stream;
        } catch (e) {
            this.error = 'Kamera tidak dapat dibuka. Pastikan izin kamera sudah diberikan dan aplikasi dibuka melalui HTTPS.';
        }
    },
    capture() {
        const video = this.$refs.video, canvas = this.$refs.canvas;
        const size = Math.min(video.videoWidth || 720, video.videoHeight || 720);
        canvas.width = 720; canvas.height = 720;
        const sx = ((video.videoWidth || size) - size) / 2, sy = ((video.videoHeight || size) - size) / 2;
        canvas.getContext('2d').drawImage(video, sx, sy, size, size, 0, 0, 720, 720);
        this.photoData = canvas.toDataURL('image/jpeg', .78); this.stopStream();
    },
    async retake() { this.photoData = ''; await this.openCamera(this.mode); },
    async submit() {
        if (!this.photoData || this.loading) return; this.loading = true; this.error = '';
        try { this.mode === 'in' ? await $wire.checkIn(this.photoData) : await $wire.checkOut(this.photoData); }
        catch (e) { this.error = e?.message || 'Absensi gagal disimpan. Silakan coba lagi.'; }
        finally { this.loading = false; }
    },
    stopStream() { if (this.stream) this.stream.getTracks().forEach(track => track.stop()); this.stream = null; },
    closeCamera() { this.stopStream(); this.cameraOpen = false; this.photoData = ''; this.error = ''; },
});

const bluetoothPrinter = {
    device: null,
    characteristic: null,
    services: [
        '000018f0-0000-1000-8000-00805f9b34fb',
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '0000ae30-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    ],
    emit(connected, message = '', name = '') {
        window.dispatchEvent(new CustomEvent('printer-state', { detail: { connected, message, name } }));
    },
    isConnected() {
        return Boolean(this.characteristic && this.device?.gatt?.connected);
    },
    async connect(requestNew = true) {
        if (!navigator.bluetooth) throw new Error('Browser ini belum mendukung Web Bluetooth. Gunakan Chrome atau Edge melalui HTTPS.');
        if (this.device?.gatt?.connected && this.characteristic) return this.device;
        if (requestNew) {
            this.device = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: this.services });
            localStorage.setItem('laundry-printer-name', this.device.name || 'Printer Bluetooth');
        } else if (navigator.bluetooth.getDevices) {
            const granted = await navigator.bluetooth.getDevices();
            const savedName = localStorage.getItem('laundry-printer-name');
            this.device = granted.find(device => !savedName || device.name === savedName) || null;
            if (!this.device) return null;
        }
        this.device.addEventListener('gattserverdisconnected', () => { this.characteristic = null; this.emit(false, 'Printer terputus', this.device?.name); });
        const server = await this.device.gatt.connect();
        let writable = null;
        for (const serviceId of this.services) {
            try {
                const service = await server.getPrimaryService(serviceId);
                const characteristics = await service.getCharacteristics();
                writable = characteristics.find(item => item.properties.writeWithoutResponse || item.properties.write);
                if (writable) break;
            } catch (_) { /* service tidak tersedia pada model printer ini */ }
        }
        if (!writable) { server.disconnect(); throw new Error('Printer ditemukan tetapi kanal cetaknya tidak dikenali. Pastikan printer mendukung Bluetooth Low Energy ESC/POS.'); }
        this.characteristic = writable; this.emit(true, 'Printer siap', this.device.name || 'Printer Bluetooth');
        return this.device;
    },
    encode(text) {
        const encoder = new TextEncoder(), parts = [new Uint8Array([0x1b, 0x40])];
        for (const rawLine of text.split('\n')) {
            if (rawLine.startsWith('[QR]')) {
                const data = encoder.encode(rawLine.slice(4));
                const storeLength = data.length + 3;
                parts.push(new Uint8Array([0x1b, 0x61, 1]));
                parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]));
                parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x43, 0x06]));
                parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x45, 0x31]));
                parts.push(new Uint8Array([0x1d, 0x28, 0x6b, storeLength & 0xff, storeLength >> 8, 0x31, 0x50, 0x30]));
                parts.push(data);
                parts.push(new Uint8Array([0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x51, 0x30, 0x0a]));
                continue;
            }
            const match = rawLine.match(/^\[(C|L|R)\](.*)$/); const align = match?.[1] || 'L'; const line = match?.[2] ?? rawLine;
            parts.push(new Uint8Array([0x1b, 0x61, align === 'C' ? 1 : (align === 'R' ? 2 : 0)]));
            parts.push(encoder.encode(line + '\n'));
        }
        parts.push(new Uint8Array([0x1b, 0x64, 4, 0x1d, 0x56, 0x00]));
        const length = parts.reduce((sum, part) => sum + part.length, 0), output = new Uint8Array(length); let offset = 0;
        for (const part of parts) { output.set(part, offset); offset += part.length; }
        return output;
    },
    async print(text) {
        if (!this.characteristic || !this.device?.gatt?.connected) await this.connect(false);
        if (!this.characteristic) throw new Error('Printer belum terhubung. Tekan tombol Hubungkan printer terlebih dahulu.');
        const bytes = this.encode(text), chunkSize = 180;
        for (let offset = 0; offset < bytes.length; offset += chunkSize) {
            const chunk = bytes.slice(offset, offset + chunkSize);
            if (this.characteristic.properties.writeWithoutResponse && this.characteristic.writeValueWithoutResponse) await this.characteristic.writeValueWithoutResponse(chunk);
            else await this.characteristic.writeValue(chunk);
        }
    },
};

window.printerControl = () => ({
    connected: false, connecting: false, deviceName: localStorage.getItem('laundry-printer-name') || '',
    init() {
        window.addEventListener('printer-state', event => { this.connected = event.detail.connected; this.deviceName = event.detail.name || this.deviceName; });
        bluetoothPrinter.connect(false).catch(() => {});
    },
    async connect() {
        this.connecting = true;
        try { await bluetoothPrinter.connect(true); window.dispatchEvent(new CustomEvent('notify', { detail: 'Printer Bluetooth berhasil dihubungkan.' })); }
        catch (error) { window.dispatchEvent(new CustomEvent('notify', { detail: error.message || 'Printer gagal dihubungkan.' })); }
        finally { this.connecting = false; }
    },
});

const restoreGrantedPrinter = () => bluetoothPrinter.connect(false).catch(() => {});
document.addEventListener('livewire:navigated', restoreGrantedPrinter);
restoreGrantedPrinter();

window.addEventListener('print-receipt', async event => {
    if (!bluetoothPrinter.isConnected()) {
        window.dispatchEvent(new CustomEvent('notify', { detail: 'Printer belum terhubung. Membuka cetak browser.' }));
        if (event.detail.fallbackUrl) window.open(event.detail.fallbackUrl, '_blank');
        return;
    }

    try {
        await bluetoothPrinter.print(event.detail.text);
        window.dispatchEvent(new CustomEvent('notify', { detail: 'Struk berhasil dikirim ke printer.' }));
    } catch (error) {
        window.dispatchEvent(new CustomEvent('notify', { detail: error.message || 'Cetak Bluetooth gagal. Periksa printer lalu coba lagi.' }));
    }
});

if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
