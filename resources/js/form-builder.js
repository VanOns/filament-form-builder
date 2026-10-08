// The version query of this file, so an update never pairs it with cached older modules.
const version = new URL(import.meta.url).search
const { hiddenKeys, readValues } = await import(`./conditions.js${version}`)
const { visibleSteps, nextStep, previousStep, firstStepWithError, progress, firstInvalid, reportStep } = await import(`./steps.js${version}`)

function readConditions(form) {
    const conditions = {}

    for (const input of form.querySelectorAll('[data-form-builder-input][data-conditions]')) {
        conditions[input.dataset.formBuilderInput] = JSON.parse(input.dataset.conditions)
    }

    return conditions
}

/**
 * Shows a conditional field while its conditions hold. A hidden field is
 * disabled rather than emptied: it is not posted, so the server sees what the
 * page does, and what the visitor typed is back when it shows again.
 */
function connect(form, conditions) {
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

/**
 * Shows a form in steps one step at a time. Without this script every step
 * shows, one below the other, and the form works as one.
 */
function paginate(form, conditions) {
    const container = form.querySelector('[data-form-builder-steps]')

    if (!container) {
        return
    }

    const fieldsets = [...container.querySelectorAll('[data-form-builder-step]')]
    const steps = fieldsets.map((fieldset) => ({
        title: fieldset.dataset.title ?? null,
        keys: [...new Set([...fieldset.querySelectorAll('[data-form-builder-input]')].map((input) => input.dataset.formBuilderInput))],
    }))
    const previous = container.querySelector('[data-form-builder-previous]')
    const next = container.querySelector('[data-form-builder-next]')
    const ends = [...container.querySelectorAll('[data-form-builder-steps-end]')]
    const status = container.querySelector('[data-form-builder-step-status]')
    const hidden = () => hiddenKeys(conditions, readValues(form))
    let current = 0

    const describe = (number, total) => {
        const title = steps[current].title

        return container.dataset.stepOf.replace(':current', number).replace(':total', total) + (title ? `: ${title}` : '')
    }

    const render = (shouldFocus = false) => {
        const hiddenNow = hidden()
        const visible = visibleSteps(steps, hiddenNow)
        const isLast = nextStep(steps, hiddenNow, current) === null
        const { number, total, percent } = progress(steps, hiddenNow, current)

        fieldsets.forEach((fieldset, index) => {
            fieldset.hidden = index !== current
        })
        previous.hidden = previousStep(steps, hiddenNow, current) === null
        next.hidden = isLast
        // Out of sight rather than hidden: a captcha does not draw itself in an element that is not displayed.
        ends.forEach((end) => end.toggleAttribute('data-form-builder-waiting', !isLast))

        for (const item of container.querySelectorAll('[data-form-builder-progress-step]')) {
            const index = Number(item.dataset.formBuilderProgressStep)

            item.hidden = !visible.includes(index)
            item.dataset.state = index < current ? 'done' : (index === current ? 'current' : 'upcoming')

            if (index === current) {
                item.setAttribute('aria-current', 'step')
            } else {
                item.removeAttribute('aria-current')
            }
        }

        const bar = container.querySelector('[data-form-builder-progress-bar]')

        if (bar) {
            bar.style.setProperty('--ffb-progress', `${percent}%`)
            bar.setAttribute('aria-valuenow', String(percent))
            bar.setAttribute('aria-valuetext', describe(number, total))
            container.querySelector('[data-form-builder-progress-label]').textContent = describe(number, total)
        }

        if (shouldFocus) {
            const heading = fieldsets[current].querySelector('legend') ?? fieldsets[current]

            status.textContent = describe(number, total)
            heading.focus()
        }
    }

    const go = (index) => {
        current = index
        render(true)
    }

    next.addEventListener('click', () => {
        const following = nextStep(steps, hidden(), current)

        if (following !== null && reportStep(fieldsets[current])) {
            go(following)
        }
    })

    previous.addEventListener('click', () => {
        const before = previousStep(steps, hidden(), current)

        if (before !== null) {
            go(before)
        }
    })

    // The browser would stop at a required field in a step the visitor has not seen yet.
    form.noValidate = true

    form.addEventListener('submit', (event) => {
        const hiddenNow = hidden()
        const following = nextStep(steps, hiddenNow, current)

        // Enter in a field of an earlier step moves on instead of sending.
        if (following !== null) {
            event.preventDefault()
            reportStep(fieldsets[current]) && go(following)

            return
        }

        // An answer changed on the way may have made an earlier step incomplete again.
        const incomplete = visibleSteps(steps, hiddenNow).find((index) => firstInvalid(fieldsets[index]) !== null)

        if (incomplete !== undefined) {
            event.preventDefault()
            go(incomplete)
            reportStep(fieldsets[incomplete])
        } else if (ends.some((end) => !reportStep(end))) {
            event.preventDefault()
        }
    })

    form.addEventListener('input', () => render())
    form.addEventListener('change', () => render())

    const errorKeys = [...container.querySelectorAll('.ffb-error')]
        .map((error) => error.closest('[data-form-builder-input-wrapper]')?.dataset.formBuilderInputWrapper)
        .filter(Boolean)
    const visible = visibleSteps(steps, hidden())

    // After the server turned the form away: the step with the first error, or the last for one at the end.
    current = firstStepWithError(steps, errorKeys) ?? (errorKeys.length > 0 ? visible.at(-1) : visible[0]) ?? 0

    for (const element of container.querySelectorAll('[data-form-builder-progress]')) {
        element.hidden = false
    }

    render()
}

/**
 * Sends a form once: a second click would post the same answers again. The
 * server stores a repeat only once as well, this spares the visitor the wait.
 */
function sendOnce(form) {
    form.addEventListener('submit', (event) => {
        // After every other listener, and after the browser read the buttons along with the form.
        setTimeout(() => event.defaultPrevented || setBusy(form, true))
    })

    // Going back in the history brings the page back as it was left, buttons and all.
    window.addEventListener('pageshow', (event) => event.persisted && setBusy(form, false))
}

function setBusy(form, isBusy) {
    if (isBusy) {
        form.setAttribute('aria-busy', 'true')
    } else {
        form.removeAttribute('aria-busy')
    }

    for (const button of form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]')) {
        button.disabled = isBusy
    }
}

document.querySelectorAll('[data-form-builder-form]').forEach((form) => {
    const conditions = readConditions(form)

    connect(form, conditions)
    paginate(form, conditions)
    sendOnce(form)
})
