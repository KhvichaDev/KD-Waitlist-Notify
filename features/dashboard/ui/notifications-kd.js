/**
 * Notifications KD Library
 * Author: KhvichaDev
 * A standalone, plug-and-play toast notification system.
 */
(function (global, factory) {
    /** Force global assignment to ensure availability in environments with conflicting AMD loaders */
    const lib = factory();
    global.KDNotification = lib;
    
    if (typeof module === "object" && typeof module.exports === "object") {
        module.exports = lib;
    } else if (typeof define === "function" && define.amd) {
        define([], function() { return lib; });
    }
}(typeof globalThis !== "undefined" ? globalThis : (typeof window !== "undefined" ? window : this), function () {
    'use strict';
 
    let activeCloser = null;
    let notificationTimeout = null;
    let animationTimeout = null;
 
    /** Lazily injects the library's complete stylesheet into the document head on first use,
     * making the library fully self-contained with zero external CSS dependencies.
     */
    function injectCSS() {
        if (document.getElementById('kd-notification-styles')) return;
 
        const css = `
            :root, .kd-theme-dark {
                --kd-accent-color: #4da6ff;
                --kd-accent-bg-transparent: rgba(77, 166, 255, 0.15);
                --kd-z-toast: 2147483647;
                --kd-bg-surface-elevated: #2c2c2c;
                --kd-bg-surface-toast: #2b2b2b;
                --kd-text-primary: #e3e3e3;
                --kd-text-secondary: #c4c7c5;
                --kd-border-subtle: rgba(255, 255, 255, 0.1);
                --kd-btn-bg: rgba(255, 255, 255, 0.08);
                --kd-color-success: #81c995;
                --kd-bg-success-transparent: rgba(46, 204, 113, 0.15);
                --kd-color-error: #f4877d;
                --kd-color-warning: #f1c40f;
                --kd-gradient-primary: linear-gradient(135deg, #a8c7fa, #8ab4f8);
                --kd-gradient-primary-hover: linear-gradient(135deg, #b8d4fc, #9bbdfc);
                --kd-gradient-danger: linear-gradient(135deg, #f4877d, #e74c3c);
                --kd-gradient-danger-hover: linear-gradient(135deg, #f6a199, #ec7063);
                --kd-toast-anim-duration: 250ms;
                --kd-font-family: system-ui, -apple-system, sans-serif;
                --kd-shadow-toast: 0 14px 40px rgba(0, 0, 0, 0.6);
                --kd-mobile-offset-x: 12px;
                --kd-mobile-offset-y: 16px;
                --kd-mobile-max-width: 420px;
            }
 
            .kd-theme-light {
                --kd-bg-surface-elevated: #f5f5f5;
                --kd-bg-surface-toast: #ffffff;
                --kd-text-primary: #1c1c1e;
                --kd-text-secondary: #5a5a5e;
                --kd-border-subtle: rgba(0, 0, 0, 0.1);
                --kd-btn-bg: rgba(0, 0, 0, 0.05);
                --kd-accent-color: #007bff;
                --kd-accent-bg-transparent: rgba(0, 123, 255, 0.15);
                --kd-shadow-toast: 0 14px 40px rgba(0, 0, 0, 0.15);
            }
 
            .kd-toast-overlay {
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                z-index: var(--kd-z-toast);
                background-color: transparent;
                display: flex;
                opacity: 0;
                will-change: opacity;
                transition: opacity var(--kd-toast-anim-duration) ease-in-out, background-color var(--kd-toast-anim-duration) ease-in-out;
                pointer-events: none;
                font-family: var(--kd-font-family);
                box-sizing: border-box;
            }
 
            .kd-toast-overlay.kd-is-modal {
                background-color: rgba(0, 0, 0, 0.2);
                backdrop-filter: blur(4px);
                -webkit-backdrop-filter: blur(4px);
                pointer-events: auto;
            }
 
            .kd-toast-overlay.kd-pos-center { align-items: center; justify-content: center; padding: 0; }
            .kd-toast-overlay.kd-pos-top-left { align-items: flex-start; justify-content: flex-start; padding: var(--kd-toast-offset-y, 50px) var(--kd-toast-offset-x, 50px); }
            .kd-toast-overlay.kd-pos-top-right { align-items: flex-start; justify-content: flex-end; padding: var(--kd-toast-offset-y, 50px) var(--kd-toast-offset-x, 50px); }
            .kd-toast-overlay.kd-pos-bottom-left { align-items: flex-end; justify-content: flex-start; padding: var(--kd-toast-offset-y, 50px) var(--kd-toast-offset-x, 50px); }
            .kd-toast-overlay.kd-pos-bottom-right { align-items: flex-end; justify-content: flex-end; padding: var(--kd-toast-offset-y, 50px) var(--kd-toast-offset-x, 50px); }
 
            .kd-toast-overlay.visible { opacity: 1; }
 
            .kd-toast {
                pointer-events: auto;
                transform: scale(0.9);
                will-change: transform;
                background-color: var(--kd-bg-surface-toast);
                color: var(--kd-text-primary);
                padding: 28px;
                border-radius: 12px;
                border: 1px solid var(--kd-border-subtle);
                border-left: 3px solid transparent;
                box-shadow: var(--kd-shadow-toast);
                transition: transform var(--kd-toast-anim-duration) ease-in-out;
                min-width: 340px; max-width: 500px; min-height: 160px;
                display: flex; flex-direction: column; justify-content: center;
                box-sizing: border-box; overflow: hidden;
            }
 
            .kd-toast-overlay.visible .kd-toast { transform: scale(1); }
 
            .kd-toast-content {
                display: flex; flex-direction: column; align-items: center; gap: 12px;
                font-size: 15px; text-align: center;
            }
 
            .kd-toast-actions {
                display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; margin-top: 20px; width: 100%;
            }
            .kd-toast-actions:empty { display: none; }
 
            .kd-toast-actions button {
                font-size: 14px; padding: 10px 24px; border-radius: 24px; border: none;
                cursor: pointer; font-weight: 600; letter-spacing: 0.5px;
                font-family: inherit;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
                background-color: var(--kd-btn-bg); color: var(--kd-text-primary);
                border: 1px solid var(--kd-border-subtle);
                transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;
            }
            .kd-toast-actions button:hover {
                transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
                background-color: var(--kd-bg-surface-elevated);
            }
            .kd-toast-actions button:active { transform: translateY(0); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); }
            
            .kd-toast-actions button:focus-visible {
                outline: 2px solid var(--kd-accent-color); outline-offset: 2px;
            }
 
            .kd-toast-actions .kd-btn-primary { background: var(--kd-gradient-primary); color: #000; }
            .kd-toast-actions .kd-btn-primary:hover { background: var(--kd-gradient-primary-hover); }
            
            .kd-toast-actions .kd-btn-danger { background: var(--kd-gradient-danger); color: #fff; }
            .kd-toast-actions .kd-btn-danger:hover { background: var(--kd-gradient-danger-hover); }
 
            .kd-toast-text { color: var(--kd-text-primary); line-height: 1.7em; letter-spacing: 0.5px; word-break: break-word; }
            .kd-toast:not(:has(.kd-toast-rich-container)) .kd-toast-text { text-align: center; }
 
            .kd-toast-rich-container { display: flex; align-items: flex-start; gap: 16px; width: 100%; text-align: left; }
            .kd-toast-rich-icon {
                flex-shrink: 0; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
                border-radius: 50%; background-color: rgba(255, 255, 255, 0.1);
            }
            .kd-toast-rich-icon svg { width: 24px; height: 24px; fill: currentColor; }
            .kd-toast-rich-content { display: flex; flex-direction: column; justify-content: center; gap: 4px; flex-grow: 1; min-width: 0; }
            .kd-toast-rich-title { font-weight: 700; font-size: 16px; color: var(--kd-text-primary); line-height: 1.2; word-break: break-word; }
            .kd-toast-rich-text { font-size: 14px; color: var(--kd-text-secondary); line-height: 1.4; word-break: break-word; }
 
            .kd-toast-custom-icon {
                display: none; width: 44px; height: 44px; border-radius: 50%; margin-bottom: 12px;
                align-self: center; margin-left: auto; margin-right: auto;
                justify-content: center; align-items: center;
                background: rgba(137, 180, 250, 0.1); color: var(--kd-accent-color); flex-shrink: 0;
            }
            .kd-toast-custom-icon svg { width: 24px; height: 24px; fill: currentColor; }
 
            .kd-toast.kd-type-error { border-left-color: var(--kd-color-error); box-shadow: var(--kd-shadow-toast), 0 0 20px rgba(244, 135, 125, 0.1); }
            .kd-toast.kd-type-error .kd-toast-rich-icon, .kd-toast.kd-type-error .kd-toast-custom-icon { color: var(--kd-color-error); background-color: rgba(244, 135, 125, 0.15); }
            
            .kd-toast.kd-type-warning { border-left-color: var(--kd-color-warning); box-shadow: var(--kd-shadow-toast), 0 0 20px rgba(241, 196, 15, 0.1); }
            .kd-toast.kd-type-warning .kd-toast-rich-icon, .kd-toast.kd-type-warning .kd-toast-custom-icon { color: var(--kd-color-warning); background-color: rgba(241, 196, 15, 0.15); }
            
            .kd-toast.kd-type-success { border-left-color: var(--kd-color-success); box-shadow: var(--kd-shadow-toast), 0 0 20px rgba(46, 204, 113, 0.1); }
            .kd-toast.kd-type-success .kd-toast-rich-icon, .kd-toast.kd-type-success .kd-toast-custom-icon { color: var(--kd-color-success); background-color: var(--kd-bg-success-transparent); }
            
            .kd-toast.kd-type-info, .kd-toast.kd-type-processing { border-left-color: var(--kd-accent-color); box-shadow: var(--kd-shadow-toast), 0 0 20px rgba(77, 166, 255, 0.1); }
            .kd-toast.kd-type-info .kd-toast-rich-icon, .kd-toast.kd-type-info .kd-toast-custom-icon, .kd-toast.kd-type-processing .kd-toast-rich-icon, .kd-toast.kd-type-processing .kd-toast-custom-icon { color: var(--kd-accent-color); background-color: var(--kd-accent-bg-transparent); }
 
            .kd-toast.kd-type-info:has(.kd-toast-actions button) {
                background-color: var(--kd-bg-surface-elevated); padding: 28px 32px; border-radius: 24px; max-width: 500px; text-align: left;
            }
            .kd-toast.kd-type-info:has(.kd-toast-actions button) .kd-toast-actions { justify-content: flex-end; margin-top: 24px; }
 
            @keyframes kd-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .kd-anm-rotate { animation: kd-spin 1s linear infinite; transform-origin: 50% 50%; }
 
            @media (max-width: 600px) {
                .kd-toast-overlay {
                    --kd-toast-offset-x: var(--kd-mobile-offset-x) !important;
                    --kd-toast-offset-y: var(--kd-mobile-offset-y) !important;
                }
                .kd-toast-overlay.kd-pos-center {
                    padding: calc(var(--kd-toast-offset-y) + env(safe-area-inset-top, 0px)) calc(var(--kd-toast-offset-x) + env(safe-area-inset-right, 0px)) calc(var(--kd-toast-offset-y) + env(safe-area-inset-bottom, 0px)) calc(var(--kd-toast-offset-x) + env(safe-area-inset-left, 0px));
                }
                .kd-toast-overlay.kd-pos-top-left, 
                .kd-toast-overlay.kd-pos-top-right {
                    justify-content: center;
                    padding-top: calc(var(--kd-toast-offset-y) + env(safe-area-inset-top, 0px));
                    padding-left: calc(var(--kd-toast-offset-x) + env(safe-area-inset-left, 0px));
                    padding-right: calc(var(--kd-toast-offset-x) + env(safe-area-inset-right, 0px));
                }
                .kd-toast-overlay.kd-pos-bottom-left, 
                .kd-toast-overlay.kd-pos-bottom-right {
                    justify-content: center;
                    padding-bottom: calc(var(--kd-toast-offset-y) + env(safe-area-inset-bottom, 0px));
                    padding-left: calc(var(--kd-toast-offset-x) + env(safe-area-inset-left, 0px));
                    padding-right: calc(var(--kd-toast-offset-x) + env(safe-area-inset-right, 0px));
                }
                .kd-toast {
                    min-width: 0;
                    min-height: 0;
                    width: 100%;
                    max-width: var(--kd-mobile-max-width);
                    padding: 24px 20px;
                }
                .kd-toast.kd-type-info:has(.kd-toast-actions button) {
                    padding: 24px 20px;
                    max-width: var(--kd-mobile-max-width);
                }
                .kd-toast-actions {
                    flex-direction: column;
                    gap: 12px;
                }
                .kd-toast-actions button {
                    width: 100%;
                    padding: 14px 24px;
                }
                .kd-toast-rich-icon { width: 48px; height: 48px; }
                .kd-toast-rich-icon svg { width: 26px; height: 26px; }
                .kd-toast-custom-icon { width: 50px; height: 50px; margin-bottom: 16px; }
                .kd-toast-custom-icon svg { width: 28px; height: 28px; }
                .kd-toast-rich-title { font-size: 18px; }
                .kd-toast-rich-text { font-size: 15px; }
                .kd-toast-text { font-size: 16px; }
            }
 
            @media (prefers-reduced-motion: reduce) {
                :root, .kd-theme-dark, .kd-theme-light {
                    --kd-toast-anim-duration: 1ms !important;
                }
                .kd-toast-actions button {
                    transition: none !important;
                }
            }
        `;
 
        const style = document.createElement('style');
        style.id = 'kd-notification-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }
 
    /** Analyzes the host page's background color and font to automatically match the notification
     * theme. Falls back to the system's prefers-color-scheme when the background is transparent.
     */
    function detectEnvironment() {
        const bodyStyle = window.getComputedStyle(document.body);
        let bgColor = bodyStyle.backgroundColor;
        const font = bodyStyle.fontFamily;
 
        const bodyAlphaMatch = bgColor.match(/rgba?\(\s*\d+[,\s]+\d+[,\s]+\d+[,\s/]+([\d.]+)/);
        if (bgColor === 'transparent' || (bodyAlphaMatch && parseFloat(bodyAlphaMatch[1]) < 0.5)) {
            bgColor = window.getComputedStyle(document.documentElement).backgroundColor;
        }
 
        let isLight = false;
        const rgbMatch = bgColor.match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
        
        if (rgbMatch) {
            const r = parseInt(rgbMatch[1], 10);
            const g = parseInt(rgbMatch[2], 10);
            const b = parseInt(rgbMatch[3], 10);
            const aMatch = bgColor.match(/rgba?\(\s*\d+[,\s]+\d+[,\s]+\d+[,\s/]+([\d.]+)/);
            const alpha = aMatch ? parseFloat(aMatch[1]) : 1;
 
            if (alpha < 0.5 || bgColor === 'transparent') {
                isLight = !window.matchMedia('(prefers-color-scheme: dark)').matches;
            } else {
                /** Calculates perceived brightness using standard relative luminance formula to determine text contrast */
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                isLight = luminance > 0.5;
            }
        } else {
            isLight = !window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
 
        return { isLight, font };
    }
 
    const DANGEROUS_TAGS = new Set(['script', 'object', 'embed', 'iframe', 'frameset', 'form', 'base', 'meta', 'link', 'style', 'math', 'svg']);
    const ALLOWED_TYPES = new Set(['info', 'success', 'error', 'warning', 'processing']);
    const ALLOWED_POSITIONS = new Set(['center', 'top-left', 'top-right', 'bottom-left', 'bottom-right']);
 
    let sharedDOMParser = null;
 
    /** Prevents Cross-Site Scripting attacks by parsing raw HTML strings into a DOM tree 
     * and stripping out dangerous tags and malicious attribute values before rendering.
     */
    function safeFormatMessage(html) {
        if (typeof html !== 'string') return '';
        
        if (!sharedDOMParser) {
            sharedDOMParser = new DOMParser();
        }
        
        const doc = sharedDOMParser.parseFromString(html, 'text/html');
        
        const sanitize = (node) => {
            const children = Array.from(node.childNodes);
            children.forEach(child => {
                if (child.nodeType === 1) { 
                    if (DANGEROUS_TAGS.has(child.tagName.toLowerCase())) {
                        child.remove(); return;
                    }
                    const attrs = Array.from(child.attributes);
                    attrs.forEach(attr => {
                        const name = attr.name.toLowerCase();
                        const strippedValue = attr.value.toLowerCase().replace(/[\s\x00-\x1F\x7F]+/g, '');
                        const isDangerousData = strippedValue.startsWith('data:') && !strippedValue.startsWith('data:image/');
                        
                        if (name.startsWith('on') || strippedValue.startsWith('javascript:') || strippedValue.startsWith('vbscript:') || isDangerousData) {
                            child.removeAttribute(attr.name);
                        }
                    });
                    sanitize(child);
                }
            });
        };
        
        sanitize(doc.body);
        return doc.body.innerHTML;
    }
 
    const defaultIcons = {
        error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"></path></svg>',
        success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path></svg>',
        info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg>',
        processing: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="kd-anm-rotate"><path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z"></path></svg>'
    };
 
    /**
     * Shows a notification and resolves a Promise when the notification is closed.
     * Yields the clicked button's value, or true/null if dismissed without a button action.
     */
    function show(options) {
        injectCSS();
 
        return new Promise(resolve => {
            if (activeCloser) {
                activeCloser(null);
            }
 
            if (animationTimeout) {
                clearTimeout(animationTimeout);
                animationTimeout = null;
            }
 
            /** Clean up any orphaned DOM elements from interrupted animations */
            const existingOverlays = document.querySelectorAll('.kd-toast-overlay');
            existingOverlays.forEach(el => {
                if (document.body.contains(el)) {
                    document.body.removeChild(el);
                }
            });
 
            const previousActiveElement = document.activeElement;
 
            const {
                type: rawType = 'info',
                title = '',
                message = '',
                duration = 3000,
                buttons = null,
                isModal = false,
                icon = null,
                style = {},
                theme = 'auto',
                position: rawPosition = 'center',
                offsetX = '50px',
                offsetY = '50px'
            } = options;
 
            const type = ALLOWED_TYPES.has(rawType) ? rawType : 'info';
            const position = ALLOWED_POSITIONS.has(rawPosition) ? rawPosition : 'center';
 
            const overlay = document.createElement('div');
            overlay.className = `kd-toast-overlay kd-pos-${position}`;
            
            if (isModal) {
                overlay.classList.add('kd-is-modal');
            }
 
            if (position !== 'center') {
                const validateOffset = (val) => {
                    if (typeof val === 'number') return `${val}px`;
                    if (typeof val === 'string') {
                        const trimmed = val.trim();
                        if (/^[0-9.-]+(px|em|rem|%|vh|vw|vmin|vmax)$/i.test(trimmed) || /^(calc|var|env)\(/.test(trimmed)) {
                            return trimmed;
                        }
                    }
                    return '50px';
                };
                overlay.style.setProperty('--kd-toast-offset-x', validateOffset(offsetX));
                overlay.style.setProperty('--kd-toast-offset-y', validateOffset(offsetY));
            }
            
            let isLightMode = false;
            if (theme === 'auto') {
                const env = detectEnvironment();
                isLightMode = env.isLight;
                overlay.style.setProperty('--kd-font-family', env.font);
            } else {
                isLightMode = (theme === 'light');
            }
            
            overlay.classList.add(isLightMode ? 'kd-theme-light' : 'kd-theme-dark');
            
            const displayIcon = icon || defaultIcons[type] || defaultIcons.info;
            let contentHTML = '';
 
            if (title) {
                contentHTML = `
                    <div class="kd-toast-rich-container">
                        <div class="kd-toast-rich-icon">${displayIcon}</div>
                        <div class="kd-toast-rich-content">
                            <span class="kd-toast-rich-title">${safeFormatMessage(title)}</span>
                            <span class="kd-toast-rich-text">${safeFormatMessage(message)}</span>
                        </div>
                    </div>
                `;
            } else {
                contentHTML = `
                    <span class="kd-toast-custom-icon" style="display: ${displayIcon ? 'flex' : 'none'}">${displayIcon}</span>
                    <span class="kd-toast-text">${safeFormatMessage(message)}</span>
                `;
            }
 
            overlay.innerHTML = `
                <div class="kd-toast kd-type-${type}">
                    <div class="kd-toast-content">
                        ${contentHTML}
                        <div class="kd-toast-actions"></div>
                    </div>
                </div>
            `;
 
            const toastEl = overlay.querySelector('.kd-toast');
 
            if (style && typeof style === 'object') {
                for (const key in style) {
                    if (Object.prototype.hasOwnProperty.call(style, key)) {
                        if (key.startsWith('--')) {
                            toastEl.style.setProperty(key, style[key]);
                        } else {
                            toastEl.style[key] = style[key];
                        }
                    }
                }
            }
 
            document.body.appendChild(overlay);
 
            const actionsEl = overlay.querySelector('.kd-toast-actions');
            clearTimeout(notificationTimeout);
 
            let isClosing = false;
 
            const dismiss = (value) => {
                if (isClosing) return;
                isClosing = true;
 
                if (notificationTimeout) {
                    clearTimeout(notificationTimeout);
                    notificationTimeout = null;
                }
 
                document.removeEventListener('keydown', handleKeyDown);
                overlay.removeEventListener('click', overlayClickHandler);
                
                const animDurationStr = window.getComputedStyle(overlay).getPropertyValue('--kd-toast-anim-duration').trim().toLowerCase();
                const isSeconds = animDurationStr.endsWith('s') && !animDurationStr.endsWith('ms');
                const parsedValue = parseFloat(animDurationStr);
                const animDuration = (isNaN(parsedValue) ? 250 : parsedValue * (isSeconds ? 1000 : 1));
                
                overlay.classList.remove('visible');
 
                if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                    previousActiveElement.focus();
                }
                
                animationTimeout = setTimeout(() => {
                    if (document.body.contains(overlay)) {
                        document.body.removeChild(overlay);
                    }
                    animationTimeout = null;
                }, animDuration);
 
                activeCloser = null;
                resolve(value);
            };
 
            const overlayClickHandler = (event) => {
                if (event.target === overlay && !isModal) dismiss(null);
            };
 
            if (!isModal) {
                overlay.addEventListener('click', overlayClickHandler);
            }
 
            const handleKeyDown = (e) => {
                if (e.key === 'Escape' && !isModal) {
                    dismiss(null);
                    return;
                }
 
                /** Traps keyboard focus within the entire notification to maintain accessibility standards */
                if (e.key === 'Tab') {
                    const focusableElements = toastEl.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    
                    if (focusableElements.length === 0) {
                        if (isModal) e.preventDefault();
                        return;
                    }
 
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];
 
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement || document.activeElement === document.body) {
                            lastElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastElement || document.activeElement === document.body) {
                            firstElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            };
 
            if (buttons && Array.isArray(buttons) && buttons.length > 0) {
                let elementToFocus = null;
 
                buttons.forEach((buttonInfo, index) => {
                    const button = document.createElement('button');
                    button.innerHTML = safeFormatMessage(buttonInfo.text);
                    
                    if (buttonInfo.className) {
                        button.className = buttonInfo.className;
                    }
                    
                    button.onclick = () => {
                        if (typeof buttonInfo.onClick === 'function') buttonInfo.onClick();
                        setTimeout(() => dismiss(buttonInfo.value !== undefined ? buttonInfo.value : true), 0);
                    };
                    actionsEl.appendChild(button);
                    
                    /** Determine which button gets priority focus (Primary wins over index 0) */
                    if (index === 0) {
                        elementToFocus = button;
                    } else if (buttonInfo.className && buttonInfo.className.includes('primary')) {
                        elementToFocus = button;
                    }
                });
 
                if (elementToFocus) {
                    setTimeout(() => elementToFocus.focus(), 50);
                }
            } else if (!isModal && duration > 0) {
                notificationTimeout = setTimeout(() => dismiss(true), duration);
            }
 
            document.addEventListener('keydown', handleKeyDown);
 
            /** Trigger Animation cleanly aligned with the browser's render cycle */
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    overlay.classList.add('visible');
                });
            });
        });
    }
 
    /** Programmatically dismisses the currently active notification, if any */
    function close() {
        if (activeCloser) {
            activeCloser(null);
        }
    }
 
    return {
        show,
        close
    };
}));
