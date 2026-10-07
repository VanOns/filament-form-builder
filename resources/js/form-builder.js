// The version query of this file, so an update never pairs it with a cached older conditions.js.
const { hiddenKeys, readValues } = await import(`./conditions.js${new URL(import.meta.url).search}`)

/**
 * Shows a conditional field while its conditions hold. A hidden field is
 * disabled rather than emptied: it is not posted, so the server sees what the
 * page does, and what the visitor typed is back when it shows again.
 */
function connect(form) {
    const conditions = {}

    for (const input of form.querySelectorAll('[data-form-builder-input][data-conditions]')) {
        conditions[input.dataset.formBuilderInput] = JSON.parse(input.dataset.conditions)
    }

    if (Object.keys(conditions).length === 0) {
        return
    }

    const update = () => {
        const hidden = hiddenKeys(conditions, readValues(form))

        for (const key of Object.keys(conditions)) {
            toggle(form, key, !hidden.has(key))
        }
    }

    form.addEventListener('input', update)
    form.addEventListener('change', update)
    update()
}

function toggle(form, key, isShown) {
    const wrapper = form.querySelector(`[data-form-builder-input-wrapper="${CSS.escape(key)}"]`)

    if (wrapper) {
        wrapper.style.display = isShown ? '' : 'none'
    }

    for (const input of form.querySelectorAll(`[data-form-builder-input="${CSS.escape(key)}"]`)) {
        input.disabled = !isShown
        input.required = isShown && input.dataset.required === 'true'
    }
}

document.querySelectorAll('[data-form-builder-form]').forEach(connect)
