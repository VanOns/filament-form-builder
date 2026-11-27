/**
 * The conditions an editor puts on fields, evaluated the way the server does
 * (ConditionOperator::matches). Free of any framework, so a React or Vue front
 * end can import it as well as form-builder.js.
 */

const OPERATORS = ['equals', 'not_equals', 'empty', 'not_empty', 'greater_than', 'at_least', 'less_than', 'at_most']

const isPresent = (answer) => answer !== undefined && answer !== null && answer !== ''

// PHP's is_numeric, so the server and the page agree on what counts as a number.
const isNumeric = (text) => text !== undefined && text !== null && /^\s*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?\s*$/.test(String(text))

const isDate = (text) => typeof text === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(text)

// Numbers, or dates as a date input sends them, which sort as text. Without two of either nothing holds.
function compare(answer, expected) {
    if (isNumeric(answer) && isNumeric(expected)) {
        return Number(answer) - Number(expected)
    }

    if (isDate(answer) && isDate(expected)) {
        return answer < expected ? -1 : (answer > expected ? 1 : 0)
    }

    return NaN
}

/**
 * Whether an answer meets one rule. An answer with several values, such as a
 * multiple choice, equals a value when that value is among them.
 */
export function matches(value, operator, expected = null) {
    const answers = (Array.isArray(value) ? value : [value]).filter(isPresent).map(String)
    const wanted = String(expected ?? '')

    switch (operator) {
        case 'equals':
            return answers.includes(wanted)
        case 'not_equals':
            return !answers.includes(wanted)
        case 'empty':
            return answers.length === 0
        case 'not_empty':
            return answers.length > 0
        case 'greater_than':
            return compare(answers[0], expected) > 0
        case 'at_least':
            return compare(answers[0], expected) >= 0
        case 'less_than':
            return compare(answers[0], expected) < 0
        case 'at_most':
            return compare(answers[0], expected) <= 0
        default:
            return false
    }
}

/**
 * Whether a field's conditions hold for the answers so far. A rule the server
 * would not read is skipped, as it is there; a field without rules shows.
 */
export function passes(conditions, values) {
    const rules = (conditions?.rules ?? []).filter((rule) => OPERATORS.includes(rule?.operator) && typeof rule.key === 'string' && rule.key !== '')

    if (rules.length === 0) {
        return true
    }

    const results = rules.map((rule) => matches(values?.[rule.key], rule.operator, rule.value ?? null))

    return conditions.match === 'any' ? results.some(Boolean) : results.every(Boolean)
}

/**
 * The keys of the fields that are hidden, given each conditional field's
 * conditions by key. A hidden field counts as empty for the others, as it does
 * on the server, which never gets its value; so a field that depends on a
 * hidden one follows it.
 */
export function hiddenKeys(conditions, values) {
    const entries = Object.entries(conditions ?? {})
    let hidden = new Set()

    // Settles within as many rounds as there are fields; a loop of fields that
    // hide each other stops there.
    for (let round = 0; round <= entries.length; round++) {
        const shownValues = Object.fromEntries(Object.entries(values ?? {}).filter(([key]) => !hidden.has(key)))
        const next = new Set(entries.filter(([, rules]) => !passes(rules, shownValues)).map(([key]) => key))

        if (next.size === hidden.size && [...next].every((key) => hidden.has(key))) {
            break
        }

        hidden = next
    }

    return hidden
}

/**
 * Whether a title, text block or anything else without an answer of its own
 * shows: its conditions hold for the answers of the fields that show.
 */
export function isShown(conditions, values, hidden = []) {
    const skipped = new Set(hidden)

    return passes(conditions, Object.fromEntries(Object.entries(values ?? {}).filter(([key]) => !skipped.has(key))))
}

/**
 * The answers in a form element, by field key: `key[]` fields as a list, a file
 * as its name. Only what the form would post counts, so a disabled field or an
 * unticked box is no answer.
 */
export function readValues(form) {
    const values = {}

    for (const [name, value] of new FormData(form)) {
        const answer = typeof value === 'string' ? value : value.name

        if (name.endsWith('[]')) {
            const key = name.slice(0, -2)
            values[key] = [...(values[key] ?? []), answer]
        } else {
            values[name] = answer
        }
    }

    return values
}
