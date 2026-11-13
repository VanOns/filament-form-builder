/**
 * A form in steps, as `$form->getStepKeys()` gives them: per step its title and
 * the keys its fields post. Free of any framework, so a React or Vue front end
 * can import it as well as form-builder.js.
 */

// An error on `cv.0` or a field posted as `cv[]` belongs to `cv`.
const baseKey = (key) => String(key).split('.')[0].replace(/\[\]$/, '')

// A step whose fields the conditions all hide is skipped; one with only text shows.
const isShown = (step, hidden) => step.keys.length === 0 || step.keys.some((key) => !hidden.has(key))

/**
 * The indexes of the steps that show, given the keys `hiddenKeys()` hides.
 */
export function visibleSteps(steps, hidden = new Set()) {
    return steps.flatMap((step, index) => (isShown(step, hidden) ? [index] : []))
}

export function nextStep(steps, hidden, current) {
    return visibleSteps(steps, hidden).find((index) => index > current) ?? null
}

export function previousStep(steps, hidden, current) {
    return visibleSteps(steps, hidden).findLast((index) => index < current) ?? null
}

export function stepOf(steps, key) {
    const index = steps.findIndex((step) => step.keys.includes(baseKey(key)))

    return index === -1 ? null : index
}

/**
 * Where a form opens again after the server turned it away: the first step
 * with an error, or null when no step has one.
 */
export function firstStepWithError(steps, errorKeys) {
    const indexes = [...errorKeys].map((key) => stepOf(steps, key)).filter((index) => index !== null)

    return indexes.length === 0 ? null : Math.min(...indexes)
}

/**
 * The current step's place among those that show, for "Step 2 of 3" or a bar.
 */
export function progress(steps, hidden, current) {
    const visible = visibleSteps(steps, hidden)
    const number = visible.filter((index) => index <= current).length

    return { number, total: visible.length, percent: visible.length === 0 ? 0 : Math.round((number / visible.length) * 100) }
}

/**
 * The first field within an element the browser would not send, skipping a
 * field the conditions disabled.
 */
export function firstInvalid(element) {
    return [...element.querySelectorAll('input, select, textarea')].find((control) => !control.disabled && !control.checkValidity()) ?? null
}

/**
 * Has the browser show why a step cannot be left yet; true when it can.
 */
export function reportStep(element) {
    const control = firstInvalid(element)

    control?.reportValidity()

    return control === null
}

/**
 * The `Precognition-Validate-Only` header that has the server check just these
 * keys, including each item of a field that posts a list.
 */
export function validateOnly(keys) {
    return [...new Set([...keys].map(baseKey))].flatMap((key) => [key, `${key}.*`]).join(',')
}
