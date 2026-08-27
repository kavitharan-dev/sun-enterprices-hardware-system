/**
 * Alpine searchable select — type-to-filter dropdown for forms.
 * Pass options: [{ value, label, search? }]
 * Panels teleport to <body> with fixed position so they never clip inside forms/tables.
 */
export function registerSearchableSelect(Alpine) {
    Alpine.data('searchableSelect', (config = {}) => ({
        open: false,
        query: '',
        highlighted: -1,
        value: String(config.value ?? ''),
        options: Array.isArray(config.options) ? config.options : [],
        placeholder: config.placeholder ?? 'Type to search…',
        emptyLabel: config.emptyLabel ?? 'Select…',
        allowEmpty: config.allowEmpty !== false,
        required: Boolean(config.required),
        disabled: Boolean(config.disabled),
        name: typeof config.name === 'function' ? '' : (config.name ?? ''),
        nameFn: typeof config.name === 'function' ? config.name : null,
        onChange: typeof config.onChange === 'function' ? config.onChange : null,
        getValue: typeof config.getValue === 'function' ? config.getValue : null,
        panelStyle: 'display:none',

        init() {
            if (this.getValue) {
                this.$watch(
                    () => this.getValue(),
                    (v) => {
                        const next = String(v ?? '');
                        if (next !== this.value) {
                            this.value = next;
                        }
                    },
                );
            }

            this._reposition = () => {
                if (this.open) {
                    this.positionPanel();
                }
            };

            window.addEventListener('resize', this._reposition);
            window.addEventListener('scroll', this._reposition, true);

            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this.positionPanel();
                    this.$nextTick(() => {
                        this.positionPanel();
                        this.$refs.search?.focus();
                    });
                } else {
                    this.panelStyle = 'display:none';
                }
            });
        },

        destroy() {
            window.removeEventListener('resize', this._reposition);
            window.removeEventListener('scroll', this._reposition, true);
        },

        get nameAttr() {
            return this.nameFn ? this.nameFn() : this.name;
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (! q) {
                return this.options;
            }

            return this.options.filter((opt) => {
                const hay = `${opt.label ?? ''} ${opt.search ?? ''} ${opt.value ?? ''}`.toLowerCase();

                return hay.includes(q);
            });
        },

        get selectedLabel() {
            if (! this.value) {
                return this.allowEmpty ? this.emptyLabel : this.placeholder;
            }

            const opt = this.options.find((o) => String(o.value) === String(this.value));

            return opt ? opt.label : this.placeholder;
        },

        positionPanel() {
            const trigger = this.$refs.trigger;
            if (! trigger) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const gap = 4;
            const minWidth = Math.max(rect.width, 300);
            const maxWidth = Math.min(480, window.innerWidth - 16);
            const width = Math.min(minWidth, maxWidth);
            const maxHeight = Math.min(360, window.innerHeight - 24);
            let left = rect.left;
            if (left + width > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - width - 8);
            }
            if (left < 8) {
                left = 8;
            }

            let top = rect.bottom + gap;
            const spaceBelow = window.innerHeight - rect.bottom - gap;
            const spaceAbove = rect.top - gap;

            if (spaceBelow < Math.min(240, maxHeight) && spaceAbove > spaceBelow) {
                top = Math.max(8, rect.top - Math.min(maxHeight, spaceAbove) - gap);
            }

            this.panelStyle = [
                'position:fixed',
                `top:${Math.round(top)}px`,
                `left:${Math.round(left)}px`,
                `width:${Math.round(width)}px`,
                `max-height:${Math.round(maxHeight)}px`,
                'z-index:10000',
                'display:flex',
            ].join(';');
        },

        toggle() {
            if (this.disabled) {
                return;
            }

            this.open = ! this.open;
            if (this.open) {
                this.query = '';
                this.highlighted = -1;
                this.positionPanel();
            }
        },

        select(opt) {
            this.value = String(opt?.value ?? '');
            this.query = '';
            this.open = false;
            this.highlighted = -1;
            if (this.onChange) {
                this.onChange(this.value, opt);
            }
        },

        clear() {
            if (! this.allowEmpty || this.disabled) {
                return;
            }

            this.select({ value: '', label: this.emptyLabel });
        },

        onKeydown(event) {
            if (! this.open && (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                this.toggle();

                return;
            }

            if (! this.open) {
                return;
            }

            const list = this.filtered;

            if (event.key === 'Escape') {
                event.preventDefault();
                this.open = false;

                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.highlighted = Math.min(this.highlighted + 1, list.length - 1);

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.highlighted = Math.max(this.highlighted - 1, 0);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (this.highlighted >= 0 && list[this.highlighted]) {
                    this.select(list[this.highlighted]);
                }
            }
        },
    }));
}
