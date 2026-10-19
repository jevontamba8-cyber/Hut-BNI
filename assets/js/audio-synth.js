/**
 * BNI Anniversary Celebration - Sound Synthesizer via Web Audio API
 * Generates dynamic wheel spin ticks and celebration fanfare without external MP3 files.
 */

class SoundSynthesizer {
    constructor() {
        this.ctx = null;
        this.isMuted = false;
        this.init();
    }

    init() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.ctx = new AudioContext();
            }
        } catch (e) {
            console.warn('Web Audio API not supported in this browser.', e);
        }
    }

    ensureContext() {
        if (!this.ctx) {
            this.init();
        }
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        return this.isMuted;
    }

    // Play a sharp mechanical ratchet tick as wheel pegs hit the flapper
    playTick(intensity = 1.0) {
        if (this.isMuted) return;
        this.ensureContext();
        if (!this.ctx) return;

        try {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            const now = this.ctx.currentTime;

            // Wood / metal tick characteristic
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(600 + Math.random() * 200, now);
            osc.frequency.exponentialRampToValueAtTime(120, now + 0.04);

            gain.gain.setValueAtTime(0.25 * intensity, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.04);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(now);
            osc.stop(now + 0.045);
        } catch (e) {
            // ignore audio glitches
        }
    }

    // Play victorious celebration chords and fanfare
    playFanfare() {
        if (this.isMuted) return;
        this.ensureContext();
        if (!this.ctx) return;

        try {
            const now = this.ctx.currentTime;
            // Notes in Hz: C5, E5, G5, C6 (Major celebratory arpeggio + finale chord)
            const notes = [
                { f: 523.25, time: 0.00, dur: 0.15 },
                { f: 659.25, time: 0.15, dur: 0.15 },
                { f: 783.99, time: 0.30, dur: 0.20 },
                { f: 1046.50, time: 0.50, dur: 0.90 },
                // Harmony chord accompaniment
                { f: 523.25, time: 0.50, dur: 0.90 },
                { f: 659.25, time: 0.50, dur: 0.90 },
                { f: 1318.51, time: 0.70, dur: 0.70 }
            ];

            notes.forEach(n => {
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                const noteStart = now + n.time;

                osc.type = 'sine';
                osc.frequency.setValueAtTime(n.f, noteStart);

                gain.gain.setValueAtTime(0.25, noteStart);
                gain.gain.exponentialRampToValueAtTime(0.001, noteStart + n.dur);

                osc.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start(noteStart);
                osc.stop(noteStart + n.dur);
            });
        } catch (e) {
            console.warn('Fanfare sound error:', e);
        }
    }
}

window.soundEngine = new SoundSynthesizer();
