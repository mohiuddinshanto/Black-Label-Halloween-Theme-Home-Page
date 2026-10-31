/**
 * Spin & Win WooCommerce - Complete Frontend Wheel & Popup Engine
 * Native HTML5 Canvas + Web Audio API synthesizer for peg ticks & win fanfare.
 */
(function() {
    'use strict';

    // Simple Web Audio Synthesizer
    var SoundEffects = {
        ctx: null,
        output: null,
        init: function() {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioContextClass();
                this.output = this.ctx.createGain();
                this.output.gain.value = 0.9;
                var delay = this.ctx.createDelay(), feedback = this.ctx.createGain(), wet = this.ctx.createGain();
                delay.delayTime.value = 0.24; feedback.gain.value = 0.38; wet.gain.value = 0.32;
                this.output.connect(this.ctx.destination);
                this.output.connect(delay); delay.connect(feedback); feedback.connect(delay); delay.connect(wet); wet.connect(this.ctx.destination);
            }
            if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
            return this.ctx;
        },
        startSpin: function(duration) {
            try {
                var ctx = this.init();
                if (!ctx) return;
                var t = ctx.currentTime + 0.02, seconds = duration / 1000;
                this.creak(t, seconds * 0.3, 160, 380, 0.2);
                this.creak(t + seconds * 0.33, seconds * 0.38, 420, 170, 0.18);
                this.creak(t + seconds * 0.78, seconds * 0.2, 260, 140, 0.14);
                this.ghost(t + seconds * 0.08, seconds * 0.85);
            } catch (e) {}
        },
        creak: function(t, duration, from, to, volume) {
            var ctx = this.ctx, osc = ctx.createOscillator(), filter = ctx.createBiquadFilter();
            var amp = ctx.createGain(), env = ctx.createGain(), lfo = ctx.createOscillator();
            var lfoGain = ctx.createGain(), pitchGain = ctx.createGain();
            osc.type = 'sawtooth'; osc.frequency.setValueAtTime(from, t); osc.frequency.exponentialRampToValueAtTime(to, t + duration);
            lfo.type = 'square'; lfo.frequency.setValueAtTime(34, t); lfo.frequency.linearRampToValueAtTime(11, t + duration);
            lfoGain.gain.value = 0.5; pitchGain.gain.value = from * 0.07; lfo.connect(lfoGain); lfoGain.connect(amp.gain); lfo.connect(pitchGain); pitchGain.connect(osc.frequency);
            filter.type = 'bandpass'; filter.Q.value = 7; filter.frequency.setValueAtTime(1100, t); filter.frequency.exponentialRampToValueAtTime(420, t + duration);
            env.gain.setValueAtTime(0, t); env.gain.linearRampToValueAtTime(volume, t + duration * 0.15);
            env.gain.linearRampToValueAtTime(volume * 0.8, t + duration * 0.7); env.gain.linearRampToValueAtTime(0, t + duration);
            osc.connect(filter); filter.connect(amp); amp.connect(env); env.connect(this.output);
            osc.start(t); lfo.start(t); osc.stop(t + duration + 0.05); lfo.stop(t + duration + 0.05);
        },
        ghost: function(t, duration) {
            var ctx = this.ctx, osc = ctx.createOscillator(), gain = ctx.createGain();
            var vibrato = ctx.createOscillator(), vibratoGain = ctx.createGain();
            osc.type = 'sine'; osc.frequency.setValueAtTime(330, t); osc.frequency.exponentialRampToValueAtTime(233, t + duration);
            vibrato.frequency.value = 5.5; vibratoGain.gain.value = 8; vibrato.connect(vibratoGain); vibratoGain.connect(osc.frequency);
            gain.gain.setValueAtTime(0, t); gain.gain.linearRampToValueAtTime(0.06, t + duration * 0.4); gain.gain.linearRampToValueAtTime(0, t + duration);
            osc.connect(gain); gain.connect(this.output); osc.start(t); vibrato.start(t); osc.stop(t + duration + 0.05); vibrato.stop(t + duration + 0.05);
        },
        playTick: function() {
            try {
                this.init();
                if (!this.ctx) return;
                if (this.isHalloween) {
                    var tickTime = this.ctx.currentTime, tick = this.ctx.createOscillator(), tickGain = this.ctx.createGain();
                    var segment = this.tickIndex || 0;
                    tick.type = 'triangle'; tick.frequency.setValueAtTime(150 + (segment % 3) * 30, tickTime);
                    tick.frequency.exponentialRampToValueAtTime((150 + (segment % 3) * 30) * 0.45, tickTime + 0.07);
                    tickGain.gain.setValueAtTime(0.22, tickTime); tickGain.gain.exponentialRampToValueAtTime(0.001, tickTime + 0.09);
                    tick.connect(tickGain); tickGain.connect(this.output); tick.start(tickTime); tick.stop(tickTime + 0.1);
                    return;
                }
                var osc = this.ctx.createOscillator();
                var gain = this.ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(650, this.ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(150, this.ctx.currentTime + 0.035);
                gain.gain.setValueAtTime(0.12, this.ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.035);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start(this.ctx.currentTime);
                osc.stop(this.ctx.currentTime + 0.04);
            } catch(e) {}
        },
        playFanfare: function() {
            try {
                this.init();
                if (!this.ctx) return;
                if (this.isHalloween) return;
                var notes = [523.25, 659.25, 783.99, 1046.5];
                var now = this.ctx.currentTime;
                var self = this;
                notes.forEach(function(freq, i) {
                    var osc = self.ctx.createOscillator();
                    var gain = self.ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + i * 0.1);
                    gain.gain.setValueAtTime(0.001, now + i * 0.1);
                    gain.gain.linearRampToValueAtTime(0.2, now + i * 0.1 + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.1 + 0.4);
                    osc.connect(gain);
                    gain.connect(self.ctx.destination);
                    osc.start(now + i * 0.1);
                    osc.stop(now + i * 0.1 + 0.45);
                });
            } catch(e) {}
        }
    };

    // Canvas Lucky Wheel Renderer
    function SpinWinWheel(canvasId, slices) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.slices = slices || [];
        this.isHalloweenWheel = !!this.canvas.closest('.mh-spin');
        SoundEffects.isHalloween = this.isHalloweenWheel;
        this.currentAngle = 0;
        this.size = this.isHalloweenWheel ? 1000 : 360;
        this.center = this.size / 2;
        this.radius = this.isHalloweenWheel ? 420 : 164;
        if (this.isHalloweenWheel) { this.canvas.width = 1000; this.canvas.height = 1000; }
        this.lastPeg = -1;
        this.blink = 0;
        this.render(0);
        if (this.isHalloweenWheel) {
            var wheelSelf = this;
            window.setInterval(function() {
                if (!wheelSelf.isSpinning) {
                    wheelSelf.blink ^= 1;
                    wheelSelf.render(wheelSelf.currentAngle);
                }
            }, 600);
        }
    }

    SpinWinWheel.prototype.render = function(angle) {
        var ctx = this.ctx;
        var total = this.slices.length;
        if (total === 0) return;
        var sliceAngle = (2 * Math.PI) / total;

        if (this.isHalloweenWheel) {
            var size = this.size, center = this.center, radius = this.radius;
            ctx.clearRect(0, 0, size, size);
            ctx.save(); ctx.translate(center, center);
            ctx.beginPath(); ctx.arc(0, 0, 490, 0, Math.PI * 2); ctx.fillStyle = '#0c0805'; ctx.fill();
            ctx.lineWidth = 12; ctx.strokeStyle = '#ff7a00'; ctx.stroke();
            for (var light = 0; light < 28; light++) {
                var lightAngle = light / 28 * Math.PI * 2;
                ctx.beginPath(); ctx.arc(Math.cos(lightAngle) * 458, Math.sin(lightAngle) * 458, 9, 0, Math.PI * 2);
                var lit = (light + this.blink) % 2 === 0;
                ctx.fillStyle = lit ? '#ffd089' : '#5a3a14'; ctx.shadowColor = '#ff9a2e'; ctx.shadowBlur = lit ? 18 : 0; ctx.fill();
            }
            ctx.shadowBlur = 0;
            var halloweenColors = [
                ['#ff7a00', '#0d0b0a'], ['#0d0b0a', '#ff7a00'],
                ['#ff7a00', '#0d0b0a'], ['#f6e9d7', '#0d0b0a']
            ];
            for (var i = 0; i < total; i++) {
                var start = angle + i * sliceAngle, end = start + sliceAngle, slice = this.slices[i];
                ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, radius, start, end); ctx.closePath();
                var halloweenColor = halloweenColors[i % halloweenColors.length];
                ctx.fillStyle = halloweenColor[0]; ctx.fill(); ctx.lineWidth = 4; ctx.strokeStyle = '#f6e9d7'; ctx.stroke();
                ctx.save(); ctx.rotate(start + sliceAngle / 2); ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
                ctx.fillStyle = halloweenColor[1];
                var label = slice.label || '';
                ctx.font = (label.length > 12 ? 38 : label.length > 9 ? 46 : 58) + "px 'Permanent Marker', cursive";
                ctx.fillText(label, 396, 0, 300); ctx.restore();
            }
            ctx.beginPath(); ctx.arc(0, 0, 92, 0, Math.PI * 2); ctx.fillStyle = '#ff7a00'; ctx.fill();
            ctx.lineWidth = 6; ctx.strokeStyle = '#f6e9d7'; ctx.stroke();
            ctx.fillStyle = '#0d0b0a';
            [[-34, -16], [34, -16]].forEach(function(point) {
                ctx.beginPath(); ctx.moveTo(point[0] - 16, point[1] + 16); ctx.lineTo(point[0] + 16, point[1] + 16);
                ctx.lineTo(point[0], point[1] - 18); ctx.closePath(); ctx.fill();
            });
            ctx.beginPath(); ctx.moveTo(-8, 6); ctx.lineTo(8, 6); ctx.lineTo(0, 18); ctx.closePath(); ctx.fill();
            ctx.beginPath(); ctx.moveTo(-50, 28); ctx.quadraticCurveTo(0, 66, 50, 28); ctx.lineTo(36, 28);
            ctx.lineTo(28, 42); ctx.lineTo(14, 32); ctx.lineTo(0, 46); ctx.lineTo(-14, 32);
            ctx.lineTo(-28, 42); ctx.lineTo(-36, 28); ctx.closePath(); ctx.fill();
            ctx.restore();
            return;
        }

        ctx.clearRect(0, 0, this.size, this.size);

        // Outer Metallic Rim
        ctx.save();
        ctx.beginPath();
        ctx.arc(this.center, this.center, this.radius + 6, 0, 2 * Math.PI);
        ctx.fillStyle = '#14091f';
        ctx.shadowColor = 'rgba(0,0,0,0.3)';
        ctx.shadowBlur = 10;
        ctx.fill();

        ctx.lineWidth = 3;
        ctx.strokeStyle = '#ff7a1a';
        ctx.stroke();
        ctx.restore();

        // Outer rim gold studs
        var studs = total * 2;
        for (var s = 0; s < studs; s++) {
            var studAngle = angle + (s * (2 * Math.PI)) / studs;
            var sx = this.center + (this.radius + 2) * Math.cos(studAngle);
            var sy = this.center + (this.radius + 2) * Math.sin(studAngle);
            ctx.beginPath();
            ctx.arc(sx, sy, 2.5, 0, 2 * Math.PI);
            ctx.fillStyle = s % 2 === 0 ? '#9be564' : '#ffb347';
            ctx.fill();
        }

        // Tiny pumpkin and bat ornaments sit on the outer ring, clear of labels.
        if (this.isHalloweenWheel) {
            for (var ornament = 0; ornament < 8; ornament++) {
                var ornamentAngle = angle + ornament * (Math.PI / 4);
                var ornamentX = this.center + (this.radius + 2) * Math.cos(ornamentAngle);
                var ornamentY = this.center + (this.radius + 2) * Math.sin(ornamentAngle);
                ctx.save();
                ctx.translate(ornamentX, ornamentY);
                ctx.rotate(ornamentAngle);
                if (ornament % 2 === 0) {
                    // Pumpkin silhouette with a little green stem.
                    ctx.beginPath();
                    ctx.moveTo(0, -5);
                    ctx.bezierCurveTo(2, -7, 5, -4, 5, 0);
                    ctx.bezierCurveTo(5, 4, 2, 6, 0, 5);
                    ctx.bezierCurveTo(-2, 6, -5, 4, -5, 0);
                    ctx.bezierCurveTo(-5, -4, -2, -7, 0, -5);
                    ctx.fillStyle = '#ff8a26';
                    ctx.fill();
                    ctx.beginPath();
                    ctx.moveTo(-1, -4);
                    ctx.lineTo(0, -7);
                    ctx.lineTo(2, -7);
                    ctx.lineTo(1, -3);
                    ctx.closePath();
                    ctx.fillStyle = '#9be564';
                    ctx.fill();
                    ctx.strokeStyle = '#4a2c6e';
                    ctx.lineWidth = 1;
                    ctx.stroke();
                } else {
                    // Small bat silhouette.
                    ctx.beginPath();
                    ctx.moveTo(-6, -2);
                    ctx.lineTo(-3, -1);
                    ctx.lineTo(-2, -4);
                    ctx.lineTo(0, -2);
                    ctx.lineTo(2, -4);
                    ctx.lineTo(3, -1);
                    ctx.lineTo(6, -2);
                    ctx.lineTo(4, 3);
                    ctx.lineTo(1, 2);
                    ctx.lineTo(0, 4);
                    ctx.lineTo(-1, 2);
                    ctx.lineTo(-4, 3);
                    ctx.closePath();
                    ctx.fillStyle = '#9be564';
                    ctx.fill();
                    ctx.strokeStyle = '#241338';
                    ctx.lineWidth = 1;
                    ctx.stroke();
                }
                ctx.restore();
            }
        }

        // Draw Slices
        for (var i = 0; i < total; i++) {
            var start = angle + i * sliceAngle;
            var end = start + sliceAngle;
            var slice = this.slices[i];

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(this.center, this.center);
            ctx.arc(this.center, this.center, this.radius, start, end);
            ctx.closePath();
            // Always use the saved campaign color so admin color choices remain visible.
            ctx.fillStyle = slice.bgColor || '#4f46e5';
            ctx.fill();

            ctx.lineWidth = this.isHalloweenWheel ? 2 : 1.5;
            ctx.strokeStyle = this.isHalloweenWheel ? 'rgba(255,190,91,0.72)' : 'rgba(255,255,255,0.25)';
            ctx.stroke();

            ctx.translate(this.center, this.center);
            var midAngle = start + sliceAngle / 2;
            // Text Label
            ctx.rotate(midAngle);
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = slice.textColor || '#ffffff';
            ctx.font = 'bold 12px sans-serif';
            ctx.fillText(slice.label || '', this.radius - 20, 0);
            ctx.restore();
        }

        // Center Hub
        ctx.save();
        ctx.beginPath();
        ctx.arc(this.center, this.center, 34, 0, 2 * Math.PI);
        ctx.fillStyle = '#241338';
        ctx.fill();
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#ff7a1a';
        ctx.stroke();

        ctx.fillStyle = '#9be564';
        ctx.font = 'bold 12px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('SPIN', this.center, this.center);
        ctx.restore();
    };

    SpinWinWheel.prototype.spinTo = function(targetIndex, onComplete) {
        var total = this.slices.length;
        var sliceAngle = (2 * Math.PI) / total;
        var pointerAngle = 1.5 * Math.PI; // Top Pointer
        var sliceCenter = targetIndex * sliceAngle + sliceAngle / 2;
        var baseRotations = 6 * (2 * Math.PI);
        var currentNorm = this.currentAngle % (2 * Math.PI);
        var targetOffset = pointerAngle - sliceCenter;
        var totalDelta = baseRotations + (targetOffset - currentNorm);

        var duration = 5200;
        var startTime = performance.now();
        var initialAngle = this.currentAngle;
        var self = this;
        this.isSpinning = true;
        SoundEffects.startSpin(duration);

        function animate(now) {
            var elapsed = now - startTime;
            var progress = Math.min(elapsed / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3.2);
            var angle = initialAngle + totalDelta * ease;
            self.currentAngle = angle;

            // Audio peg ticks
            var normAngle = (angle % (2 * Math.PI) + (2 * Math.PI)) % (2 * Math.PI);
            var currentPeg = Math.floor(normAngle / (sliceAngle / 2));
            if (currentPeg !== self.lastPeg) {
                self.lastPeg = currentPeg;
                SoundEffects.tickIndex = currentPeg;
                SoundEffects.playTick();
            }

            self.render(angle);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                self.isSpinning = false;
                if (onComplete) onComplete();
            }
        }
        requestAnimationFrame(animate);
    };

    // Main Applet Initialization
    function initializeSpinWin() {
        if (typeof spinWinData === 'undefined') return;

        var triggers = document.querySelectorAll('.spin-win-floating-badge');
        var overlay = document.getElementById('spin-win-modal-overlay');
        var inlineMount = document.querySelector('.mh-spin .spin-win-inline-mount[data-spin-win-inline="1"]');
        // The wheel is opt-in: it exists only where the page provides the inline mount.
        // This keeps the plugin from adding a floating launcher or popup site-wide.
        triggers.forEach(function(trigger) { trigger.remove(); });
        if (!inlineMount) {
            if (overlay) overlay.remove();
            return;
        }
        if (inlineMount && overlay) {
            var inlineModal = overlay.querySelector('.spin-win-modal');
            if (inlineModal) {
                inlineModal.classList.add('spin-win-inline-modal');
                var inlineClose = inlineModal.querySelector('.spin-win-close-btn');
                if (inlineClose) inlineClose.remove();
                inlineMount.appendChild(inlineModal);
            }
            overlay.remove();
            overlay = null;
        } else {
            return;
        }
        var closeBtn = document.getElementById('spin-win-close-btn');
        var form = document.getElementById('spin-win-ajax-form');
        var errorBox = document.getElementById('spin-win-error');

        var viewForm = document.getElementById('spin-win-view-form');
        var viewSpinning = document.getElementById('spin-win-view-spinning');
        var viewResult = document.getElementById('spin-win-view-result');
        var wonTitle = document.getElementById('spin-won-title');
        var wonValue = document.getElementById('spin-won-value');
        var wonCode = document.getElementById('spin-won-code');
        var copyBtn = document.getElementById('spin-copy-code-btn');
        var savedRewardKey = 'spin_win_saved_reward_v1';

        function setRewardMessage(message) {
            var resultMessage = document.getElementById('spin-win-result-message');
            if (resultMessage) resultMessage.textContent = message;
        }

        function showPrizeValue(reward) {
            if (!wonValue) return;
            var amount = reward.prize_value;
            var type = reward.prize_type;
            if (amount === undefined || amount === null || amount === '' || Number(amount) <= 0) {
                wonValue.textContent = '';
                return;
            }
            if (type === 'percentage_discount') wonValue.textContent = amount + '% off';
            else if (type === 'fixed_cart_discount' || type === 'fixed_product_discount') wonValue.textContent = '$' + amount + ' off';
            else wonValue.textContent = '';
        }

        function showSavedReward(reward) {
            viewForm.style.display = 'none';
            viewSpinning.style.display = 'none';
            viewResult.style.display = 'block';
            wonTitle.textContent = reward.label || 'Your saved reward';
            showPrizeValue(reward);
            wonCode.textContent = reward.coupon_code || 'No coupon';
        }

        function applySavedReward(reward, restoreFirst) {
            if (!reward.coupon_code) {
                setRewardMessage('Your previous result is saved. You can spin again after the IP limit period.');
                return Promise.resolve();
            }
            var ready = restoreFirst ? fetch(spinWinData.restoreRewardUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': spinWinData.nonce },
                body: JSON.stringify({ log_id: reward.log_id, email: reward.email, coupon_code: reward.coupon_code })
            }).then(function(response) { return response.json(); }) : Promise.resolve({ success: true });

            return ready.then(function(data) {
                if (!data || data.code || !reward.coupon_code) {
                    setRewardMessage('Your saved reward is shown above. Enter the coupon code at checkout.');
                    return null;
                }
                return fetch(spinWinData.applyCouponUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': spinWinData.nonce },
                    body: JSON.stringify({ coupon_code: reward.coupon_code })
                }).then(function(response) { return response.json(); });
            }).then(function(result) {
                if (!result) return;
                if (result.applied) setRewardMessage('Your saved reward is applied to your cart.');
                else if (result.reason === 'empty_cart') setRewardMessage('Your saved reward will apply automatically when your cart has an item.');
                else setRewardMessage('Your reward is saved. Use the coupon code at checkout.');
            }).catch(function() {
                setRewardMessage('Your saved reward is shown above. Enter the coupon code at checkout.');
            });
        }

        function saveReward(data, email) {
            if (!spinWinData.ipLimitEnabled || !data.ip_lock_expires_at) return;
            try {
                localStorage.setItem(savedRewardKey, JSON.stringify({
                    log_id: data.log_id,
                    email: email,
                    coupon_code: data.coupon_code || '',
                    label: data.label || '',
                    slice_index: data.slice_index,
                    prize_type: data.prize_type || '',
                    prize_value: data.prize_value,
                    spin_created_at: data.spin_created_at,
                    ip_lock_expires_at: data.ip_lock_expires_at
                }));
            } catch (e) {}
        }

        function lookupSavedReward() {
            if (!spinWinData.lookupRewardUrl) return;
            fetch(spinWinData.lookupRewardUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': spinWinData.nonce },
                body: JSON.stringify({})
            }).then(function(response) { return response.json(); }).then(function(data) {
                if (!data || data.code || !data.already_spun) return;
                saveReward(data, data.email || '');
                showSavedReward(data);
                applySavedReward(data, false);
            }).catch(function() {});
        }

        function resumeSavedReward() {
            if (!spinWinData.ipLimitEnabled) {
                try { localStorage.removeItem(savedRewardKey); } catch (e) {}
                return;
            }
            try {
                var reward = JSON.parse(localStorage.getItem(savedRewardKey) || 'null');
                if (!reward) {
                    lookupSavedReward();
                    return;
                }
                var hours = Math.max(1, parseInt(spinWinData.ipLimitHours, 10) || 24);
                var startedAt = (parseInt(reward.spin_created_at, 10) || 0) * 1000;
                var expiresAt = startedAt + hours * 60 * 60 * 1000;
                if (!reward.log_id || !reward.email || expiresAt <= Date.now()) {
                    localStorage.removeItem(savedRewardKey);
                    lookupSavedReward();
                    return;
                }
                showSavedReward(reward);
                applySavedReward(reward, true);
            } catch (e) { lookupSavedReward(); }
        }

        resumeSavedReward();

        // Keep fixed-position UI relative to the viewport, outside theme wrappers
        // that may apply transforms or clipping.
        triggers.forEach(function(trigger) {
            if (trigger.parentNode !== document.body) document.body.appendChild(trigger);
            applyLauncherPosition(trigger);
        });
        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        function applyLauncherPosition(trigger) {
            var className = trigger.className || '';
            var isLeft = /spin-win-pos-(bottom|top)_left/.test(className);
            var isTop = /spin-win-pos-top_(left|right)/.test(className);
            var adminBar = document.getElementById('wpadminbar');
            var adminBarBottom = 0;
            if (isTop && adminBar) {
                var adminBarRect = adminBar.getBoundingClientRect();
                if (adminBarRect.bottom > 0 && adminBarRect.top < window.innerHeight) {
                    adminBarBottom = adminBarRect.bottom;
                }
            }

            var gap = window.innerWidth <= 768 ? 12 : 20;
            var configuredSize = parseInt((trigger.style.getPropertyValue('--spin-launcher-size') || '72').replace('px', ''), 10);
            var launcherSize = Math.max(1, Math.min(100, configuredSize || 72, window.innerWidth - gap * 2));
            var styles = {
                position: 'fixed',
                top: isTop ? Math.max(gap, adminBarBottom + gap) + 'px' : 'auto',
                right: isLeft ? 'auto' : gap + 'px',
                bottom: isTop ? 'auto' : gap + 'px',
                left: isLeft ? gap + 'px' : 'auto',
                width: launcherSize + 'px',
                height: launcherSize + 'px',
                minWidth: launcherSize + 'px',
                minHeight: launcherSize + 'px',
                maxWidth: launcherSize + 'px',
                maxHeight: launcherSize + 'px',
                boxSizing: 'border-box',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0',
                padding: '0',
                border: '0',
                borderRadius: '50%',
                background: 'transparent',
                boxShadow: 'none',
                float: 'none',
                transform: 'none',
                overflow: 'visible',
                lineHeight: '0',
                opacity: '1',
                visibility: 'visible',
                pointerEvents: 'auto',
                cursor: 'pointer',
                zIndex: '2147483646',
                appearance: 'none',
                WebkitAppearance: 'none'
            };
            Object.keys(styles).forEach(function(property) {
                var cssProperty = property.replace(/[A-Z]/g, function(letter) { return '-' + letter.toLowerCase(); });
                trigger.style.setProperty(cssProperty, styles[property], 'important');
            });

            var icon = trigger.querySelector('.spin-win-wheel-svg');
            if (icon) {
                ['display', 'width', 'height', 'max-width', 'max-height', 'pointer-events'].forEach(function(property) {
                    icon.style.setProperty(property, property === 'display' ? 'block' : (property === 'pointer-events' ? 'none' : '100%'), 'important');
                });
            }
        }

        window.addEventListener('resize', function() {
            triggers.forEach(applyLauncherPosition);
        });

        var wheel = null;
        if (inlineMount && document.getElementById('spin-win-canvas')) {
            wheel = new SpinWinWheel('spin-win-canvas', spinWinData.slices);
        }

        function openModal() {
            if (!overlay) return;
            overlay.style.display = 'flex';
            if (!wheel) {
                wheel = new SpinWinWheel('spin-win-canvas', spinWinData.slices);
            }
        }

        function closeModal() {
            overlay.style.display = 'none';
        }

        window.SpinWinApp = { open: openModal, close: closeModal };

        triggers.forEach(function(trigger) {
            trigger.addEventListener('click', function(event) {
                event.preventDefault();
                openModal();
            });
        });
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal();
            });
        }

        // Auto Open timer if configured
        var delay = (spinWinData.rules && spinWinData.rules.popup_delay !== undefined) ? parseInt(spinWinData.rules.popup_delay, 10) : 0;
        if (delay > 0) {
            setTimeout(function() {
                // Check if user dismissed recently
                if (!sessionStorage.getItem('spin_win_dismissed')) {
                    openModal();
                }
            }, delay * 1000);
        }

        // Form Submit
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                // Unlock/resume browser audio directly from the user's submit gesture.
                SoundEffects.init();
                errorBox.style.display = 'none';

                var hp = form.querySelector('input[name="spin_win_hp"]').value;
                if (hp) return;

                var email = document.getElementById('spin-email').value;
                var name = document.getElementById('spin-name').value;

                viewForm.style.display = 'none';
                viewSpinning.style.display = 'block';

                fetch(spinWinData.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': spinWinData.nonce
                    },
                    body: JSON.stringify({
                        email: email,
                        name: name,
                    })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.code || !data.success) {
                        viewSpinning.style.display = 'none';
                        viewForm.style.display = 'block';
                        errorBox.textContent = data.message || 'Something went wrong. Please try again.';
                        errorBox.style.display = 'block';
                        return;
                    }

                    saveReward(data, email);

                    var applyCouponRequest = data.coupon_code ? fetch(spinWinData.applyCouponUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': spinWinData.nonce
                        },
                        body: JSON.stringify({ coupon_code: data.coupon_code || '' })
                    }).then(function(res) {
                        return res.json();
                    }).catch(function() {
                        return { success: false, applied: false };
                    }) : Promise.resolve({ success: true, applied: false, reason: 'no_coupon' });

                    if (data.already_spun) {
                        showSavedReward(data);
                        applyCouponRequest.then(function(result) {
                            if (result && result.applied) setRewardMessage('Your saved reward is applied to your cart.');
                            else if (result && result.reason === 'empty_cart') setRewardMessage('Your saved reward will apply automatically when your cart has an item.');
                            else setRewardMessage('Your previous reward is shown above. Use its coupon code at checkout.');
                        });
                        return;
                    }

                    // Spin Wheel to Server Result
                    wheel.spinTo(data.slice_index, function() {
                        SoundEffects.playFanfare();
                        viewSpinning.style.display = 'none';
                        viewResult.style.display = 'block';
                        wonTitle.textContent = data.label || 'Reward Won!';
                        showPrizeValue(data);
                        wonCode.textContent = data.coupon_code || 'APPLIED';
                        applyCouponRequest.then(function(applyResult) {
                            var resultMessage = document.getElementById('spin-win-result-message');
                            if (!resultMessage) return;
                            if (applyResult && applyResult.applied) {
                                resultMessage.textContent = 'Your reward is applied to your cart. Continue shopping or check out when you’re ready.';
                            } else if (applyResult && applyResult.reason === 'no_coupon') {
                                resultMessage.textContent = 'This spin did not include a coupon. Try your luck again next time.';
                            } else if (applyResult && applyResult.reason === 'empty_cart') {
                                resultMessage.textContent = 'Your reward is saved and will apply automatically when your cart has an item.';
                            } else {
                                resultMessage.textContent = 'Your reward is saved. Open your cart after adding an item and it will apply automatically.';
                            }
                        });
                        sessionStorage.setItem('spin_win_dismissed', '1');
                    });
                })
                .catch(function(err) {
                    viewSpinning.style.display = 'none';
                    viewForm.style.display = 'block';
                    errorBox.textContent = 'Connection error. Please try again.';
                    errorBox.style.display = 'block';
                });
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var code = wonCode.textContent;
                navigator.clipboard.writeText(code);
                copyBtn.textContent = 'Copied!';
                setTimeout(function() { copyBtn.textContent = 'Copy'; }, 2000);
            });
        }
    }

    // The script is normally loaded in the footer, but themes and optimization
    // plugins may execute it after DOMContentLoaded has already fired.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSpinWin);
    } else {
        initializeSpinWin();
    }
})();
