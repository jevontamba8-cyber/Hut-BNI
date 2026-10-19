/**
 * BNI Anniversary Celebration - Magic Wheel Canvas Engine
 * Handles rendering, spin physics, sound synchronization, and winner reveal.
 */

class MagicWheel {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        
        this.participants = [];       // All eligible participants
        this.visibleParticipants = [];// Slices currently drawn on wheel
        this.currentAngle = 0;        // Current rotation angle in radians
        this.isSpinning = false;
        this.targetWinner = null;
        this.selectedPrizeId = null;

        // Custom palette matching BNI theme
        this.sliceColors = [
            '#F37021', // BNI Orange
            '#0B2240', // BNI Navy
            '#005E54', // BNI Teal
            '#F5A623', // Festive Gold
            '#0A1D37', // Deep Midnight
            '#D45A10', // Deep Orange
            '#00796B', // Vibrant Teal
            '#FFB300'  // Amber Gold
        ];

        this.init();
    }

    init() {
        this.resize();
        window.addEventListener('resize', () => this.resize());
    }

    resize() {
        const dpr = window.devicePixelRatio || 1;
        const rect = this.canvas.getBoundingClientRect();
        const size = Math.min(rect.width, rect.height) || 550;

        this.canvas.width = size * dpr;
        this.canvas.height = size * dpr;
        this.ctx.scale(dpr, dpr);
        this.radius = size / 2;
        this.center = size / 2;

        this.draw();
    }

    setParticipants(list) {
        this.participants = list || [];
        this.prepareVisibleSlices();
        this.draw();
    }

    /**
     * For 1,200+ participants, rendering 1,200 thin 0.3° slivers would make text unreadable.
     * We keep 100% of participants eligible on the backend, while rendering a clean,
     * high-contrast set of 20 to 30 visible slices on the wheel.
     */
    prepareVisibleSlices(guaranteedWinner = null) {
        if (!this.participants || this.participants.length === 0) {
            this.visibleParticipants = [{ nama_lengkap: 'Belum Ada Peserta Hadir', nomor_undian: '-' }];
            return;
        }

        const maxSlices = Math.min(24, this.participants.length);
        
        // Shuffle or pick random sample of participants
        const pool = [...this.participants];
        const shuffled = pool.sort(() => 0.5 - Math.random());
        let selected = shuffled.slice(0, maxSlices);

        // If a winner is predetermined by backend, ensure winner is at index 0 or specific slice
        if (guaranteedWinner) {
            selected = selected.filter(p => p.id !== guaranteedWinner.id);
            selected.unshift(guaranteedWinner);
        }

        this.visibleParticipants = selected;
    }

    draw() {
        if (!this.ctx) return;
        const ctx = this.ctx;
        const count = this.visibleParticipants.length;
        const sliceAngle = (2 * Math.PI) / count;

        ctx.clearRect(0, 0, this.radius * 2, this.radius * 2);

        ctx.save();
        ctx.translate(this.center, this.center);
        ctx.rotate(this.currentAngle);

        for (let i = 0; i < count; i++) {
            const startAngle = i * sliceAngle;
            const endAngle = startAngle + sliceAngle;
            const participant = this.visibleParticipants[i];

            // 1. Draw Slice Wedge
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.arc(0, 0, this.radius - 8, startAngle, endAngle);
            ctx.closePath();

            ctx.fillStyle = this.sliceColors[i % this.sliceColors.length];
            ctx.fill();

            // Slice separator line
            ctx.strokeStyle = '#FFFFFF';
            ctx.lineWidth = 2;
            ctx.stroke();

            // 2. Draw Participant Name (Text)
            ctx.save();
            ctx.rotate(startAngle + sliceAngle / 2);
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#FFFFFF';
            
            // Text shadow for crisp visibility
            ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
            ctx.shadowBlur = 4;
            ctx.shadowOffsetX = 1;
            ctx.shadowOffsetY = 1;

            // Responsive font size
            const fontSize = Math.max(11, Math.min(16, 280 / count));
            ctx.font = `bold ${fontSize}px 'Inter', sans-serif`;

            // Truncate name if exceptionally long for slice width
            let displayName = participant.nama_lengkap || 'Peserta';
            if (displayName.length > 22) {
                displayName = displayName.substring(0, 20) + '...';
            }

            ctx.fillText(displayName, this.radius - 28, 0);
            ctx.restore();

            // 3. Draw Outer Pegs
            ctx.save();
            const pegAngle = startAngle;
            const pegX = (this.radius - 12) * Math.cos(pegAngle);
            const pegY = (this.radius - 12) * Math.sin(pegAngle);
            ctx.beginPath();
            ctx.arc(pegX, pegY, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#FFE082';
            ctx.fill();
            ctx.strokeStyle = '#FFFFFF';
            ctx.lineWidth = 1.5;
            ctx.stroke();
            ctx.restore();
        }

        ctx.restore();
    }

    /**
     * Start smooth animated spin physics toward the backend winner
     */
    spinToWinner(winnerData, prizeData, onComplete) {
        if (this.isSpinning) return;
        this.isSpinning = true;
        this.targetWinner = winnerData;

        // Rebuild slices with guaranteed winner placed at a known slice
        const winnerSliceIndex = Math.floor(Math.random() * Math.min(12, this.visibleParticipants.length));
        
        // Form array with winner precisely at winnerSliceIndex
        let otherParticipants = this.participants.filter(p => p.id !== winnerData.id);
        otherParticipants.sort(() => 0.5 - Math.random());
        
        const count = Math.min(24, this.participants.length || 1);
        const slices = [];
        let otherIdx = 0;
        for (let i = 0; i < count; i++) {
            if (i === winnerSliceIndex) {
                slices.push(winnerData);
            } else {
                slices.push(otherParticipants[otherIdx++] || { nama_lengkap: 'BNI Participant', nomor_undian: '-' });
            }
        }
        this.visibleParticipants = slices;

        // Calculate Target Angle:
        // Pointer is at TOP (12 o'clock = 3 * PI / 2 or -PI / 2)
        const sliceAngle = (2 * Math.PI) / count;
        // Winner slice center angle in wheel space
        const winnerCenter = winnerSliceIndex * sliceAngle + sliceAngle / 2;
        
        // We want: (wheelRotation + winnerCenter) % 2PI = 3*PI/2 (top)
        // wheelRotation = 3*PI/2 - winnerCenter
        const pointerPos = 1.5 * Math.PI;
        let targetEndAngle = pointerPos - winnerCenter;
        
        // Normalize
        while (targetEndAngle < 0) targetEndAngle += 2 * Math.PI;

        // Add 6 to 9 full spins for suspense and drama
        const fullSpins = 7 * (2 * Math.PI);
        const totalRotation = fullSpins + targetEndAngle - (this.currentAngle % (2 * Math.PI));
        const startAngle = this.currentAngle;
        const finalAngle = startAngle + totalRotation;

        const duration = 7500; // 7.5 seconds dramatic spin
        const startTime = performance.now();
        let lastTickAngle = startAngle;

        const flapper = document.querySelector('.wheel-pointer');
        if (flapper) flapper.classList.add('flapping');

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Custom cubic-bezier / quintic deceleration curve for realistic physics
            const easeOut = 1 - Math.pow(1 - progress, 4.5);
            this.currentAngle = startAngle + totalRotation * easeOut;

            // Audio tick as each slice peg crosses the top needle
            const angleDiff = Math.abs(this.currentAngle - lastTickAngle);
            if (angleDiff >= sliceAngle) {
                if (window.soundEngine) {
                    const intensity = Math.max(0.2, 1 - progress * 0.7);
                    window.soundEngine.playTick(intensity);
                }
                lastTickAngle = this.currentAngle;
            }

            this.draw();

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                this.isSpinning = false;
                if (flapper) flapper.classList.remove('flapping');
                this.currentAngle = finalAngle % (2 * Math.PI);
                this.draw();

                // Sound & Confetti celebration
                if (window.soundEngine) window.soundEngine.playFanfare();
                if (window.fireConfetti) window.fireConfetti(150);

                if (typeof onComplete === 'function') {
                    onComplete(winnerData, prizeData);
                }
            }
        };

        requestAnimationFrame(animate);
    }
}

window.MagicWheel = MagicWheel;
