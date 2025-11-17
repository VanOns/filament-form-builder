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

        const visibleWhenKey = input.getAttribute('data-visible-when-key');
        const visibleWhenValue = input.getAttribute('data-visible-when-value');

        if (!visibleWhenKey || !visibleWhenValue) return;

        const currentValue = this.getValue(visibleWhenKey);

        const shouldShow = this.shouldShow(currentValue, visibleWhenValue);

        if (shouldShow) {
            inputWrapper.style.display = '';
            if (input.hasAttribute('data-required')) {
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

    shouldShow(value, conditionValue) {
        if (conditionValue === '__not_empty__') {
            return Array.isArray(value)
                ? value.length > 0
                : value !== undefined && value !== null && value !== '';
        }

        if (conditionValue === '__empty__') {
            return Array.isArray(value)
                ? value.length === 0
                : value === undefined || value === null || value === '';
        }

        if (conditionValue.startsWith('__not__')) {
            const actualValue = conditionValue.replace('__not__', '');
            return Array.isArray(value)
                ? !value.includes(actualValue)
                : value !== actualValue;
        }

        return Array.isArray(value)
            ? value.includes(conditionValue)
            : value === conditionValue;
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

    static initAll() {
        const forms = document.querySelectorAll('[data-form-builder-form]');
        forms.forEach(formElement => new FormBuilderForm(formElement));
    }
}

document.addEventListener('DOMContentLoaded', () => FormBuilderForm.initAll());
