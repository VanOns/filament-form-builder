export type ConditionOperator =
    | 'equals'
    | 'not_equals'
    | 'empty'
    | 'not_empty'
    | 'greater_than'
    | 'at_least'
    | 'less_than'
    | 'at_most'

export interface ConditionRule {
    key: string
    operator: ConditionOperator
    value: string | null
}

/** What `$field->getConditions()->toArray()` and `$form->getFieldConditions()` give. */
export interface Conditions {
    match: 'all' | 'any'
    rules: ConditionRule[]
}

export type Answer = string | number | null | undefined | Array<string | number>

export type Answers = Record<string, Answer>

export function matches(value: Answer, operator: ConditionOperator, expected?: string | null): boolean

export function passes(conditions: Conditions | null | undefined, values: Answers): boolean

export function hiddenKeys(conditions: Record<string, Conditions> | null | undefined, values: Answers): Set<string>

export function isShown(conditions: Conditions | null | undefined, values: Answers, hidden?: Iterable<string>): boolean

export function readValues(form: HTMLFormElement): Record<string, string | string[]>
