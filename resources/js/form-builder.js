class FormBuilderForm {
    constructor(formElement) {
        this.formElement = formElement;
        this.inputs = Array.from(formElement.querySelectorAll('[data-form-builder-input]'));
        this.init();
    }

    init() {
        this.inputs.forEach(input => {
            input.addEventListener('change', () => {
                this.updateAllVisibility();
            });
        });

        this.updateAllVisibility();
    }

    updateAllVisibility() {
        this.inputs.forEach(input => this.updateVisibility(input));
    }

    updateVisibility(input) {
        const inputWrapper = input.closest('[data-form-builder-input-wrapper]');
        const conditions = input.getAttribute('data-conditions');

        if (!conditions || !inputWrapper) return;

        if (this.passes(JSON.parse(conditions))) {
            inputWrapper.style.display = '';
            if (input.hasAttribute('data-required') && input.getAttribute('data-required') === 'true') {
                input.setAttribute('required', true);
            }
        } else {
            inputWrapper.style.display = 'none';
            input.removeAttribute('required');

            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.tagName === 'SELECT') {
                if (input.multiple) {
                    Array.from(input.options).forEach(option => option.selected = false);
                } else {
                    input.selectedIndex = -1;
                }
            } else {
                input.value = '';
            }
        }
    }

    passes({ match, rules }) {
        const results = rules.map(rule => this.matches(this.getValue(rule.key), rule.operator, rule.value));

        return match === 'any' ? results.some(Boolean) : results.every(Boolean);
    }

    // A field with several answers equals a value when that value is among them.
    matches(value, operator, expected) {
        const answers = (Array.isArray(value) ? value : [value])
            .filter(answer => answer !== undefined && answer !== null && answer !== '');

        switch (operator) {
            case 'equals':
                return answers.includes(expected ?? '');
            case 'not_equals':
                return !answers.includes(expected ?? '');
            case 'empty':
                return answers.length === 0;
            case 'not_empty':
                return answers.length > 0;
            case 'greater_than':
                return this.compare(answers, expected) > 0;
            case 'at_least':
                return this.compare(answers, expected) >= 0;
            case 'less_than':
                return this.compare(answers, expected) < 0;
            case 'at_most':
                return this.compare(answers, expected) <= 0;
            default:
                return true;
        }
    }

    // Numbers, or dates as a date input sends them, which sort as text. Without two of either nothing holds;
    // NaN fails every comparison, like null does on the server.
    compare(answers, expected) {
        const isNumber = (text) => text !== undefined && text !== null && String(text).trim() !== '' && Number.isFinite(Number(text));
        const isDate = (text) => typeof text === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(text);

        if (isNumber(answers[0]) && isNumber(expected)) {
            return Number(answers[0]) - Number(expected);
        }

        if (isDate(answers[0]) && isDate(expected)) {
            return answers[0] < expected ? -1 : (answers[0] > expected ? 1 : 0);
        }

        return NaN;
    }

    getValue(key) {
        const inputs = Array.from(this.formElement.querySelectorAll(`[data-form-builder-input="${CSS.escape(key)}"]`));

        const values = inputs.reduce((acc, input) => {
            if ('checked' in input && (input.type === 'checkbox' || input.type === 'radio')) {
                if (input.checked) acc.push(input.value);
            } else if (input.value?.trim() !== '') {
                acc.push(input.value);
            }
            return acc;
        }, []);

        return values.length === 1 ? values[0] : values;
    }

    // A form can be on the page twice over, or the script loaded twice: each form starts once.
    static initAll() {
        document.querySelectorAll('[data-form-builder-form]').forEach((formElement) => {
            if (!formElement.formBuilder) {
                formElement.formBuilder = new FormBuilderForm(formElement);
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FormBuilderForm.initAll());
} else {
    FormBuilderForm.initAll();
}
