/** What `$form->getHoneypot()` gives. */
export interface Honeypot {
    field: string
    tokenField: string
    token: string
}

export interface HoneypotInput {
    type: 'text'
    name: string
    tabIndex: -1
    autoComplete: 'off'
    'aria-hidden': 'true'
    style: Record<string, string>
}

export function honeypotInput(honeypot: Honeypot): HoneypotInput

export function honeypotFields(honeypot: Honeypot | null | undefined): Record<string, string>
