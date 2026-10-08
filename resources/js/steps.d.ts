/** What `$form->getStepKeys()` gives, one entry per step. */
export interface Step {
    title: string | null
    keys: string[]
}

export interface Progress {
    number: number
    total: number
    percent: number
}

export function visibleSteps(steps: Step[], hidden?: ReadonlySet<string>): number[]

export function nextStep(steps: Step[], hidden: ReadonlySet<string>, current: number): number | null

export function previousStep(steps: Step[], hidden: ReadonlySet<string>, current: number): number | null

export function stepOf(steps: Step[], key: string): number | null

export function firstStepWithError(steps: Step[], errorKeys: Iterable<string>): number | null

export function progress(steps: Step[], hidden: ReadonlySet<string>, current: number): Progress

export function firstInvalid(element: ParentNode): HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null

export function reportStep(element: ParentNode): boolean
