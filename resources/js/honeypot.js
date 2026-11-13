/**
 * The honeypot of `$form->getHoneypot()` for a front end of its own: a field
 * a person never sees, which has to stay empty, and the moment the form was
 * shown. The server turns a form away without them.
 */

/**
 * The attributes of the field to stay empty, to spread on an <input>.
 */
export function honeypotInput(honeypot) {
    return {
        type: 'text',
        name: honeypot.field,
        tabIndex: -1,
        autoComplete: 'off',
        'aria-hidden': 'true',
        style: { position: 'absolute', left: '-10000px', width: '1px', height: '1px', overflow: 'hidden' },
    }
}

/**
 * Both values as the form starts out, to merge into the data a form helper
 * such as Inertia's useForm() posts. Nothing when the form has no honeypot.
 */
export function honeypotFields(honeypot) {
    if (!honeypot) {
        return {}
    }

    return { [honeypot.field]: '', [honeypot.tokenField]: honeypot.token }
}
